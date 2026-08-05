<?php

namespace App\Http\Controllers;

use App\Actions\Study\CompleteFocusSession;
use App\Actions\Study\Exceptions\InvalidFocusSessionTransitionException;
use App\Actions\Study\PauseFocusSession;
use App\Actions\Study\ResumeFocusSession;
use App\Actions\Study\StartFocusSession;
use App\Http\Requests\Study\CompleteFocusSessionRequest;
use App\Http\Requests\Study\StartFocusSessionRequest;
use App\Models\Enums\ActivityType;
use App\Models\Enums\FocusSessionState;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Topic;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FocusSessionController extends Controller
{
    /**
     * Show the focus screen: the current session (if any), the active
     * plan's subjects/topics for the pickers, and today's cycle count.
     */
    public function show(Request $request): Response
    {
        $user = $request->user();
        $plan = $user->activeStudyPlan()->with('subjects.topics')->first();
        $session = $this->currentSession($request);
        $settings = $user->pomodoroSetting()->firstOrCreate([]);

        $todayCycles = $user->studySessions()
            ->where('state', FocusSessionState::Completed)
            ->whereDate('ended_at', today())
            ->count();

        return Inertia::render('focus/show', [
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
                'elapsedSeconds' => $session->elapsedSeconds(),
                'plannedSeconds' => $session->planned_seconds,
            ] : null,
            'pomodoroSetting' => [
                'focusMinutes' => $settings->focus_minutes,
                'shortBreakMinutes' => $settings->short_break_minutes,
                'longBreakMinutes' => $settings->long_break_minutes,
                'cyclesBeforeLongBreak' => $settings->cycles_before_long_break,
            ],
            'todayCycles' => $todayCycles,
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
     * Stop the session and register what was studied (B4).
     */
    public function complete(CompleteFocusSessionRequest $request, CompleteFocusSession $action): RedirectResponse
    {
        return $this->transition(
            $request,
            fn (StudySession $session) => $action->handle(
                $session,
                Subject::findOrFail($request->integer('subject_id')),
                $request->filled('topic_id') ? Topic::find($request->integer('topic_id')) : null,
                ActivityType::from($request->string('activity_type')->toString()),
                $request->filled('exercises_total') ? $request->integer('exercises_total') : null,
                $request->filled('exercises_correct') ? $request->integer('exercises_correct') : null,
            ),
            __('Sessão registrada.'),
        );
    }

    /**
     * Resolve the user's current non-completed session (if any) and hand
     * it to a state-transition Action, flashing a toast either way. There
     * is never more than one, and it is never addressed by ID from the
     * client — B7's single-active-session rule makes the lookup implicit.
     *
     * @param  Closure(StudySession): StudySession  $handle
     */
    private function transition(Request $request, Closure $handle, ?string $successMessage = null): RedirectResponse
    {
        $session = $this->currentSession($request);

        if (! $session) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Nenhuma sessão de foco em andamento.')]);

            return to_route('focus.show');
        }

        try {
            $handle($session);
        } catch (InvalidFocusSessionTransitionException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return to_route('focus.show');
        }

        if ($successMessage) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $successMessage]);
        }

        return to_route('focus.show');
    }

    private function currentSession(Request $request): ?StudySession
    {
        return $request->user()->studySessions()
            ->where('state', '!=', FocusSessionState::Completed)
            ->latest()
            ->first();
    }

    /**
     * @return array{id: int, name: string, topics: array<int, array{id: int, name: string, reviewStatus: string|null}>}
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
     * @return array{id: int, name: string, reviewStatus: string|null}
     */
    private function topicProp(Topic $topic): array
    {
        return [
            'id' => $topic->id,
            'name' => $topic->name,
            'reviewStatus' => $topic->reviewStatus(),
        ];
    }
}
