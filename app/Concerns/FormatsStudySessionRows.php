<?php

namespace App\Concerns;

use App\Models\StudySession;
use App\Models\Subject;

trait FormatsStudySessionRows
{
    /**
     * @return array{date: string, subject: string, durationMinutes: int, activityType: string, exercises: int|null, accuracy: int|null}
     */
    protected function sessionRow(StudySession $session): array
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
