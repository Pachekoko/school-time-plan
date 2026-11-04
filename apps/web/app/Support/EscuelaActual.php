<?php

namespace App\Support;

use Illuminate\Container\Attributes\Singleton;

/**
 * La escuela con la que trabaja la petición o el job en curso. Los modelos de dominio
 * filtran por ella (ver PerteneceAEscuela); sin escuela actual no ven nada.
 */
#[Singleton]
class EscuelaActual
{
    private ?int $id = null;

    public function id(): ?int
    {
        return $this->id;
    }

    public function establecer(?int $id): void
    {
        $this->id = $id;
    }

    /**
     * Corre `$fn` con `$id` como escuela actual y al salir restaura la anterior,
     * aunque `$fn` lance.
     *
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     */
    public function usar(int $id, callable $fn): mixed
    {
        $anterior = $this->id;
        $this->id = $id;

        try {
            return $fn();
        } finally {
            $this->id = $anterior;
        }
    }
}
