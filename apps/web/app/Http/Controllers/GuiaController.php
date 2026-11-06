<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuiaRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Abre o cierra la guía de primeros pasos; se recuerda en el usuario, para cualquier computadora.
 */
class GuiaController extends Controller
{
    public function update(GuiaRequest $request): RedirectResponse
    {
        $request->user()?->forceFill(['guia_cerrada_en' => $request->boolean('abierta') ? null : now()])->save();

        return back();
    }
}
