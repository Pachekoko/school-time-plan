<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ReglasEscuela;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SincroniaRequest extends FormRequest
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
            'clave' => [
                'required', 'string', 'max:30',
                $this->unica('sincronias', $this->idEnRuta('sincronia'), donde: ['ciclo_id' => $this->cicloActual()->id]),
            ],
            'nombre' => ['required', 'string', 'max:120'],
        ];
    }
}
