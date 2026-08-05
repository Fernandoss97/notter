<?php

namespace Database\Factories;

use App\Models\Enums\StudyPlanType;
use App\Models\StudyPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudyPlan>
 */
class StudyPlanFactory extends Factory
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
            'name' => fake()->words(3, true),
            'type' => StudyPlanType::Free,
            'is_active' => false,
            'weekly_goal_minutes' => fake()->numberBetween(10, 30) * 60,
        ];
    }

    public function contest(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => StudyPlanType::Contest,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}
