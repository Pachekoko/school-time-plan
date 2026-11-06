<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuiaRequest;
use Illuminate\Http\Response;

/**
 * Abre o cierra la guía de primeros pasos; se recuerda en el usuario, para cualquier computadora.
 * Responde sin contenido: el navegador no cambia de página al guardarlo.
 */
class GuiaController extends Controller
{
    public function update(GuiaRequest $request): Response
    {
        $request->user()?->forceFill(['guia_cerrada_en' => $request->boolean('abierta') ? null : now()])->save();

        return response()->noContent();
    }
}
