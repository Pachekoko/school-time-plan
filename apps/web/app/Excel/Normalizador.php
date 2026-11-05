<?php

namespace App\Excel;

use App\Domain\Dias;
use DateTimeInterface;

/**
 * Lee las celdas como las deja Excel: números que llegan como 3.0, horas como fracción de
 * día, días escritos de mil formas, espacios de más.
 */
final class Normalizador
{
    private const array DIAS_LARGOS = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];

    /**
     * Sin espacios dobles ni al inicio o al final; vacío da null.
     */
    public static function texto(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        if (is_float($valor) && floor($valor) === $valor) {
            $valor = (int) $valor;
        }

        $texto = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', is_scalar($valor) ? (string) $valor : ''));

        return $texto === '' ? null : $texto;
    }

    /**
     * Acepta 3, «3» y 3.0; rechaza 3.5.
     *
     * @throws ValorInvalido
     */
    public static function entero(mixed $valor): ?int
    {
        $texto = self::texto($valor);

        if ($texto === null) {
            return null;
        }

        if (preg_match('/^-?\d+(\.0+)?$/', $texto) !== 1) {
            throw new ValorInvalido("«{$texto}» no es un número entero");
        }

        return (int) $texto;
    }

    /**
     * Acepta «7:00», «07:00:00», una fracción de día (0.2916… = 07:00) o una fecha con hora.
     *
     * @throws ValorInvalido
     */
    public static function hora(mixed $valor): ?string
    {
        if ($valor instanceof DateTimeInterface) {
            return $valor->format('H:i');
        }

        if (is_int($valor) || is_float($valor)) {
            $minutos = (int) round(($valor - floor($valor)) * 1440) % 1440;

            return sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
        }

        $texto = self::texto($valor);

        if ($texto === null) {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(:\d{2})?$/', $texto, $partes) !== 1 || (int) $partes[1] > 23 || (int) $partes[2] > 59) {
            throw new ValorInvalido("«{$texto}» no es una hora; escríbela como 07:00");
        }

        return sprintf('%02d:%02d', (int) $partes[1], (int) $partes[2]);
    }

    /**
     * «Lun», «lunes», «LUN», «Mié», «mie»… da 0 a 6 (lunes a domingo).
     *
     * @throws ValorInvalido
     */
    public static function dia(mixed $valor): int
    {
        $texto = self::texto($valor) ?? '';
        $simple = self::sinAcentos(mb_strtolower($texto));

        foreach (self::DIAS_LARGOS as $dia => $nombre) {
            if (strlen($simple) >= 3 && str_starts_with($nombre, $simple)) {
                return $dia;
            }
        }

        throw new ValorInvalido('«'.$texto.'» no es un día; usa '.implode(', ', Dias::CORTOS));
    }

    /**
     * S, Sí, 1 o verdadero es sí; N, No, 0, falso o vacío es no.
     *
     * @throws ValorInvalido
     */
    public static function siNo(mixed $valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }

        $texto = self::sinAcentos(mb_strtolower(self::texto($valor) ?? ''));

        return match ($texto) {
            's', 'si', '1', 'true', 'verdadero' => true,
            'n', 'no', '0', 'false', 'falso', '' => false,
            default => throw new ValorInvalido("«{$texto}» no es S ni N"),
        };
    }

    /**
     * Vacío o D es disponible; P, preferente; N, no disponible.
     *
     * @throws ValorInvalido
     */
    public static function disponibilidad(mixed $valor): string
    {
        $letra = mb_strtoupper(self::texto($valor) ?? 'D');

        return in_array($letra, ['D', 'P', 'N'], true)
            ? $letra
            : throw new ValorInvalido("«{$letra}» no es D, P ni N");
    }

    /**
     * Para comparar encabezados: minúsculas, sin acentos y con _ en lugar de espacios.
     */
    public static function encabezado(string $texto): string
    {
        return str_replace(' ', '_', self::sinAcentos(mb_strtolower(self::texto($texto) ?? '')));
    }

    /**
     * Para comparar nombres: minúsculas, sin acentos ni espacios dobles.
     */
    public static function comparable(?string $texto): string
    {
        return self::sinAcentos(mb_strtolower(self::texto($texto) ?? ''));
    }

    private static function sinAcentos(string $texto): string
    {
        return strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }
}
