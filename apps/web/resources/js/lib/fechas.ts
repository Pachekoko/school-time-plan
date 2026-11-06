const formato = new Intl.DateTimeFormat('es-MX', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

/** «2026-02-01» → «1 feb 2026»; sin fecha, una raya. */
export function fechaCorta(fecha: string | null): string {
    return fecha ? formato.format(new Date(`${fecha}T00:00:00`)) : '—';
}
