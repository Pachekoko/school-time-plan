<?php

namespace Tests\Support;

use App\Models\Ciclo;
use App\Models\Escuela;
use App\Models\User;
use App\Support\EscuelaActual;

/**
 * Para las pruebas de las pantallas del admin: entra como admin de una escuela nueva y la
 * deja como escuela actual, así las factories crean dentro de ella.
 */
trait ActuaComoAdmin
{
    protected function comoAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        app(EscuelaActual::class)->establecer($admin->escuela_id);
        $this->actingAs($admin);

        return $admin;
    }

    /**
     * Corre `$fn` dentro de una escuela nueva y devuelve lo que regrese.
     *
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     */
    protected function enOtraEscuela(callable $fn): mixed
    {
        return app(EscuelaActual::class)->usar(Escuela::factory()->create()->id, $fn);
    }

    /**
     * Crea un ciclo en la escuela actual y lo deja como ciclo actual.
     */
    protected function conCicloActual(): Ciclo
    {
        $ciclo = Ciclo::factory()->create();
        Escuela::findOrFail(app(EscuelaActual::class)->id())->forceFill(['ciclo_actual_id' => $ciclo->id])->save();

        return $ciclo;
    }
}
