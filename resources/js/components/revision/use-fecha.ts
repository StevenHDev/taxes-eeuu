import { useTranslation } from 'react-i18next';

/** Formatea una fecha ISO (o de solo día) en el idioma activo. */
export function useFecha() {
    const { i18n } = useTranslation();

    return (iso: string) => {
        const fecha = /^\d{4}-\d{2}-\d{2}$/.test(iso)
            ? new Date(`${iso}T00:00:00`)
            : new Date(iso);

        return new Intl.DateTimeFormat(i18n.language, {
            dateStyle: 'medium',
        }).format(fecha);
    };
}
