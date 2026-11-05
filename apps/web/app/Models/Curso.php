<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEscuela;
use Database\Factories\CursoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Una materia que se da a uno o varios grupos juntos. Sin profesor, lo elige el motor;
 * vacante, no lleva profesor.
 *
 * @property int $id
 * @property int $escuela_id
 * @property int $ciclo_id
 * @property int $materia_id
 * @property int|null $profesor_id
 * @property bool $vacante
 * @property int|null $sincronia_id
 * @property int|null $alumnos
 * @property string $clave
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('cursos')]
#[Fillable(['materia_id', 'profesor_id', 'vacante', 'sincronia_id', 'alumnos'])]
class Curso extends Model
{
    /** @use HasFactory<CursoFactory> */
    use HasFactory, PerteneceAEscuela;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vacante' => 'boolean',
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
     * @return BelongsToMany<Grupo, $this>
     */
    public function grupos(): BelongsToMany
    {
        return $this->belongsToMany(Grupo::class, 'curso_grupo');
    }

    /**
     * @return BelongsTo<Materia, $this>
     */
    public function materia(): BelongsTo
    {
        return $this->belongsTo(Materia::class);
    }

    /**
     * @return BelongsTo<Profesor, $this>
     */
    public function profesor(): BelongsTo
    {
        return $this->belongsTo(Profesor::class);
    }

    /**
     * @return BelongsTo<Sincronia, $this>
     */
    public function sincronia(): BelongsTo
    {
        return $this->belongsTo(Sincronia::class);
    }
}
