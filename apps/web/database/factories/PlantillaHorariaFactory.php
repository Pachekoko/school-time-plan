<?php

namespace Database\Factories;

use App\Models\PlantillaHoraria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlantillaHoraria>
 */
class PlantillaHorariaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clave' => fake()->unique()->bothify('T-###'),
            'nombre' => 'Turno '.fake()->unique()->word(),
        ];
    }
}
