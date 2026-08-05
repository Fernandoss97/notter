<?php

namespace Database\Factories;

use App\Models\Enums\ActivityType;
use App\Models\Enums\FocusSessionState;
use App\Models\StudyPlan;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudySession>
 */
class StudySessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'study_plan_id' => StudyPlan::factory(),
            'subject_id' => Subject::factory(),
            'topic_id' => Topic::factory(),
            'activity_type' => null,
            'state' => FocusSessionState::Running,
            'planned_seconds' => 50 * 60,
            'accumulated_seconds' => 0,
            'started_at' => now(),
            'resumed_at' => now(),
            'ended_at' => null,
            'exercises_total' => null,
            'exercises_correct' => null,
        ];
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => FocusSessionState::Paused,
            'resumed_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => FocusSessionState::Completed,
            'resumed_at' => null,
            'ended_at' => now(),
            'activity_type' => ActivityType::Exercises,
            'exercises_total' => 20,
            'exercises_correct' => 16,
        ]);
    }
}
