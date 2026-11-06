<?php

namespace Database\Factories;

use App\Models\PlanEstudios;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanEstudios>
 */
class PlanEstudiosFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clave' => fake()->unique()->bothify('PLAN-###'),
            'nombre' => 'Plan '.fake()->unique()->numberBetween(2000, 2999),
        ];
    }
}
