<?php

namespace Database\Factories;

use App\Enums\TipoEspacio;
use App\Models\Espacio;
use App\Models\Plantel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Sin `escuela_id`: el espacio (y su plantel, si no se pasa) toman la escuela actual.
 *
 * @extends Factory<Espacio>
 */
class EspacioFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plantel_id' => Plantel::factory(),
            'clave' => fake()->unique()->bothify('ES-####'),
            'nombre' => 'Aula '.fake()->unique()->numberBetween(1, 9999),
            'tipo' => TipoEspacio::Aula,
            'capacidad' => 40,
        ];
    }
}
