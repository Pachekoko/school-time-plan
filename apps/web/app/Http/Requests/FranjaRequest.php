<?php

namespace App\Http\Requests;

use App\Domain\Hora;
use App\Domain\ReglasFranjas;
use App\Enums\TipoFranja;
use App\Models\Franja;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FranjaRequest extends FormRequest
{
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
            'inicio' => ['required', 'date_format:H:i'],
            'fin' => ['required', 'date_format:H:i'],
            'tipo' => ['required', Rule::enum(TipoFranja::class)],
        ];
    }

    /**
     * Con los cambios puestos, la franja no puede traslaparse con sus vecinas del día:
     * el error va en `inicio` si choca con la anterior y en `fin` si choca con la siguiente.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $franja = $this->route('franja');

            if ($validator->errors()->isNotEmpty() || ! $franja instanceof Franja) {
                return;
            }

            $inicio = $this->string('inicio')->value();
            $fin = $this->string('fin')->value();

            if (Hora::aMinutos($fin) <= Hora::aMinutos($inicio)) {
                $validator->errors()->add('fin', 'El fin debe ser posterior al inicio.');

                return;
            }

            $delDia = Franja::query()
                ->where('plantilla_id', $franja->plantilla_id)
                ->where('dia', $franja->dia)
                ->get(['id', 'dia', 'orden', 'inicio', 'fin'])
                ->map(fn (Franja $f) => [
                    'dia' => $f->dia,
                    'orden' => $f->orden,
                    'inicio' => $f->is($franja) ? $inicio : $f->inicio,
                    'fin' => $f->is($franja) ? $fin : $f->fin,
                ]);

            foreach (ReglasFranjas::traslapes($delDia) as $traslape) {
                if ($traslape['despues'] === $franja->orden) {
                    $validator->errors()->add('inicio', $traslape['mensaje']);
                } elseif ($traslape['antes'] === $franja->orden) {
                    $validator->errors()->add('fin', $traslape['mensaje']);
                }
            }
        }];
    }
}
