<?php

namespace Database\Factories;

use App\Enums\Contrato;
use App\Models\Profesor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profesor>
 */
class ProfesorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clave' => fake()->unique()->bothify('PR-###'),
            'nombre' => fake()->name(),
            'contrato' => Contrato::Asignatura,
            'horas_min' => 0,
            'horas_max' => 20,
            'max_dia' => 6,
            'sin_huecos' => false,
        ];
    }
}
