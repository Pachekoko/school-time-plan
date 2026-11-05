<?php

namespace App\Models;

use App\Enums\Contrato;
use App\Models\Concerns\PerteneceAEscuela;
use Database\Factories\ProfesorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $escuela_id
 * @property string $clave
 * @property string $nombre
 * @property Contrato $contrato
 * @property int $horas_min
 * @property int $horas_max
 * @property int $max_dia
 * @property bool $sin_huecos
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('profesores')]
#[Fillable(['clave', 'nombre', 'contrato', 'horas_min', 'horas_max', 'max_dia', 'sin_huecos'])]
class Profesor extends Model
{
    /** @use HasFactory<ProfesorFactory> */
    use HasFactory, PerteneceAEscuela;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contrato' => Contrato::class,
            'horas_min' => 'integer',
            'horas_max' => 'integer',
            'max_dia' => 'integer',
            'sin_huecos' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Plantel, $this>
     */
    public function planteles(): BelongsToMany
    {
        return $this->belongsToMany(Plantel::class, 'profesor_plantel');
    }

    /**
     * Las materias que puede dar.
     *
     * @return BelongsToMany<Materia, $this>
     */
    public function materias(): BelongsToMany
    {
        return $this->belongsToMany(Materia::class, 'profesor_materia');
    }

    /**
     * @return HasMany<Disponibilidad, $this>
     */
    public function disponibilidades(): HasMany
    {
        return $this->hasMany(Disponibilidad::class);
    }

    /**
     * La cuenta con la que entra a capturar su disponibilidad.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
