import { AlertTriangle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import type { DatoLeido, ExcepcionDocumento } from '@/types/lectura';

type Grupo = {
    codigo: string;
    severidad: ExcepcionDocumento['severidad'];
    titulo: string | null;
    detalle: string | null;
    queHacer: string | null;
    deCasilla: ExcepcionDocumento[];
};

/** La primera frase de la descripción de la regla es su título; el resto, cómo se calcula. */
function partirDescripcion(descripcion: string | null) {
    if (!descripcion) {
        return { titulo: null, detalle: null };
    }

    const corte = descripcion.search(/\.\s/);

    if (corte === -1) {
        return { titulo: descripcion, detalle: null };
    }

    return {
        titulo: descripcion.slice(0, corte + 1),
        detalle: descripcion.slice(corte + 2),
    };
}

/** Agrupa por regla las excepciones de una casilla; las que no lo son quedan aparte. */
function agrupar(excepciones: ExcepcionDocumento[]) {
    const grupos = new Map<string, Grupo>();
    const sueltas: ExcepcionDocumento[] = [];

    for (const e of excepciones) {
        if (!e.casilla) {
            sueltas.push(e);

            continue;
        }

        const grupo = grupos.get(e.regla_codigo) ?? {
            codigo: e.regla_codigo,
            severidad: e.severidad,
            ...partirDescripcion(e.regla_descripcion),
            queHacer: null,
            deCasilla: [],
        };

        grupo.deCasilla.push(e);
        grupos.set(e.regla_codigo, grupo);
    }

    for (const grupo of grupos.values()) {
        // "Qué hacer" una sola vez cuando es el mismo para todas las del grupo.
        const distintos = new Set(grupo.deCasilla.map((e) => e.que_hacer));
        const unico = [...distintos][0] ?? null;

        grupo.queHacer = distintos.size === 1 ? unico : null;
    }

    return { grupos: [...grupos.values()], sueltas };
}

function SeveridadBadge({
    severidad,
}: {
    severidad: ExcepcionDocumento['severidad'];
}) {
    const { t } = useTranslation();

    return (
        <Badge
            variant={severidad === 'bloqueante' ? 'destructive' : 'secondary'}
        >
            {t(`excepciones.severidad.${severidad}`)}
        </Badge>
    );
}

/**
 * Las excepciones abiertas de este documento. Las de una casilla se agrupan
 * por regla (la explicación sale una vez) y cada casilla es un botón que lleva
 * a su dato en la lista; las de año, tipo o archivo repetido no son de una
 * casilla, así que salen completas aquí.
 */
export function ExcepcionesDocumento({
    excepciones,
    datos,
    onIrADato,
}: {
    excepciones: ExcepcionDocumento[];
    datos: DatoLeido[];
    onIrADato: (datoId: number) => void;
}) {
    const { t } = useTranslation();

    if (excepciones.length === 0) {
        return null;
    }

    const { grupos, sueltas } = agrupar(excepciones);
    const datoDe = (casilla: string) =>
        datos.find((d) => d.casilla === casilla);

    return (
        <section
            aria-labelledby="excepciones-documento"
            className="space-y-3 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-700/60 dark:bg-amber-950/30"
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

            {sueltas.map((e) => (
                <article
                    key={e.id}
                    className="space-y-1 rounded-md bg-background/70 p-3 text-sm"
                >
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="outline" className="font-mono">
                            {e.regla_codigo}
                        </Badge>
                        <SeveridadBadge severidad={e.severidad} />
                    </div>
                    <p className="font-medium text-foreground">{e.caso}</p>
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
                </article>
            ))}

            {grupos.map((g) => (
                <article
                    key={g.codigo}
                    className="space-y-3 rounded-md bg-background/70 p-3 text-sm"
                >
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="outline" className="font-mono">
                                {g.codigo}
                            </Badge>
                            <SeveridadBadge severidad={g.severidad} />
                            <span className="text-xs text-muted-foreground">
                                {t('visor.casillasConExcepcion', {
                                    count: g.deCasilla.length,
                                })}
                            </span>
                        </div>
                        {g.titulo ? (
                            <p className="font-medium text-foreground">
                                {g.titulo}
                            </p>
                        ) : null}
                        {g.queHacer ? (
                            <p className="text-muted-foreground">
                                <span className="font-medium">
                                    {t('visor.queHacer')}
                                </span>{' '}
                                {g.queHacer}
                            </p>
                        ) : null}
                        {g.detalle ? (
                            <details className="text-xs text-muted-foreground">
                                <summary className="w-fit cursor-pointer rounded-sm py-1 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                                    {t('visor.comoSeCalcula')}
                                </summary>
                                <p className="mt-1">{g.detalle}</p>
                            </details>
                        ) : null}
                    </div>

                    <ul
                        className="flex flex-wrap gap-2"
                        aria-label={t('visor.casillasConExcepcionLista', {
                            regla: g.codigo,
                        })}
                    >
                        {g.deCasilla.map((e) => {
                            const dato = datoDe(e.casilla ?? '');

                            return (
                                <li key={e.id}>
                                    <button
                                        type="button"
                                        title={e.caso}
                                        disabled={!dato}
                                        onClick={() =>
                                            dato ? onIrADato(dato.id) : null
                                        }
                                        className="inline-flex min-h-11 items-center rounded-md border border-amber-400/70 bg-amber-100/60 px-3 font-mono text-xs transition-colors hover:bg-amber-200/70 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:bg-amber-200 disabled:cursor-not-allowed disabled:opacity-50 sm:min-h-8 dark:bg-amber-900/30 dark:hover:bg-amber-900/50"
                                    >
                                        <span className="font-mono font-medium">
                                            {e.casilla}
                                        </span>
                                        {dato ? (
                                            <span className="ml-2 max-w-52 truncate font-sans text-muted-foreground">
                                                {dato.descripcion}
                                            </span>
                                        ) : null}
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                </article>
            ))}
        </section>
    );
}
