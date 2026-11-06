<?php

namespace App\Http\Requests;

use App\Enums\Contrato;
use App\Enums\Rol;
use App\Http\Requests\Concerns\ReglasEscuela;
use App\Models\Profesor;
use App\Models\User;
use App\Support\EscuelaActual;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProfesorRequest extends FormRequest
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
            'clave' => ['required', 'string', 'max:30', $this->unica('profesores', $this->idEnRuta('profesor'))],
            'nombre' => ['required', 'string', 'max:120'],
            'contrato' => ['required', Rule::enum(Contrato::class)],
            'horas_min' => ['required', 'integer', 'between:0,168'],
            'horas_max' => ['required', 'integer', 'between:0,168', 'gte:horas_min'],
            'max_dia' => ['required', 'integer', 'between:1,24'],
            'sin_huecos' => ['nullable', 'boolean'],
            'planteles' => ['nullable', 'array'],
            'planteles.*' => ['bail', 'integer', 'distinct', $this->existe('planteles')],
            'materias' => ['nullable', 'array'],
            'materias.*' => ['bail', 'integer', 'distinct', $this->existe('materias')],
            'correo' => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'horas_min' => 'horas mínimas',
            'horas_max' => 'horas máximas',
            'max_dia' => 'máximo de horas por día',
            'planteles.*' => 'plantel',
            'materias.*' => 'materia',
        ];
    }

    /**
     * El correo crea una cuenta nueva o vincula la de un profesor de esta escuela que
     * todavía no está vinculado. Si el profesor ya tiene cuenta, se ignora.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $correo = $this->correo();
            $profesor = $this->route('profesor');

            if ($validator->errors()->has('correo') || $correo === null || ($profesor instanceof Profesor && $profesor->user_id !== null)) {
                return;
            }

            $usuario = User::firstWhere('email', $correo);

            if ($usuario === null) {
                return;
            }

            if ($usuario->escuela_id !== app(EscuelaActual::class)->id() || $usuario->rol !== Rol::Profesor) {
                $validator->errors()->add('correo', 'Ese correo ya tiene una cuenta que no se puede vincular.');
            } elseif (Profesor::query()->where('user_id', $usuario->id)->exists()) {
                $validator->errors()->add('correo', 'Ese correo ya está vinculado a otro profesor.');
            }
        }];
    }

    /**
     * Las columnas del profesor.
     *
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        return [
            ...$this->safe()->only(['clave', 'nombre', 'contrato', 'horas_min', 'horas_max', 'max_dia']),
            'sin_huecos' => $this->boolean('sin_huecos'),
        ];
    }

    /**
     * @return list<int>
     */
    public function planteles(): array
    {
        return array_values(array_map(intval(...), (array) $this->input('planteles', [])));
    }

    /**
     * @return list<int>
     */
    public function materias(): array
    {
        return array_values(array_map(intval(...), (array) $this->input('materias', [])));
    }

    /**
     * En minúsculas, como guarda Fortify los correos.
     */
    public function correo(): ?string
    {
        return $this->filled('correo') ? mb_strtolower(trim($this->string('correo')->value())) : null;
    }
}
