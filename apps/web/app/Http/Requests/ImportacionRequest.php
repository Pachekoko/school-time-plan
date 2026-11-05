<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ReglasEscuela;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportacionRequest extends FormRequest
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
            'archivo' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
            ...$this->reglasDeFormato(),
        ];
    }

    /**
     * El formato y, para el de UTEG, dónde van los grupos: plantel, plantillas por semestre y plan.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function reglasDeFormato(): array
    {
        return [
            'formato' => ['required', 'in:plantilla,uteg'],
            'plantel_id' => ['nullable', 'required_if:formato,uteg', 'integer', $this->existe('planteles')],
            'plantilla_1y2_id' => ['nullable', 'required_if:formato,uteg', 'integer', $this->existe('plantillas')],
            'plantilla_3a6_id' => ['nullable', 'required_if:formato,uteg', 'integer', $this->existe('plantillas')],
            'plan_id' => ['nullable', 'required_if:formato,uteg', 'integer', $this->existe('planes')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'plantel_id' => 'plantel',
            'plantilla_1y2_id' => 'plantilla de 1.º y 2.º',
            'plantilla_3a6_id' => 'plantilla de 3.º a 6.º',
            'plan_id' => 'plan',
        ];
    }

    public function esUteg(): bool
    {
        return $this->string('formato')->value() === 'uteg';
    }

    /**
     * @return array{plantel_id: int, plantilla_1y2_id: int, plantilla_3a6_id: int, plan_id: int}
     */
    public function opciones(): array
    {
        return [
            'plantel_id' => $this->integer('plantel_id'),
            'plantilla_1y2_id' => $this->integer('plantilla_1y2_id'),
            'plantilla_3a6_id' => $this->integer('plantilla_3a6_id'),
            'plan_id' => $this->integer('plan_id'),
        ];
    }
}
