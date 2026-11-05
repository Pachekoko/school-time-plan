<?php

namespace Database\Factories;

use App\Enums\TipoEspacio;
use App\Models\Materia;
use App\Models\PlanEstudios;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Materia>
 */
class MateriaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => PlanEstudios::factory(),
            'clave' => fake()->unique()->bothify('MAT-###'),
            'nombre' => 'Materia '.fake()->unique()->numberBetween(1, 9999),
            'semestre' => 1,
            'horas_semana' => 3,
            'patrones' => null,
            'tipo_espacio' => TipoEspacio::Aula,
        ];
    }
}
