<?php

namespace App\Enums;

enum EstadoCorrida: string
{
    case Pendiente = 'pendiente';
    case Resolviendo = 'resolviendo';
    case Ok = 'ok';
    case FactibleParcial = 'factible_parcial';
    case SinSolucion = 'sin_solucion';
    case TiempoAgotado = 'tiempo_agotado';
    case Error = 'error';

    /**
     * En cola o resolviendo: a lo más una por ciclo.
     */
    public function activa(): bool
    {
        return $this === self::Pendiente || $this === self::Resolviendo;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'En cola',
            self::Resolviendo => 'Resolviendo',
            self::Ok => 'Listo',
            self::FactibleParcial => 'Parcial',
            self::SinSolucion => 'Sin solución',
            self::TiempoAgotado => 'Tiempo agotado',
            self::Error => 'Error',
        };
    }
}
