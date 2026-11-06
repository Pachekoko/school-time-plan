<?php

namespace App\Models;

use App\Enums\EstadoCiclo;
use App\Models\Concerns\PerteneceAEscuela;
use Database\Factories\CicloFactory;
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
 * @property Carbon|null $inicio
 * @property Carbon|null $fin
 * @property EstadoCiclo $estado
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('ciclos')]
#[Fillable(['clave', 'inicio', 'fin', 'estado'])]
class Ciclo extends Model
{
    /** @use HasFactory<CicloFactory> */
    use HasFactory, PerteneceAEscuela;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inicio' => 'date',
            'fin' => 'date',
            'estado' => EstadoCiclo::class,
        ];
    }

    /**
     * @return HasMany<Grupo, $this>
     */
    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class);
    }

    /**
     * @return HasMany<Curso, $this>
     */
    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class);
    }

    /**
     * @return HasMany<Sincronia, $this>
     */
    public function sincronias(): HasMany
    {
        return $this->hasMany(Sincronia::class);
    }

    /**
     * @return HasMany<Corrida, $this>
     */
    public function corridas(): HasMany
    {
        return $this->hasMany(Corrida::class);
    }
}
