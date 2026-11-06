<?php

namespace App\Models;

use App\Enums\TipoFranja;
use App\Models\Concerns\PerteneceAEscuela;
use Database\Factories\FranjaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $escuela_id
 * @property int $plantilla_id
 * @property int $dia
 * @property int $orden
 * @property string $inicio
 * @property string $fin
 * @property TipoFranja $tipo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('franjas')]
#[Fillable(['dia', 'orden', 'inicio', 'fin', 'tipo'])]
class Franja extends Model
{
    /** @use HasFactory<FranjaFactory> */
    use HasFactory, PerteneceAEscuela;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dia' => 'integer',
            'orden' => 'integer',
            'tipo' => TipoFranja::class,
        ];
    }

    /**
     * @return BelongsTo<PlantillaHoraria, $this>
     */
    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(PlantillaHoraria::class, 'plantilla_id');
    }
}
