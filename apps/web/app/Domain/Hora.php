<?php

namespace App\Domain;

/**
 * Horas del día como texto «HH:MM» (24 h, con cero a la izquierda) y como minutos desde
 * las 00:00. Así las guarda la base y así las pide el contrato del motor.
 */
final class Hora
{
    public static function aMinutos(string $hora): int
    {
        [$h, $m] = explode(':', $hora);

        return (int) $h * 60 + (int) $m;
    }

    public static function deMinutos(int $minutos): string
    {
        return sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
    }
}
