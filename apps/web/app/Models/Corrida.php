<?php

namespace App\Models;

use App\Enums\EstadoCorrida;
use App\Models\Concerns\PerteneceAEscuela;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un intento de generar el horario de un ciclo con el motor.
 *
 * @property int $id
 * @property int $escuela_id
 * @property int $ciclo_id
 * @property EstadoCorrida $estado
 * @property array<string, mixed> $parametros
 * @property int $tiempo_limite_s
 * @property int|null $puntaje
 * @property array<string, mixed>|null $desglose
 * @property list<array<string, mixed>>|null $diagnostico
 * @property array<string, mixed>|null $estadisticas
 * @property string|null $mensaje_error
 * @property int|null $origen_corrida_id
 * @property bool $publicada
 * @property int $version
 * @property Carbon|null $iniciada_en
 * @property Carbon|null $terminada_en
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('corridas')]
#[Fillable([
    'estado', 'parametros', 'tiempo_limite_s', 'puntaje', 'desglose', 'diagnostico', 'estadisticas',
    'mensaje_error', 'origen_corrida_id', 'publicada', 'version', 'iniciada_en', 'terminada_en',
])]
class Corrida extends Model
{
    use PerteneceAEscuela;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoCorrida::class,
            'parametros' => 'array',
            'tiempo_limite_s' => 'integer',
            'puntaje' => 'integer',
            'desglose' => 'array',
            'diagnostico' => 'array',
            'estadisticas' => 'array',
            'publicada' => 'boolean',
            'version' => 'integer',
            'iniciada_en' => 'datetime',
            'terminada_en' => 'datetime',
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
     * @return HasMany<Sesion, $this>
     */
    public function sesiones(): HasMany
    {
        return $this->hasMany(Sesion::class);
    }

    /**
     * @param  Builder<Corrida>  $consulta
     */
    #[Scope]
    protected function activas(Builder $consulta): void
    {
        $consulta->whereIn('estado', [EstadoCorrida::Pendiente, EstadoCorrida::Resolviendo]);
    }
}
