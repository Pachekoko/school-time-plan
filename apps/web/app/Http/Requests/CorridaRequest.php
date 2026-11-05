<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * La forma de lo que manda el formulario de generar; los valores los valida
 * ParametrosCorrida::normalizar.
 */
class CorridaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tiempo_limite_s' => ['nullable', 'integer', 'between:1,300'],
            'permitir_parcial' => ['nullable', 'boolean'],
            'reglas' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['tiempo_limite_s' => 'tiempo límite'];
    }

    /**
     * @return array<string, mixed>
     */
    public function entrada(): array
    {
        return array_filter(
            $this->only(['tiempo_limite_s', 'permitir_parcial', 'reglas']),
            fn (mixed $valor) => $valor !== null,
        );
    }
}
