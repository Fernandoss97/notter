<?php

namespace Database\Factories;

use App\Models\CycleBlock;
use App\Models\CycleSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CycleBlock>
 */
class CycleBlockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cycle_slot_id' => CycleSlot::factory(),
            'lap' => 1,
            'sequence' => 1,
            'target_seconds' => 60 * 60,
            'accumulated_seconds' => 0,
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'accumulated_seconds' => $attributes['target_seconds'],
            'completed_at' => now(),
        ]);
    }
}
