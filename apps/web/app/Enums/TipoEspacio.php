<?php

namespace App\Enums;

enum TipoEspacio: string
{
    case Aula = 'aula';
    case Laboratorio = 'laboratorio';
    case Computo = 'computo';
    case Cancha = 'cancha';
    case Cocina = 'cocina';
    case Taller = 'taller';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Aula => 'Aula',
            self::Laboratorio => 'Laboratorio',
            self::Computo => 'Cómputo',
            self::Cancha => 'Cancha',
            self::Cocina => 'Cocina',
            self::Taller => 'Taller',
        };
    }

    /**
     * Para los selects de la interfaz, en el orden de los casos.
     *
     * @return list<array{valor: string, etiqueta: string}>
     */
    public static function opciones(): array
    {
        return array_map(fn (self $tipo) => ['valor' => $tipo->value, 'etiqueta' => $tipo->etiqueta()], self::cases());
    }
}
