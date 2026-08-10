<?php

namespace App\Concerns;

use App\Models\StudySession;
use App\Models\Subject;

trait FormatsStudySessionRows
{
    /**
     * @return array{date: string, subject: string, durationMinutes: int, activityType: string, exercises: int|null, accuracy: int|null, pages: int|null}
     */
    protected function sessionRow(StudySession $session, bool $excludeBreaks = false): array
    {
        $subject = $session->subject;

        return [
            'date' => $session->ended_at?->format('d/m') ?? '',
            'subject' => $subject instanceof Subject ? $subject->name : 'Sessão livre',
            'durationMinutes' => (int) round($session->recordedSeconds($excludeBreaks) / 60),
            'activityType' => $session->activity_type === null ? 'exercises' : $session->activity_type->value,
            'exercises' => $session->exercises_total,
            'accuracy' => $session->accuracyPercent(),
            'pages' => $session->pages_read,
        ];
    }
}
