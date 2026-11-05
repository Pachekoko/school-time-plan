<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ReglasEscuela;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GrupoRequest extends FormRequest
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
            'plantel_id' => ['required', 'integer', $this->existe('planteles')],
            'plantilla_id' => ['required', 'integer', $this->existe('plantillas')],
            'plan_id' => ['required', 'integer', $this->existe('planes')],
            'semestre' => ['required', 'integer', 'between:1,12'],
            'clave' => [
                'required', 'string', 'max:30',
                $this->unica('grupos', $this->idEnRuta('grupo'), donde: ['ciclo_id' => $this->cicloActual()->id]),
            ],
            'alumnos' => ['required', 'integer', 'between:1,10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'plantel_id' => 'plantel',
            'plantilla_id' => 'plantilla',
            'plan_id' => 'plan',
        ];
    }
}
