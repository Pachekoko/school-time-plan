<?php

namespace Database\Factories;

use App\Models\Plantel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Sin `escuela_id`: el plantel toma la escuela actual (o la de `->for($escuela)`).
 *
 * @extends Factory<Plantel>
 */
class PlantelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clave' => fake()->unique()->bothify('PL-####'),
            'nombre' => 'Plantel '.fake()->unique()->city(),
        ];
    }
}
