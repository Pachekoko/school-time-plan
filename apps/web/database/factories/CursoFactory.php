<?php

namespace Database\Factories;

use App\Models\Ciclo;
use App\Models\Curso;
use App\Models\Materia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Sin grupos: se agregan con `$curso->grupos()->attach(...)`.
 *
 * @extends Factory<Curso>
 */
class CursoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ciclo_id' => Ciclo::factory(),
            'materia_id' => Materia::factory(),
            'profesor_id' => null,
            'vacante' => false,
            'sincronia_id' => null,
            'alumnos' => null,
            'clave' => fake()->unique()->bothify('CUR-####'),
        ];
    }
}
