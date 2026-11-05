<?php

namespace App\Http\Requests;

use App\Enums\TipoEspacio;
use App\Http\Requests\Concerns\ReglasEscuela;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EspacioRequest extends FormRequest
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
            'plantel_id' => ['bail', 'required', 'integer', $this->existe('planteles')],
            'clave' => ['required', 'string', 'max:30', $this->unica('espacios', $this->idEnRuta('espacio'))],
            'nombre' => ['required', 'string', 'max:120'],
            'tipo' => ['required', Rule::enum(TipoEspacio::class)],
            'capacidad' => ['required', 'integer', 'between:1,10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['plantel_id' => 'plantel'];
    }
}
