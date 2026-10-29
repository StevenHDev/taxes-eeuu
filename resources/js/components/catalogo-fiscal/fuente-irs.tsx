import { ExternalLink } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { FuenteIrs as FuenteIrsTipo } from '@/types';
import { EstadoValidacion } from './estado-validacion';

/**
 * Bloque "Fuente del IRS" del detalle de una pieza: el texto de la fuente,
 * el enlace para corroborarla y quién la validó.
 */
export function FuenteIrs({ pieza }: { pieza: FuenteIrsTipo }) {
    const { t, i18n } = useTranslation();

    return (
        <section className="space-y-2" aria-labelledby="fuente-irs-titulo">
            <div className="flex items-center justify-between gap-2">
                <h3 id="fuente-irs-titulo" className="text-sm font-medium">
                    {t('catalogoFiscal.detalle.fuente')}
                </h3>
                <EstadoValidacion pieza={pieza} />
            </div>

            {pieza.fuente_texto ? (
                <p className="text-sm text-muted-foreground">
                    {pieza.fuente_texto}
                </p>
            ) : null}

            {pieza.fuente_url ? (
                <a
                    href={pieza.fuente_url}
                    target="_blank"
                    rel="noreferrer noopener"
                    className="inline-flex items-center gap-1 rounded-sm text-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    {t('catalogoFiscal.detalle.verEnIrs')}
                    <ExternalLink className="size-3.5" aria-hidden="true" />
                    <span className="sr-only">
                        {t('catalogoFiscal.detalle.abreNuevaPestana')}
                    </span>
                </a>
            ) : null}

            {!pieza.fuente_texto && !pieza.fuente_url ? (
                <p className="text-sm text-muted-foreground">
                    {t('catalogoFiscal.detalle.sinFuente')}
                </p>
            ) : null}

            {pieza.validado_en ? (
                <p className="text-xs text-muted-foreground">
                    {t('catalogoFiscal.detalle.validadoPor', {
                        nombre: pieza.validado_por ?? '—',
                        fecha: new Date(pieza.validado_en).toLocaleDateString(
                            i18n.language,
                        ),
                    })}
                </p>
            ) : null}
        </section>
    );
}
