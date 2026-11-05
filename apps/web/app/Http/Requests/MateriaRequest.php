<?php

namespace App\Http\Requests;

use App\Domain\Patrones;
use App\Enums\TipoEspacio;
use App\Http\Requests\Concerns\ReglasEscuela;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class MateriaRequest extends FormRequest
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
            'plan_id' => ['bail', 'required', 'integer', $this->existe('planes')],
            'clave' => ['required', 'string', 'max:30', $this->unica('materias', $this->idEnRuta('materia'))],
            'nombre' => ['required', 'string', 'max:120'],
            'semestre' => ['required', 'integer', 'between:1,12'],
            'horas_semana' => ['required', 'integer', 'between:1,40'],
            'patrones' => ['nullable', 'string', 'max:100'],
            'tipo_espacio' => ['required', Rule::enum(TipoEspacio::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'plan_id' => 'plan',
            'horas_semana' => 'horas por semana',
            'tipo_espacio' => 'tipo de espacio',
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('patrones')) {
                return;
            }

            try {
                $this->patrones();
            } catch (InvalidArgumentException $e) {
                $validator->errors()->add('patrones', $e->getMessage());
            }
        }];
    }

    /**
     * Lo validado, con los patrones ya convertidos (null si se dejaron vacíos).
     *
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        return [...$this->validated(), 'patrones' => $this->patrones()];
    }

    /**
     * @return list<list<int>>|null
     */
    private function patrones(): ?array
    {
        return $this->filled('patrones')
            ? Patrones::parsear($this->string('patrones')->value(), $this->integer('horas_semana'))
            : null;
    }
}
