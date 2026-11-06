<?php

namespace App\Actions;

use Illuminate\Http\RedirectResponse;
use RuntimeException;

/**
 * El ciclo ya tiene una corrida en cola o resolviendo.
 */
final class CorridaEnCurso extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Ya hay una corrida en curso para este ciclo.');
    }

    public function render(): RedirectResponse
    {
        return back()->withErrors(['corrida' => $this->getMessage()]);
    }

    /**
     * Es un aviso para quien usa la app, no un error que haya que registrar.
     */
    public function report(): void {}
}
