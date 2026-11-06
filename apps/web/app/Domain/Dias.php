<?php

namespace App\Domain;

/**
 * Los días van de 0 (lunes) a 6 (domingo), como en el contrato del motor.
 */
final class Dias
{
    /** Los mismos nombres cortos que usan los mensajes del motor. */
    public const array CORTOS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

    public static function corto(int $dia): string
    {
        return self::CORTOS[$dia];
    }
}
