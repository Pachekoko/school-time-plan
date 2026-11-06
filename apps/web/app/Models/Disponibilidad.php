<?php

namespace App\Models;

use App\Enums\EstadoDisponibilidad;
use App\Models\Concerns\PerteneceAEscuela;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $escuela_id
 * @property int $profesor_id
 * @property int $franja_id
 * @property EstadoDisponibilidad $estado
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('disponibilidades')]
#[Fillable(['franja_id', 'estado'])]
class Disponibilidad extends Model
{
    use PerteneceAEscuela;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoDisponibilidad::class,
        ];
    }

    /**
     * @return BelongsTo<Profesor, $this>
     */
    public function profesor(): BelongsTo
    {
        return $this->belongsTo(Profesor::class);
    }

    /**
     * @return BelongsTo<Franja, $this>
     */
    public function franja(): BelongsTo
    {
        return $this->belongsTo(Franja::class);
    }
}
