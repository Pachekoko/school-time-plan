<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEscuela;
use Database\Factories\GrupoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $escuela_id
 * @property int $ciclo_id
 * @property int $plantel_id
 * @property int $plantilla_id
 * @property int $plan_id
 * @property int $semestre
 * @property string $clave
 * @property int $alumnos
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('grupos')]
#[Fillable(['plantel_id', 'plantilla_id', 'plan_id', 'semestre', 'clave', 'alumnos'])]
class Grupo extends Model
{
    /** @use HasFactory<GrupoFactory> */
    use HasFactory, PerteneceAEscuela;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'semestre' => 'integer',
            'alumnos' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Ciclo, $this>
     */
    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(Ciclo::class);
    }

    /**
     * @return BelongsTo<Plantel, $this>
     */
    public function plantel(): BelongsTo
    {
        return $this->belongsTo(Plantel::class);
    }

    /**
     * @return BelongsTo<PlantillaHoraria, $this>
     */
    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(PlantillaHoraria::class, 'plantilla_id');
    }

    /**
     * @return BelongsTo<PlanEstudios, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanEstudios::class, 'plan_id');
    }
}
