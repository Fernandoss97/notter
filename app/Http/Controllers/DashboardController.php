<?php

namespace App\Http\Controllers;

use App\Models\Enums\FocusSessionState;
use App\Models\StudyPlan;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        $plan = $user->activeStudyPlan()->with('subjects.topics.reviews')->first();

        $subjects = [];
        $topicsDone = 0;
        $topicsTotal = 0;
        $topicsDoneThisWeek = 0;
        $weekStart = now()->startOfWeek();

        if ($plan) {
            foreach ($plan->subjects as $subject) {
                $done = $subject->topics->filter(fn (Topic $topic) => $topic->isCompleted())->count();
                $total = $subject->topics->count();
                $topicsDone += $done;
                $topicsTotal += $total;
                $topicsDoneThisWeek += $subject->topics
                    ->filter(fn (Topic $topic) => $topic->completed_at !== null && $topic->completed_at->greaterThanOrEqualTo($weekStart))
                    ->count();

                $subjects[] = [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'topicsDone' => $done,
                    'topicsTotal' => $total,
                    'progress' => $total > 0 ? (int) round($done / $total * 100) : 0,
                    'reviewStatus' => $this->subjectReviewStatus($subject),
                ];
            }
        }

        $streaks = $this->streaks($user);

        return Inertia::render('dashboard', [
            'plan' => $plan ? [
                'id' => $plan->id,
                'name' => $plan->name,
                'weeklyGoalMinutes' => $plan->weekly_goal_minutes,
            ] : null,
            'stats' => [
                'todayMinutes' => $this->minutesStudied($user, today(), today()->endOfDay()),
                'todayGoalMinutes' => $plan instanceof StudyPlan ? (int) round($plan->weekly_goal_minutes / 7) : 0,
                'weekMinutes' => $this->minutesStudied($user, $weekStart, now()->endOfWeek()),
                'weekGoalMinutes' => $plan instanceof StudyPlan ? $plan->weekly_goal_minutes : 0,
                'topicsDone' => $topicsDone,
                'topicsTotal' => $topicsTotal,
                'topicsDoneThisWeek' => $topicsDoneThisWeek,
                'currentStreak' => $streaks['current'],
                'longestStreak' => $streaks['longest'],
            ],
            'subjects' => $subjects,
            'recentSessions' => $user->studySessions()
                ->where('state', FocusSessionState::Completed)
                ->with('subject')
                ->latest('ended_at')
                ->take(5)
                ->get()
                ->map($this->sessionRow(...))
                ->all(),
        ]);
    }

    private function minutesStudied(User $user, CarbonInterface $from, CarbonInterface $to): int
    {
        $completedSeconds = (int) $user->studySessions()
            ->where('state', FocusSessionState::Completed)
            ->whereBetween('ended_at', [$from, $to])
            ->sum('accumulated_seconds');

        $active = $user->studySessions()
            ->where('state', '!=', FocusSessionState::Completed)
            ->first();

        $activeSeconds = $active && $active->started_at->between($from, $to)
            ? $active->elapsedSeconds()
            : 0;

        return (int) round(($completedSeconds + $activeSeconds) / 60);
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

    /**
     * @return 'overdue'|null
     */
    private function subjectReviewStatus(Subject $subject): ?string
    {
        foreach ($subject->topics as $topic) {
            if ($topic->reviewStatus() === 'overdue') {
                return 'overdue';
            }
        }

        return null;
    }

    /**
     * @return array{date: string, subject: string, durationMinutes: int, activityType: string, exercises: int|null, accuracy: int|null}
     */
    private function sessionRow(StudySession $session): array
    {
        $subject = $session->subject;

        return [
            'date' => $session->ended_at?->format('d/m') ?? '',
            'subject' => $subject instanceof Subject ? $subject->name : 'Sessão livre',
            'durationMinutes' => (int) round($session->accumulated_seconds / 60),
            'activityType' => $session->activity_type === null ? 'exercises' : $session->activity_type->value,
            'exercises' => $session->exercises_total,
            'accuracy' => $session->accuracyPercent(),
        ];
    }
}
