<?php

namespace App\Models;

use App\Domain\Patrones;
use App\Enums\TipoEspacio;
use App\Models\Concerns\PerteneceAEscuela;
use Database\Factories\MateriaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $escuela_id
 * @property int $plan_id
 * @property string $clave
 * @property string $nombre
 * @property int $semestre
 * @property int $horas_semana
 * @property list<list<int>>|null $patrones
 * @property TipoEspacio $tipo_espacio
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('materias')]
#[Fillable(['plan_id', 'clave', 'nombre', 'semestre', 'horas_semana', 'patrones', 'tipo_espacio'])]
class Materia extends Model
{
    /** @use HasFactory<MateriaFactory> */
    use HasFactory, PerteneceAEscuela;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'semestre' => 'integer',
            'horas_semana' => 'integer',
            'patrones' => 'array',
            'tipo_espacio' => TipoEspacio::class,
        ];
    }

    /**
     * @return BelongsTo<PlanEstudios, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanEstudios::class, 'plan_id');
    }

    /**
     * Los patrones capturados o, si no hay, los derivados de las horas por semana.
     *
     * @return list<list<int>>
     */
    public function patronesEfectivos(): array
    {
        return $this->patrones ?? Patrones::derivar($this->horas_semana);
    }
}
