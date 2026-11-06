<?php

namespace App\Enums;

enum TipoFranja: string
{
    case Clase = 'clase';
    case Receso = 'receso';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Clase => 'Clase',
            self::Receso => 'Receso',
        };
    }
}
