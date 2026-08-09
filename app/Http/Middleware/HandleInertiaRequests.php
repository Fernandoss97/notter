<?php

namespace App\Http\Middleware;

use App\Actions\Study\ResolveCurrentFocusSession;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(private ResolveCurrentFocusSession $resolveCurrentSession) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'activeSession' => $this->activeSessionProp($request),
        ];
    }

    /**
     * A sessão corrente para a barra persistente. `elapsedSeconds` é o
     * relógio da *fase*, não o total da sessão: depois do primeiro ciclo
     * o total passaria dos `plannedSeconds` e a barra de progresso ficaria
     * cravada em 100%.
     *
     * @return array{id: int, subject: string, topic: string, state: string, phase: string, completedCycles: int, elapsedSeconds: int, plannedSeconds: int}|null
     */
    private function activeSessionProp(Request $request): ?array
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $session = $this->resolveCurrentSession->handle($user, ['subject', 'topic']);

        if (! $session instanceof StudySession) {
            return null;
        }

        $subject = $session->subject;
        $topic = $session->topic;

        return [
            'id' => $session->id,
            'subject' => $subject instanceof Subject ? $subject->name : __('Sessão livre'),
            'topic' => $topic instanceof Topic ? $topic->name : __('Sem tópico'),
            'state' => $session->state->value,
            'phase' => $session->phase->value,
            // A barra é quem fecha o ciclo quando o tempo acaba, de
            // qualquer tela: precisa distinguir uma fase da seguinte.
            'completedCycles' => $session->completed_cycles,
            'elapsedSeconds' => $session->phaseElapsedSeconds(),
            'plannedSeconds' => $session->planned_seconds,
        ];
    }
}
