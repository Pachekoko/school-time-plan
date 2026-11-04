<?php

namespace App\Http\Requests\Concerns;

use App\Support\EscuelaActual;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;
use LogicException;

/**
 * Reglas `exists` y `unique` limitadas a la escuela actual, para que un formulario no
 * acepte ni choque con registros de otra escuela.
 */
trait ReglasEscuela
{
    protected function existe(string $tabla): Exists
    {
        return Rule::exists($tabla, 'id')->where('escuela_id', $this->escuelaActualId());
    }

    /**
     * @param  array<string, mixed>  $donde  columnas extra del alcance, por ejemplo `['ciclo_id' => 3]`
     */
    protected function unica(string $tabla, ?int $ignorar = null, string $columna = 'clave', array $donde = []): Unique
    {
        $regla = Rule::unique($tabla, $columna)->where('escuela_id', $this->escuelaActualId());

        foreach ($donde as $campo => $valor) {
            $regla->where($campo, $valor);
        }

        return $ignorar === null ? $regla : $regla->ignore($ignorar);
    }

    private function escuelaActualId(): int
    {
        return app(EscuelaActual::class)->id() ?? throw new LogicException('No hay escuela actual');
    }
}
