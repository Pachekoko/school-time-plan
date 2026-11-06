<?php

namespace App\Models\Concerns;

use App\Models\Escuela;
use App\Support\EscuelaActual;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Limita el modelo a la escuela actual. Falla cerrado: sin escuela actual las consultas
 * no devuelven nada y crear lanza.
 */
trait PerteneceAEscuela
{
    public static function bootPerteneceAEscuela(): void
    {
        static::addGlobalScope('escuela', function (Builder $consulta): void {
            $id = app(EscuelaActual::class)->id();

            if ($id === null) {
                $consulta->whereRaw('1 = 0');

                return;
            }

            $consulta->where($consulta->qualifyColumn('escuela_id'), $id);
        });

        static::creating(function (Model $modelo): void {
            if ($modelo->getAttribute('escuela_id') !== null) {
                return;
            }

            $modelo->setAttribute(
                'escuela_id',
                app(EscuelaActual::class)->id() ?? throw new LogicException('No hay escuela actual'),
            );
        });
    }

    /**
     * @return BelongsTo<Escuela, $this>
     */
    public function escuela(): BelongsTo
    {
        return $this->belongsTo(Escuela::class);
    }
}
