<?php

namespace App\Http\Requests;

use App\Enums\EstadoCiclo;
use App\Http\Requests\Concerns\ReglasEscuela;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CicloRequest extends FormRequest
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
            'clave' => ['required', 'string', 'max:30', $this->unica('ciclos', $this->idEnRuta('ciclo'))],
            'inicio' => ['nullable', 'date_format:Y-m-d'],
            'fin' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('inicio'), 'after_or_equal:inicio')],
            'estado' => ['required', Rule::enum(EstadoCiclo::class)],
        ];
    }
}
