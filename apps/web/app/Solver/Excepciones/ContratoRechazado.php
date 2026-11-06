<?php

namespace App\Solver\Excepciones;

use RuntimeException;

/**
 * El motor respondió 422: el problema no cumple el contrato. Reintentar no sirve.
 */
class ContratoRechazado extends RuntimeException
{
    /**
     * @param  mixed  $detail  el `detail` de la respuesta: una lista de errores o un texto
     */
    public function __construct(public readonly mixed $detail)
    {
        parent::__construct('El motor rechazó el problema: '.$this->primerError());
    }

    /**
     * «grupos.0.alumnos: Input should be…»: la ruta del primer error (sin «body») y su mensaje.
     */
    public function primerError(): string
    {
        $primero = is_array($this->detail) ? ($this->detail[0] ?? null) : $this->detail;

        if (! is_array($primero)) {
            return is_scalar($primero) ? (string) $primero : 'respuesta 422 sin detalle';
        }

        $mensaje = is_string($primero['msg'] ?? null) ? $primero['msg'] : (string) json_encode($primero);
        $ruta = implode('.', array_filter((array) ($primero['loc'] ?? []), fn (mixed $parte) => $parte !== 'body'));

        return $ruta === '' ? $mensaje : "{$ruta}: {$mensaje}";
    }
}
