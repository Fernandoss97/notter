<?php

namespace Database\Factories;

use App\Models\Review;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'topic_id' => Topic::factory(),
            'sequence' => 1,
            'due_on' => now()->addDay()->toDateString(),
            'completed_at' => null,
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_on' => now()->subDays(2)->toDateString(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => now(),
        ]);
    }
}
