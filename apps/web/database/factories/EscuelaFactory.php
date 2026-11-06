<?php

namespace Database\Factories;

use App\Models\Escuela;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Escuela>
 */
class EscuelaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Prepa '.fake()->unique()->lastName(),
        ];
    }
}
