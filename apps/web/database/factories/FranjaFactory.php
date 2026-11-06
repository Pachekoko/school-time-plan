<?php

namespace Database\Factories;

use App\Enums\TipoFranja;
use App\Models\Franja;
use App\Models\PlantillaHoraria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Una franja suelta de lunes 07:00–07:50. Para una semana completa conviene generarla con
 * GeneradorFranjas.
 *
 * @extends Factory<Franja>
 */
class FranjaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plantilla_id' => PlantillaHoraria::factory(),
            'dia' => 0,
            'orden' => fake()->unique()->numberBetween(0, 500),
            'inicio' => '07:00',
            'fin' => '07:50',
            'tipo' => TipoFranja::Clase,
        ];
    }
}
