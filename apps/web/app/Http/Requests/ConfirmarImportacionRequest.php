<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Al confirmar ya no viene el archivo, pero sí el formato y sus opciones.
 */
class ConfirmarImportacionRequest extends ImportacionRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->reglasDeFormato();
    }
}
