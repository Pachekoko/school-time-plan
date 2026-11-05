<?php

namespace App\Enums;

enum EstadoCiclo: string
{
    case Captura = 'captura';
    case Cerrado = 'cerrado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Captura => 'En captura',
            self::Cerrado => 'Cerrado',
        };
    }

    /**
     * @return list<array{valor: string, etiqueta: string}>
     */
    public static function opciones(): array
    {
        return array_map(fn (self $estado) => ['valor' => $estado->value, 'etiqueta' => $estado->etiqueta()], self::cases());
    }
}
