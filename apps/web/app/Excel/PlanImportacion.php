<?php

namespace App\Excel;

/**
 * Lo que haría una importación: conteos por hoja, errores, avisos y las operaciones en
 * orden de dependencias (las aplica AplicadorImportacion).
 */
final class PlanImportacion
{
    /** @var array<string, array{crear: int, actualizar: int, sin_cambios: int}> */
    private array $conteos = [];

    /** @var list<array{hoja: string, fila: int, columna: string, mensaje: string}> */
    private array $errores = [];

    /** @var list<array{hoja: string, fila: int|null, mensaje: string}> */
    private array $avisos = [];

    /** @var list<array{tipo: string, datos: array<string, mixed>}> */
    private array $operaciones = [];

    public function __construct(public readonly int $escuelaId, public readonly int $cicloId) {}

    /**
     * @param  'crear'|'actualizar'|'sin_cambios'  $resultado
     */
    public function contar(string $hoja, string $resultado): void
    {
        $this->conteos[$hoja] ??= ['crear' => 0, 'actualizar' => 0, 'sin_cambios' => 0];
        $this->conteos[$hoja][$resultado]++;
    }

    public function hoja(string $hoja): void
    {
        $this->conteos[$hoja] ??= ['crear' => 0, 'actualizar' => 0, 'sin_cambios' => 0];
    }

    public function error(string $hoja, int $fila, string $columna, string $mensaje): void
    {
        $this->errores[] = ['hoja' => $hoja, 'fila' => $fila, 'columna' => $columna, 'mensaje' => $mensaje];
    }

    public function aviso(string $hoja, ?int $fila, string $mensaje): void
    {
        $this->avisos[] = ['hoja' => $hoja, 'fila' => $fila, 'mensaje' => $mensaje];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function operar(string $tipo, array $datos): void
    {
        $this->operaciones[] = ['tipo' => $tipo, 'datos' => $datos];
    }

    /**
     * @return array<string, array{crear: int, actualizar: int, sin_cambios: int}>
     */
    public function conteos(): array
    {
        return $this->conteos;
    }

    /**
     * @return list<array{hoja: string, fila: int, columna: string, mensaje: string}>
     */
    public function errores(): array
    {
        return $this->errores;
    }

    /**
     * @return list<array{hoja: string, fila: int|null, mensaje: string}>
     */
    public function avisos(): array
    {
        return $this->avisos;
    }

    /**
     * @return list<array{tipo: string, datos: array<string, mixed>}>
     */
    public function operaciones(): array
    {
        return $this->operaciones;
    }

    public function tieneErrores(): bool
    {
        return $this->errores !== [];
    }

    /**
     * @return array{crear: int, actualizar: int}
     */
    public function totales(): array
    {
        return [
            'crear' => array_sum(array_column($this->conteos, 'crear')),
            'actualizar' => array_sum(array_column($this->conteos, 'actualizar')),
        ];
    }
}
