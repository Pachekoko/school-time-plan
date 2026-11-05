<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ReglasEscuela;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PlanRequest extends FormRequest
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
            'clave' => ['required', 'string', 'max:30', $this->unica('planes', $this->idEnRuta('plan'))],
            'nombre' => ['required', 'string', 'max:120'],
        ];
    }
}
