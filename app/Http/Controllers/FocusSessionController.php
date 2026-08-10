<?php

namespace App\Http\Controllers;

use App\Actions\Study\AdvanceFocusPhase;
use App\Actions\Study\CompleteFocusSession;
use App\Actions\Study\Exceptions\InvalidFocusSessionTransitionException;
use App\Actions\Study\ExtendFocusPhase;
use App\Actions\Study\PauseFocusSession;
use App\Actions\Study\ResolveCurrentCycleBlock;
use App\Actions\Study\ResolveCurrentFocusSession;
use App\Actions\Study\ResumeFocusSession;
use App\Actions\Study\StartFocusSession;
use App\Actions\Study\SyncCycleBlockProgress;
use App\Concerns\AnnouncesCycleTurn;
use App\Concerns\ListsStudyPlans;
use App\Concerns\SummarizesCycleBlock;
use App\Concerns\SummarizesStudyTime;
use App\Http\Requests\Study\CompleteFocusSessionRequest;
use App\Http\Requests\Study\StartFocusSessionRequest;
use App\Http\Requests\Study\UpdateFocusSessionNotesRequest;
use App\Models\CycleBlock;
use App\Models\Enums\ActivityType;
use App\Models\Enums\FocusSessionState;
use App\Models\Review;
use App\Models\StudyPlan;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FocusSessionController extends Controller
{
    use AnnouncesCycleTurn;
    use ListsStudyPlans;
    use SummarizesCycleBlock;
    use SummarizesStudyTime;

    /**
     * Teto da fila de revisões da tela de foco: ela é um convite para a
     * próxima sessão, não o inventário completo de revisões atrasadas.
     */
    private const DUE_REVIEWS_LIMIT = 8;

    public function __construct(
        private ResolveCurrentFocusSession $resolveCurrentSession,
        private ResolveCurrentCycleBlock $resolveCurrentBlock,
        private SyncCycleBlockProgress $syncCycleBlock,
    ) {}

    /**
     * A tela principal do app: a sessão corrente (se houver), o plano
     * ativo para os seletores, o progresso do dia e a fila de revisões.
     */
    public function show(Request $request): Response
    {
        $user = $request->user();
        $plan = $user->activeStudyPlan()->with('subjects.topics.reviews')->first();
        $session = $this->currentSession($request);
        $settings = $user->pomodoroSetting()->firstOrCreate([]);

        // Pomodoros fechados hoje, não sessões: os pontos do ciclo na tela
        // são medidos em ciclos, e uma sessão pode conter vários.
        $todayPomodoros = (int) $user->studySessions()
            ->where('state', FocusSessionState::Completed)
            ->whereDate('ended_at', today())
            ->sum('completed_cycles');

        if ($session instanceof StudySession) {
            $todayPomodoros += $session->completed_cycles;
        }

        return Inertia::render('focus/show', [
            'plans' => $this->planOptions($request),
            'plan' => $plan ? [
                'id' => $plan->id,
                'name' => $plan->name,
                'subjects' => $plan->subjects->map($this->subjectProp(...))->all(),
            ] : null,
            'session' => $session ? [
                'id' => $session->id,
                'subjectId' => $session->subject_id,
                'topicId' => $session->topic_id,
                'activityType' => $session->activity_type?->value,
                'state' => $session->state->value,
                'phase' => $session->phase->value,
                'elapsedSeconds' => $session->phaseElapsedSeconds(),
                'plannedSeconds' => $session->planned_seconds,
                'focusSeconds' => $session->elapsedSeconds(),
                'completedCycles' => $session->completed_cycles,
                'notes' => $session->notes ?? '',
            ] : null,
            'pomodoroSetting' => [
                'focusMinutes' => $settings->focus_minutes,
                'shortBreakMinutes' => $settings->short_break_minutes,
                'longBreakMinutes' => $settings->long_break_minutes,
                'cyclesBeforeLongBreak' => $settings->cycles_before_long_break,
                'autoStartBreak' => $settings->auto_start_break,
                'autoStartFocus' => $settings->auto_start_focus,
                'logSessionOnFinish' => $settings->log_session_on_finish,
                'silentModeDuringFocus' => $settings->silent_mode_during_focus,
            ],
            'currentBlock' => $plan instanceof StudyPlan
                ? $this->cycleBlockProp($this->resolveCurrentBlock->handle($plan), $session)
                : null,
            'todayPomodoros' => $todayPomodoros,
            'today' => [
                'minutes' => $this->minutesStudied(
                    $user,
                    today(),
                    today()->endOfDay(),
                    $settings->exclude_breaks_from_study_time,
                    $session,
                ),
                'goalMinutes' => $plan instanceof StudyPlan ? (int) round($plan->weekly_goal_minutes / 7) : 0,
            ],
            'dueReviews' => $this->dueReviews($user),
        ]);
    }

    /**
     * Start a new focus session against the user's active plan.
     */
    public function start(StartFocusSessionRequest $request, StartFocusSession $action): RedirectResponse
    {
        $user = $request->user();
        $plan = $user->activeStudyPlan;

        if (! $plan) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Crie um plano de estudos antes de iniciar uma sessão.')]);

            return to_route('focus.show');
        }

        $subject = $request->filled('subject_id') ? Subject::find($request->integer('subject_id')) : null;
        $topic = $request->filled('topic_id') ? Topic::find($request->integer('topic_id')) : null;

        try {
            $action->handle($user, $plan, $subject, $topic);
        } catch (InvalidFocusSessionTransitionException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return to_route('focus.show');
    }

    public function pause(Request $request, PauseFocusSession $action): RedirectResponse
    {
        return $this->transition($request, fn (StudySession $session) => $action->handle($session));
    }

    public function resume(Request $request, ResumeFocusSession $action): RedirectResponse
    {
        return $this->transition($request, fn (StudySession $session) => $action->handle($session));
    }

    /**
     * Fecha o ciclo corrente e abre o próximo (B2). Chamado pelo cliente
     * quando o cronômetro chega ao fim, e ao pular um intervalo — o
     * servidor recalcula a expiração e recusa avanços prematuros.
     */
    public function advance(Request $request, AdvanceFocusPhase $action): RedirectResponse
    {
        $settings = $request->user()->pomodoroSetting()->firstOrCreate([]);
        $skip = $request->boolean('skip');

        return $this->transition(
            $request,
            fn (StudySession $session) => $action->handle($session, $settings, $skip),
        );
    }

    /**
     * Estica o ciclo de foco corrente em alguns minutos, para o caso de o
     * tempo acabar no meio de um raciocínio.
     */
    public function extend(Request $request, ExtendFocusPhase $action): RedirectResponse
    {
        return $this->transition(
            $request,
            fn (StudySession $session) => $action->handle($session),
        );
    }

    /**
     * Stop the session and register what was studied (B4).
     */
    public function complete(CompleteFocusSessionRequest $request, CompleteFocusSession $action): RedirectResponse
    {
        return $this->transition(
            $request,
            fn (StudySession $session) => $action->handle(
                $session,
                $request->filled('subject_id') ? Subject::find($request->integer('subject_id')) : null,
                $request->filled('topic_id') ? Topic::find($request->integer('topic_id')) : null,
                $request->filled('activity_type') ? ActivityType::from($request->string('activity_type')->toString()) : null,
                $request->filled('exercises_total') ? $request->integer('exercises_total') : null,
                $request->filled('exercises_correct') ? $request->integer('exercises_correct') : null,
                // `filled()` e não `string()`: sem a chave, `string()`
                // devolve '' e apagaria as notas que o autosave gravou
                // durante a sessão.
                $request->filled('notes') ? $request->string('notes')->toString() : null,
            ),
            __('Sessão registrada.'),
        );
    }

    /**
     * Salva as notas rápidas da sessão. Responde JSON em vez de redirect
     * porque o cliente grava enquanto o usuário digita: uma visita Inertia
     * a cada tecla re-renderizaria o cronômetro sem necessidade.
     */
    public function notes(UpdateFocusSessionNotesRequest $request): JsonResponse
    {
        $session = $this->currentSession($request);

        if (! $session instanceof StudySession) {
            return response()->json(['saved' => false], 409);
        }

        $session->update(['notes' => $request->string('notes')->toString()]);

        return response()->json(['saved' => true]);
    }

    /**
     * Resolve the user's current non-completed session (if any) and hand
     * it to a state-transition Action, flashing a toast either way. There
     * is never more than one, and it is never addressed by ID from the
     * client — B7's single-active-session rule makes the lookup implicit.
     *
     * Volta para a página de origem, não para a tela de foco: a barra de
     * sessão persegue o usuário por todas as telas, e pausar a partir do
     * plano não é um pedido para sair do plano.
     *
     * @param  Closure(StudySession): StudySession  $handle
     */
    private function transition(Request $request, Closure $handle, ?string $successMessage = null): RedirectResponse
    {
        $session = $this->currentSession($request);

        if (! $session) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Nenhuma sessão de foco em andamento.')]);

            return back();
        }

        try {
            $handle($session);
        } catch (InvalidFocusSessionTransitionException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        // Toda transição que congela tempo de foco passa por aqui — pausar,
        // avançar de fase e encerrar. É o ponto único onde o bloco do
        // rodízio se reconcilia com o que a sessão acumulou, e onde ele
        // fecha ao bater o alvo, fazendo o ciclo andar.
        $message = $this->syncBlockAndAnnounce($session, $successMessage);

        if ($message !== null) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
        }

        return back();
    }

    private function currentSession(Request $request): ?StudySession
    {
        return $this->resolveCurrentSession->handle($request->user());
    }

    /**
     * Reconcilia o bloco do rodízio e devolve o aviso a mostrar: o da
     * própria transição, ou ele acrescido do convite para a próxima
     * matéria quando o tempo da sessão fechou o bloco.
     */
    private function syncBlockAndAnnounce(StudySession $session, ?string $successMessage): ?string
    {
        $block = $session->cycleBlock()->first();

        if (! $block instanceof CycleBlock) {
            return $successMessage;
        }

        $wasOpen = ! $block->isCompleted();

        // O bloco reconciliado vem do retorno, e não da instância mutada:
        // "fechou agora" é a diferença entre dois valores, e lê melhor
        // como tal do que como um efeito colateral relido.
        $synced = $this->syncCycleBlock->handle($block);

        return $wasOpen && $synced->isCompleted()
            ? $this->cycleTurnMessage($session->studyPlan, $synced, $successMessage)
            : $successMessage;
    }

    /**
     * A fila de revisões devidas (C1). É a única porta de saída das
     * revisões que CompleteTopic cria: sem ela o ciclo de repetição
     * espaçada só acumula. Ordenada pela mais atrasada.
     *
     * @return array<int, array{id: int, subjectId: int, subjectName: string, topicId: int, topicName: string, sequence: int, dueOn: string, overdue: bool}>
     */
    private function dueReviews(User $user): array
    {
        return Review::query()
            ->forUser($user)
            ->due()
            ->with('topic.subject')
            ->orderBy('due_on')
            ->take(self::DUE_REVIEWS_LIMIT)
            ->get()
            ->map(fn (Review $review) => [
                'id' => $review->id,
                'subjectId' => $review->topic->subject->id,
                'subjectName' => $review->topic->subject->name,
                'topicId' => $review->topic->id,
                'topicName' => $review->topic->name,
                'sequence' => $review->sequence,
                'dueOn' => $review->due_on->format('d/m'),
                'overdue' => $review->due_on->isBefore(today()),
            ])
            ->all();
    }

    /**
     * @return array{id: int, name: string, topics: array<int, array{id: int, name: string, cycle: int, position: int, completed: bool, reviewStatus: string|null}>}
     */
    private function subjectProp(Subject $subject): array
    {
        return [
            'id' => $subject->id,
            'name' => $subject->name,
            'topics' => $subject->topics->map($this->topicProp(...))->all(),
        ];
    }

    /**
     * `cycle` e `position` viajam junto porque a sugestão do que estudar
     * agora é a ordem da trilha, e ela atravessa disciplinas — não dá para
     * reconstruí-la a partir da lista aninhada de cada disciplina.
     *
     * @return array{id: int, name: string, cycle: int, position: int, completed: bool, reviewStatus: string|null}
     */
    private function topicProp(Topic $topic): array
    {
        return [
            'id' => $topic->id,
            'name' => $topic->name,
            'cycle' => $topic->cycle,
            'position' => $topic->position,
            'completed' => $topic->isCompleted(),
            'reviewStatus' => $topic->reviewStatus(),
        ];
    }
}
