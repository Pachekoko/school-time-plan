<?php

namespace App\Enums;

enum Contrato: string
{
    case Asignatura = 'asignatura';
    case MedioTiempo = 'medio_tiempo';
    case TiempoCompleto = 'tiempo_completo';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Asignatura => 'Por asignatura',
            self::MedioTiempo => 'Medio tiempo',
            self::TiempoCompleto => 'Tiempo completo',
        };
    }

    /**
     * @return list<array{valor: string, etiqueta: string}>
     */
    public static function opciones(): array
    {
        return array_map(fn (self $contrato) => ['valor' => $contrato->value, 'etiqueta' => $contrato->etiqueta()], self::cases());
    }
}
