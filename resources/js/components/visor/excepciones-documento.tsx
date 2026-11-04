import { AlertTriangle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import type { ExcepcionDocumento } from '@/types/lectura';

/**
 * Las excepciones abiertas de este documento, cada una con por qué saltó en
 * este caso (lo que dice el documento, no solo lo que revisa la regla) y, en
 * las que más trabajo dan, qué hacer. Las de año, tipo y archivo repetido no
 * son de una casilla, así que solo aparecen aquí.
 */
export function ExcepcionesDocumento({
    excepciones,
}: {
    excepciones: ExcepcionDocumento[];
}) {
    const { t } = useTranslation();

    if (excepciones.length === 0) {
        return null;
    }

    return (
        <section
            aria-labelledby="excepciones-documento"
            className="space-y-2 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-700/60 dark:bg-amber-950/30"
        >
            <h2
                id="excepciones-documento"
                className="flex items-center gap-2 text-sm font-medium"
            >
                <AlertTriangle
                    className="size-4 text-amber-700 dark:text-amber-400"
                    aria-hidden="true"
                />
                {t('visor.excepcionesTitulo', { count: excepciones.length })}
            </h2>
            <ul className="space-y-3">
                {excepciones.map((e) => (
                    <li key={e.id} className="space-y-1 text-sm">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="outline" className="font-mono">
                                {e.regla_codigo}
                            </Badge>
                            <Badge
                                variant={
                                    e.severidad === 'bloqueante'
                                        ? 'destructive'
                                        : 'secondary'
                                }
                            >
                                {t(`excepciones.severidad.${e.severidad}`)}
                            </Badge>
                        </div>
                        <p className="text-foreground">{e.caso}</p>
                        {e.que_hacer ? (
                            <p className="text-muted-foreground">
                                <span className="font-medium">
                                    {t('visor.queHacer')}
                                </span>{' '}
                                {e.que_hacer}
                            </p>
                        ) : null}
                        {e.regla_descripcion ? (
                            <p className="text-xs text-muted-foreground">
                                {t('visor.queRevisa')} {e.regla_descripcion}
                            </p>
                        ) : null}
                    </li>
                ))}
            </ul>
        </section>
    );
}
