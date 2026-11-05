<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ReglasEscuela;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `{celdas: [{franja_id, estado: 'D'|'P'|'N'}]}`: las franjas recibidas quedan con ese estado.
 */
class DisponibilidadRequest extends FormRequest
{
    use ReglasEscuela;

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
            'celdas' => ['present', 'array'],
            'celdas.*.franja_id' => ['bail', 'required', 'integer', 'distinct', $this->existe('franjas')],
            'celdas.*.estado' => ['required', Rule::in(['D', 'P', 'N'])],
        ];
    }

    /**
     * @return list<array{franja_id: int, estado: string}>
     */
    public function celdas(): array
    {
        return array_map(
            fn (array $celda) => ['franja_id' => (int) $celda['franja_id'], 'estado' => (string) $celda['estado']],
            array_values((array) $this->validated('celdas')),
        );
    }
}
