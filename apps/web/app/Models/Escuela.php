<?php

namespace App\Models;

use Database\Factories\EscuelaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nombre
 * @property int $minutos_traslado
 * @property int $tiempo_limite_s
 * @property int|null $ciclo_actual_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('escuelas')]
#[Fillable(['nombre', 'minutos_traslado', 'tiempo_limite_s'])]
class Escuela extends Model
{
    /** @use HasFactory<EscuelaFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minutos_traslado' => 'integer',
            'tiempo_limite_s' => 'integer',
        ];
    }
}
