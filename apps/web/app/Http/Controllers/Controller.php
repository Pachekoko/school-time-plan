<?php

namespace App\Http\Controllers;

use App\Models\Ciclo;
use Illuminate\Http\Request;
use LogicException;

abstract class Controller
{
    /**
     * El ciclo actual que dejó el middleware `ciclo` en la petición.
     */
    protected function cicloActual(Request $request): Ciclo
    {
        $ciclo = $request->attributes->get('ciclo');

        return $ciclo instanceof Ciclo ? $ciclo : throw new LogicException('La ruta necesita el middleware ciclo.');
    }
}
