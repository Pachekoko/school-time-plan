<?php

namespace App\Enums;

/**
 * En la interfaz cada franja va con una letra: D (disponible, sin fila), P o N.
 */
enum EstadoDisponibilidad: string
{
    case Preferente = 'preferente';
    case NoDisponible = 'no_disponible';

    public function letra(): string
    {
        return match ($this) {
            self::Preferente => 'P',
            self::NoDisponible => 'N',
        };
    }

    /**
     * La letra de la interfaz; D (disponible) no tiene estado y da null.
     */
    public static function deLetra(string $letra): ?self
    {
        return match ($letra) {
            'P' => self::Preferente,
            'N' => self::NoDisponible,
            default => null,
        };
    }
}
