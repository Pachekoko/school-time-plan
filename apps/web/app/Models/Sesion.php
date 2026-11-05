<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEscuela;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un bloque de un curso en una corrida: `duracion` franjas seguidas desde `orden_inicio`
 * el día `dia`, o sin acomodar.
 *
 * @property int $id
 * @property int $escuela_id
 * @property int $corrida_id
 * @property int $curso_id
 * @property int $patron
 * @property int $bloque
 * @property int|null $dia
 * @property int|null $orden_inicio
 * @property int $duracion
 * @property int|null $espacio_id
 * @property int|null $profesor_id
 * @property bool $fijada
 * @property bool $sin_acomodar
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('sesiones')]
#[Fillable(['curso_id', 'patron', 'bloque', 'dia', 'orden_inicio', 'duracion', 'espacio_id', 'profesor_id', 'fijada', 'sin_acomodar'])]
class Sesion extends Model
{
    use PerteneceAEscuela;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'patron' => 'integer',
            'bloque' => 'integer',
            'dia' => 'integer',
            'orden_inicio' => 'integer',
            'duracion' => 'integer',
            'fijada' => 'boolean',
            'sin_acomodar' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Corrida, $this>
     */
    public function corrida(): BelongsTo
    {
        return $this->belongsTo(Corrida::class);
    }

    /**
     * @return BelongsTo<Curso, $this>
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /**
     * @return BelongsTo<Espacio, $this>
     */
    public function espacio(): BelongsTo
    {
        return $this->belongsTo(Espacio::class);
    }

    /**
     * @return BelongsTo<Profesor, $this>
     */
    public function profesor(): BelongsTo
    {
        return $this->belongsTo(Profesor::class);
    }
}
