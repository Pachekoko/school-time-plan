<?php

namespace App\Http\Requests\Concerns;

use App\Models\Ciclo;
use App\Support\EscuelaActual;
use Illuminate\Database\Eloquent\Model;
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

    /**
     * El id del modelo enlazado en la ruta (al editar), o null (al crear).
     */
    protected function idEnRuta(string $parametro): ?int
    {
        $modelo = $this->route($parametro);

        return $modelo instanceof Model ? (int) $modelo->getKey() : null;
    }

    /**
     * El ciclo actual que dejó el middleware `ciclo` en la petición.
     */
    protected function cicloActual(): Ciclo
    {
        $ciclo = $this->attributes->get('ciclo');

        return $ciclo instanceof Ciclo ? $ciclo : throw new LogicException('La ruta necesita el middleware ciclo.');
    }

    private function escuelaActualId(): int
    {
        return app(EscuelaActual::class)->id() ?? throw new LogicException('No hay escuela actual');
    }
}
