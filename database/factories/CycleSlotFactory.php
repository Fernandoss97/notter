<?php

namespace Database\Factories;

use App\Models\CycleSlot;
use App\Models\Enums\CycleSlotState;
use App\Models\StudyPlan;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CycleSlot>
 */
class CycleSlotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'study_plan_id' => StudyPlan::factory(),
            'subject_id' => Subject::factory(),
            'position' => 0,
            'block_minutes' => 60,
            'state' => CycleSlotState::Active,
        ];
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => CycleSlotState::Paused,
        ]);
    }
}
