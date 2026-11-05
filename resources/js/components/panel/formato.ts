/** Fecha corta ("3 nov.") desde un ISO completo o una fecha "AAAA-MM-DD". */
export function fechaCorta(valor: string | null, locale: string): string {
    if (!valor) {
        return '';
    }

    const solo = /^(\d{4})-(\d{2})-(\d{2})$/.exec(valor);
    const fecha = solo
        ? new Date(Number(solo[1]), Number(solo[2]) - 1, Number(solo[3]))
        : new Date(valor);

    if (Number.isNaN(fecha.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat(locale, {
        day: 'numeric',
        month: 'short',
        year: solo ? 'numeric' : undefined,
    }).format(fecha);
}

/** "hoy", "ayer", "hace 3 días"; pasada una semana, la fecha corta. */
export function fechaRelativa(iso: string, locale: string): string {
    const fecha = new Date(iso);

    if (Number.isNaN(fecha.getTime())) {
        return '';
    }

    const inicioDia = (d: Date) =>
        new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();
    const dias = Math.round(
        (inicioDia(fecha) - inicioDia(new Date())) / 86_400_000,
    );

    if (dias < -7) {
        return fechaCorta(iso, locale);
    }

    return new Intl.RelativeTimeFormat(locale, { numeric: 'auto' }).format(
        dias,
        'day',
    );
}

export function numero(valor: number, locale: string): string {
    return new Intl.NumberFormat(locale, { maximumFractionDigits: 1 }).format(
        valor,
    );
}
