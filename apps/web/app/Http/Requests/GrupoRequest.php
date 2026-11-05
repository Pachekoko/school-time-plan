<?php

namespace App\Http\Requests;

use App\Domain\CursosCompartidos;
use App\Http\Requests\Concerns\ReglasEscuela;
use App\Models\Grupo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

    /**
     * Un grupo no se mueve de plantel o de plantilla sin los grupos con los que comparte cursos.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $grupo = $this->route('grupo');

            if (! $grupo instanceof Grupo || $validator->errors()->hasAny(['plantel_id', 'plantilla_id'])) {
                return;
            }

            $plantel = $this->integer('plantel_id');
            $plantilla = $this->integer('plantilla_id');

            if ($grupo->plantel_id === $plantel && $grupo->plantilla_id === $plantilla) {
                return;
            }

            /** @var list<string> $partidos */
            $partidos = $grupo->cursos()
                ->whereHas('grupos', fn (Builder $otros) => $otros->whereKeyNot($grupo->id)
                    ->where(fn (Builder $lugar) => $lugar->where('plantel_id', '!=', $plantel)->orWhere('plantilla_id', '!=', $plantilla)))
                ->orderBy('cursos.clave')
                ->pluck('cursos.clave')
                ->all();

            if ($partidos !== []) {
                $validator->errors()->add($grupo->plantel_id !== $plantel ? 'plantel_id' : 'plantilla_id', CursosCompartidos::mensaje($grupo->clave, $partidos));
            }
        }];
    }
}
