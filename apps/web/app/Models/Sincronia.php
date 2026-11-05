<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEscuela;
use Database\Factories\SincroniaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $escuela_id
 * @property int $ciclo_id
 * @property string $clave
 * @property string $nombre
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('sincronias')]
#[Fillable(['clave', 'nombre'])]
class Sincronia extends Model
{
    /** @use HasFactory<SincroniaFactory> */
    use HasFactory, PerteneceAEscuela;

    /**
     * @return BelongsTo<Ciclo, $this>
     */
    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(Ciclo::class);
    }

    /**
     * @return HasMany<Curso, $this>
     */
    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class);
    }
}
