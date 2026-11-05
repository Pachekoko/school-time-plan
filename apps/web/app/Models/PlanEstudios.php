<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEscuela;
use Database\Factories\PlanEstudiosFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $escuela_id
 * @property string $clave
 * @property string $nombre
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('planes')]
#[Fillable(['clave', 'nombre'])]
class PlanEstudios extends Model
{
    /** @use HasFactory<PlanEstudiosFactory> */
    use HasFactory, PerteneceAEscuela;

    /**
     * @return HasMany<Materia, $this>
     */
    public function materias(): HasMany
    {
        return $this->hasMany(Materia::class, 'plan_id');
    }

    /**
     * @return HasMany<Grupo, $this>
     */
    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class, 'plan_id');
    }
}
