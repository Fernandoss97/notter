<?php

namespace App\Http\Controllers;

use App\Actions\Study\ResolveCurrentFocusSession;
use App\Concerns\CalculatesPercentage;
use App\Concerns\SummarizesStudyTime;
use App\Concerns\SummarizesTrackPace;
use App\Models\Enums\FocusSessionState;
use App\Models\StudyPlan;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use CalculatesPercentage;
    use SummarizesStudyTime;
    use SummarizesTrackPace;

    /**
     * Dias mostrados no gráfico de barras — uma semana móvel, que é o
     * horizonte em que a meta semanal faz sentido.
     */
    private const RECENT_DAYS = 7;

    /**
     * Abreviações indexadas por `dayOfWeek` (0 = domingo). Explícitas
     * porque o locale da aplicação é `en`: pedir o nome traduzido ao
     * Carbon devolveria "Mon".
     *
     * @var array<int, string>
     */
    private const WEEKDAY_LABEL = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];

    public function __construct(private ResolveCurrentFocusSession $resolveCurrentSession) {}

    /**
     * A tela de retrospectiva. Não repete o que já está a um clique:
     * sessão por sessão é o histórico e o dia de hoje é a própria tela de
     * foco. Aqui fica o que nenhuma delas mostra — a semana contra a meta,
     * a constância e a evolução por disciplina (ver evolution()).
     */
    public function show(Request $request): Response
    {
        $user = $request->user();
        $plan = $user->activeStudyPlan()->with('subjects.topics')->first();
        $settings = $user->pomodoroSetting()->firstOrCreate([]);
        $excludeBreaks = $settings->exclude_breaks_from_study_time;
        $activeSession = $this->resolveCurrentSession->handle($user);

        $topicsDone = 0;
        $topicsTotal = 0;
        $topicsDoneThisWeek = 0;
        $weekStart = now()->startOfWeek();

        if ($plan) {
            foreach ($plan->subjects as $subject) {
                $topicsDone += $subject->topics->filter(fn (Topic $topic) => $topic->isCompleted())->count();
                $topicsTotal += $subject->topics->count();
                $topicsDoneThisWeek += $subject->topics
                    ->filter(fn (Topic $topic) => $topic->completed_at !== null && $topic->completed_at->greaterThanOrEqualTo($weekStart))
                    ->count();
            }
        }

        $streaks = $this->streaks($user);

        return Inertia::render('dashboard', [
            // Mesma ordem estável da tela de plano: o seletor é feito de
            // alvos clicáveis, e promover o ativo para a frente os faria
            // dançar a cada troca.
            'plans' => $user->studyPlans()->orderBy('name')->get()
                ->map(fn (StudyPlan $each) => [
                    'id' => $each->id,
                    'name' => $each->name,
                ])->all(),
            'plan' => $plan ? [
                'id' => $plan->id,
                'name' => $plan->name,
                'weeklyGoalMinutes' => $plan->weekly_goal_minutes,
            ] : null,
            'stats' => [
                'weekMinutes' => $this->minutesStudied($user, $weekStart, now()->endOfWeek(), $excludeBreaks, $activeSession),
                'weekGoalMinutes' => $plan instanceof StudyPlan ? $plan->weekly_goal_minutes : 0,
                'topicsDone' => $topicsDone,
                'topicsTotal' => $topicsTotal,
                'topicsDoneThisWeek' => $topicsDoneThisWeek,
                'currentStreak' => $streaks['current'],
                'longestStreak' => $streaks['longest'],
            ],
            'recentDays' => $this->recentDays($user, $excludeBreaks),
            'evolution' => $plan instanceof StudyPlan ? $this->evolution($plan) : null,
            'pace' => $plan instanceof StudyPlan ? $this->trackPace($plan) : null,
        ]);
    }

    /**
     * Evolução por disciplina: tarefas, questões e acerto, mais a linha de
     * totais.
     *
     * É a única exceção ao "não repete disciplina por disciplina": o plano
     * mostra o avanço das tarefas, mas o acerto em questões — a medida de
     * onde o estudo está falhando — não aparece em nenhuma outra tela.
     *
     * @return array{rows: array<int, array{id: int, name: string, tasksDone: int, tasksTotal: int, progress: int, exercises: int, correct: int, accuracy: int|null}>, totals: array{tasksDone: int, tasksTotal: int, progress: int, exercises: int, correct: int, accuracy: int|null}}
     */
    private function evolution(StudyPlan $plan): array
    {
        $rows = $plan->subjects->map(function (Subject $subject) {
            $tasksTotal = $subject->topics->count();
            $tasksDone = $subject->topics->filter(fn (Topic $topic) => $topic->isCompleted())->count();
            $exercises = (int) $subject->topics->sum('exercises_total');
            $correct = (int) $subject->topics->sum('exercises_correct');

            return [
                'id' => $subject->id,
                'name' => $subject->name,
                'tasksDone' => $tasksDone,
                'tasksTotal' => $tasksTotal,
                'progress' => $this->percent($tasksDone, $tasksTotal) ?? 0,
                'exercises' => $exercises,
                'correct' => $correct,
                'accuracy' => $this->percent($correct, $exercises),
            ];
        });

        $tasksDone = (int) $rows->sum('tasksDone');
        $tasksTotal = (int) $rows->sum('tasksTotal');
        $exercises = (int) $rows->sum('exercises');
        $correct = (int) $rows->sum('correct');

        return [
            'rows' => $rows->all(),
            'totals' => [
                'tasksDone' => $tasksDone,
                'tasksTotal' => $tasksTotal,
                'progress' => $this->percent($tasksDone, $tasksTotal) ?? 0,
                'exercises' => $exercises,
                'correct' => $correct,
                'accuracy' => $this->percent($correct, $exercises),
            ],
        ];
    }

    /**
     * Minutos estudados em cada um dos últimos sete dias, do mais antigo
     * para hoje. Dias sem estudo entram com zero — a lacuna é a
     * informação, e uma barra ausente não a comunicaria.
     *
     * @return array<int, array{date: string, weekday: string, minutes: int}>
     */
    private function recentDays(User $user, bool $excludeBreaks): array
    {
        $from = today()->subDays(self::RECENT_DAYS - 1);

        $byDay = $user->studySessions()
            ->where('state', FocusSessionState::Completed)
            ->where('ended_at', '>=', $from)
            ->toBase()
            ->selectRaw('DATE(ended_at) as day')
            ->selectRaw('COALESCE(SUM(accumulated_seconds), 0) as focus_seconds')
            ->selectRaw('COALESCE(SUM(break_seconds), 0) as break_seconds')
            ->groupBy('day')
            ->get()
            ->keyBy(fn (object $row) => (string) $row->day);

        return collect(range(0, self::RECENT_DAYS - 1))
            ->map(function (int $offset) use ($from, $byDay, $excludeBreaks) {
                $day = $from->copy()->addDays($offset);
                $row = $byDay->get($day->toDateString());

                $seconds = (int) ($row->focus_seconds ?? 0)
                    + ($excludeBreaks ? 0 : (int) ($row->break_seconds ?? 0));

                return [
                    'date' => $day->format('d/m'),
                    'weekday' => self::WEEKDAY_LABEL[$day->dayOfWeek],
                    'minutes' => (int) round($seconds / 60),
                ];
            })
            ->all();
    }

    /**
     * @return array{current: int, longest: int}
     */
    private function streaks(User $user): array
    {
        $days = $user->studySessions()
            ->where('state', FocusSessionState::Completed)
            ->selectRaw('DATE(ended_at) as day')
            ->distinct()
            ->pluck('day')
            ->sort()
            ->values()
            ->map(fn (string $day) => Carbon::parse($day));

        if ($days->isEmpty()) {
            return ['current' => 0, 'longest' => 0];
        }

        $longest = 1;
        $run = 1;

        for ($i = 1; $i < $days->count(); $i++) {
            $run = abs((int) $days[$i]->diffInDays($days[$i - 1])) === 1 ? $run + 1 : 1;
            $longest = max($longest, $run);
        }

        $daySet = $days->map(fn (Carbon $day) => $day->toDateString())->flip();
        $cursor = $daySet->has(today()->toDateString()) ? today() : today()->subDay();
        $current = 0;

        while ($daySet->has($cursor->toDateString())) {
            $current++;
            $cursor = $cursor->copy()->subDay();
        }

        return ['current' => $current, 'longest' => $longest];
    }
}
