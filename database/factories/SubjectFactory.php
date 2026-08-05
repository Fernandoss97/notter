<?php

namespace Database\Factories;

use App\Models\Enums\SubjectPriority;
use App\Models\StudyPlan;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
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
            'name' => fake()->words(2, true),
            'priority' => SubjectPriority::Medium,
            'goal_minutes' => fake()->numberBetween(5, 20) * 60,
            'position' => 0,
        ];
    }

    public function priority(SubjectPriority $priority): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $priority,
        ]);
    }
}
