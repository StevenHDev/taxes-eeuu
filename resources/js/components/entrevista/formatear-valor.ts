import { useTranslation } from 'react-i18next';
import type { TipoRespuesta, ValorEntrevista } from '@/types/entrevista';

export type ValorConPregunta = {
    tipo: TipoRespuesta;
    opciones: { valor: string; texto: string }[];
    valor: ValorEntrevista;
};

/**
 * Un valor guardado, en palabras del idioma de quien mira: el resumen del
 * cliente (2.7) y la ficha del preparador (2.9) lo muestran igual.
 * `claveQuien` cambia "Yo" y "Mi cónyuge" por "Cliente" y "Cónyuge" en
 * la ficha.
 */
export function useFormatearValor(claveQuien = 'entrevista.quien') {
    const { t, i18n } = useTranslation();

    return (p: ValorConPregunta): string => {
        const v = p.valor;
        const opcion = (valor: string) =>
            p.opciones.find((o) => o.valor === valor)?.texto ?? valor;

        if (v === null || v === '') {
            return t('entrevista.resumen.sinResponder');
        }

        switch (p.tipo) {
            case 'si_no':
                return v === 'si' ? t('entrevista.si') : t('entrevista.no');
            case 'usted_conyuge_no':
                return Array.isArray(v)
                    ? v.map((q) => t(`${claveQuien}.${q}`)).join(' · ')
                    : t('entrevista.no');
            case 'opciones':
                return opcion(String(v));
            case 'casillas':
                return Array.isArray(v) ? v.map(opcion).join(' · ') : String(v);
            case 'fecha':
                return new Intl.DateTimeFormat(i18n.language, {
                    dateStyle: 'long',
                    timeZone: 'UTC',
                }).format(new Date(`${v}T00:00:00Z`));
            default:
                return String(v);
        }
    };
}
