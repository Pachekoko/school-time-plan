<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ReglasEscuela;
use App\Models\Grupo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CursoRequest extends FormRequest
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
        $ciclo = $this->cicloActual()->id;

        return [
            'materia_id' => ['bail', 'required', 'integer', $this->existe('materias')],
            'grupos' => ['required', 'array', 'min:1'],
            'grupos.*' => ['bail', 'integer', 'distinct', $this->existe('grupos')->where('ciclo_id', $ciclo)],
            'profesor_id' => ['bail', 'nullable', 'integer', $this->existe('profesores')],
            'vacante' => ['nullable', 'boolean'],
            'sincronia_id' => ['bail', 'nullable', 'integer', $this->existe('sincronias')->where('ciclo_id', $ciclo)],
            'alumnos' => ['nullable', 'integer', 'between:1,10000'],
            'clave' => ['nullable', 'string', 'max:120', $this->unica('cursos', $this->idEnRuta('curso'), donde: ['ciclo_id' => $ciclo])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'grupos.*.distinct' => 'Un grupo no puede repetirse en el mismo curso.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'materia_id' => 'materia',
            'grupos.*' => 'grupo',
            'profesor_id' => 'profesor',
            'sincronia_id' => 'sincronía',
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->boolean('vacante') && $this->filled('profesor_id')) {
                $validator->errors()->add('profesor_id', 'Un curso vacante no lleva profesor.');
            }

            if ($validator->errors()->has('grupos') || $validator->errors()->has('grupos.*')) {
                return;
            }

            $grupos = Grupo::query()->whereKey($this->grupos())->get(['plantel_id', 'plantilla_id']);

            if ($grupos->pluck('plantel_id')->unique()->count() > 1 || $grupos->pluck('plantilla_id')->unique()->count() > 1) {
                $validator->errors()->add('grupos', 'Los grupos de un curso deben ser del mismo plantel y la misma plantilla.');
            }
        }];
    }

    /**
     * Las columnas del curso (sin clave ni ciclo).
     *
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        return [
            'materia_id' => $this->integer('materia_id'),
            'profesor_id' => $this->filled('profesor_id') ? $this->integer('profesor_id') : null,
            'vacante' => $this->boolean('vacante'),
            'sincronia_id' => $this->filled('sincronia_id') ? $this->integer('sincronia_id') : null,
            'alumnos' => $this->filled('alumnos') ? $this->integer('alumnos') : null,
        ];
    }

    /**
     * @return list<int>
     */
    public function grupos(): array
    {
        return array_values(array_map(intval(...), (array) $this->input('grupos', [])));
    }
}
