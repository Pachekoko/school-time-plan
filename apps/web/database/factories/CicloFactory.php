<?php

namespace Database\Factories;

use App\Enums\EstadoCiclo;
use App\Models\Ciclo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ciclo>
 */
class CicloFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clave' => fake()->unique()->numerify('20##-').fake()->randomElement(['A', 'B']),
            'estado' => EstadoCiclo::Captura,
        ];
    }
}
