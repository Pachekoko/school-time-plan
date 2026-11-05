<?php

namespace Database\Factories;

use App\Models\Ciclo;
use App\Models\Sincronia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sincronia>
 */
class SincroniaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ciclo_id' => Ciclo::factory(),
            'clave' => fake()->unique()->bothify('SIN-###'),
            'nombre' => 'Sincronía '.fake()->unique()->numberBetween(1, 999),
        ];
    }
}
