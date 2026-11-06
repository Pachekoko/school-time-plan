// Los días van de 0 (lunes) a 6 (domingo), como en el contrato del motor.
export const DIAS_CORTOS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

export const DIAS_LARGOS = [
    'Lunes',
    'Martes',
    'Miércoles',
    'Jueves',
    'Viernes',
    'Sábado',
    'Domingo',
];

/** «Lun–Vie» si los días son seguidos; si no, «Lun, Mié, Vie». */
export function rangoDias(dias: number[]): string {
    if (dias.length === 0) {
        return '—';
    }

    const ordenados = [...dias].sort((a, b) => a - b);
    const seguidos = ordenados.every(
        (dia, i) => i === 0 || dia === ordenados[i - 1] + 1,
    );

    if (seguidos && ordenados.length > 2) {
        return `${DIAS_CORTOS[ordenados[0]]}–${DIAS_CORTOS[ordenados[ordenados.length - 1]]}`;
    }

    return ordenados.map((dia) => DIAS_CORTOS[dia]).join(', ');
}
