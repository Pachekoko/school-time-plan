<?php

namespace Database\Factories;

use App\Models\Ciclo;
use App\Models\Grupo;
use App\Models\PlanEstudios;
use App\Models\Plantel;
use App\Models\PlantillaHoraria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grupo>
 */
class GrupoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ciclo_id' => Ciclo::factory(),
            'plantel_id' => Plantel::factory(),
            'plantilla_id' => PlantillaHoraria::factory(),
            'plan_id' => PlanEstudios::factory(),
            'semestre' => 1,
            'clave' => fake()->unique()->bothify('#?##'),
            'alumnos' => 30,
        ];
    }
}
