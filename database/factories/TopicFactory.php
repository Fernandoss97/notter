<?php

namespace Database\Factories;

use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Topic>
 */
class TopicFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'cycle' => 0,
            'name' => fake()->sentence(3),
            'position' => 0,
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => now(),
        ]);
    }

    /**
     * Posiciona a tarefa na fila da trilha: em que ciclo ela cai e em que
     * ponto da sequência do plano.
     */
    public function inQueue(int $cycle, int $position): static
    {
        return $this->state(fn (array $attributes) => [
            'cycle' => $cycle,
            'position' => $position,
        ]);
    }
}
