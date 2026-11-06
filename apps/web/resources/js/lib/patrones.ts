// Espejo de App\Domain\Patrones (PHP): el formulario de materias muestra en vivo el
// patrón que usará la materia si no se captura uno.

/** Bloques de 2 h y uno de 1 h si sobra; 2 h admite también 1+1. */
export function derivarPatrones(horas: number): number[][] {
    if (horas === 2) {
        return [[2], [1, 1]];
    }

    const bloques: number[] = Array.from(
        { length: Math.floor(horas / 2) },
        () => 2,
    );

    if (horas % 2 === 1) {
        bloques.push(1);
    }

    return [bloques];
}

/** `[[2], [1, 1]]` → «2 | 1+1». */
export function formatearPatrones(patrones: number[][]): string {
    return patrones.map((alternativa) => alternativa.join('+')).join(' | ');
}
