<?php

namespace App\Http\Controllers;

use App\Concerns\FormatsStudySessionRows;
use App\Models\Enums\ActivityType;
use App\Models\Enums\FocusSessionState;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HistoryController extends Controller
{
    use FormatsStudySessionRows;

    /**
     * @var array<int, string>
     */
    private const PERIODS = ['7', '30', 'all'];

    public function index(Request $request): Response
    {
        $request->validate([
            'period' => ['nullable', Rule::in(self::PERIODS)],
            'type' => ['nullable', Rule::in([
                'all',
                ...array_map(fn (ActivityType $type) => $type->value, ActivityType::cases()),
            ])],
        ]);

        $user = $request->user();
        $period = $request->string('period', '7')->toString();
        $type = $request->string('type', 'all')->toString();

        $query = $user->studySessions()
            ->where('state', FocusSessionState::Completed)
            ->with('subject');

        if ($period !== 'all') {
            $query->where('ended_at', '>=', now()->subDays((int) $period));
        }

        if ($type !== 'all') {
            $query->where('activity_type', $type);
        }

        $total = (clone $query)->count();

        return Inertia::render('history/index', [
            'filters' => ['period' => $period, 'type' => $type],
            'total' => $total,
            'sessions' => $query->latest('ended_at')
                ->take(50)
                ->get()
                ->map($this->sessionRow(...))
                ->all(),
        ]);
    }
}
