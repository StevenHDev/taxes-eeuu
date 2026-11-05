export function formatearFechaHora(iso: string, idioma: string): string {
    return new Intl.DateTimeFormat(idioma, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(iso));
}

export function formatearFecha(iso: string, idioma: string): string {
    return new Intl.DateTimeFormat(idioma, { dateStyle: 'medium' }).format(
        new Date(iso),
    );
}
