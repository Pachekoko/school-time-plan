<?php

namespace App\Http\Requests;

use App\Domain\Hora;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GenerarFranjasRequest extends FormRequest
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
            'dias' => ['required', 'array', 'min:1'],
            'dias.*' => ['integer', 'between:0,6', 'distinct'],
            'inicio' => ['required', 'date_format:H:i'],
            'numero' => ['required', 'integer', 'between:1,20'],
            'duracion' => ['required', 'integer', 'between:10,240'],
            'receso_tras' => ['nullable', 'integer', 'min:1', 'lt:numero'],
            'receso_min' => ['nullable', 'integer', 'between:5,120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'dias' => 'días',
            'dias.*' => 'día',
            'numero' => 'número de franjas',
            'duracion' => 'duración',
            'receso_tras' => 'receso después de la franja',
            'receso_min' => 'duración del receso',
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $p = $this->parametros();
            $fin = Hora::aMinutos($p['inicio']) + $p['n'] * $p['duracionMin']
                + ($p['recesoTras'] === null ? 0 : $p['recesoMin']);

            if ($fin > 23 * 60 + 59) {
                $validator->errors()->add('numero', 'Las franjas no caben en el día: terminarían a las '.Hora::deMinutos($fin).'.');
            }
        }];
    }

    /**
     * Los argumentos de GeneradorFranjas::generar.
     *
     * @return array{dias: list<int>, inicio: string, n: int, duracionMin: int, recesoTras: int|null, recesoMin: int}
     */
    public function parametros(): array
    {
        $dias = array_map(intval(...), (array) $this->input('dias'));
        sort($dias);

        return [
            'dias' => $dias,
            'inicio' => $this->string('inicio')->value(),
            'n' => $this->integer('numero'),
            'duracionMin' => $this->integer('duracion'),
            'recesoTras' => $this->filled('receso_tras') ? $this->integer('receso_tras') : null,
            'recesoMin' => $this->filled('receso_min') ? $this->integer('receso_min') : 30,
        ];
    }
}
