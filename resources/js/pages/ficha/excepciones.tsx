import { Deferred, Head, Link } from '@inertiajs/react';
import {
    CircleHelp,
    Info,
    OctagonAlert,
    PartyPopper,
    TriangleAlert,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ResolverExcepcionDialog } from '@/components/excepciones/resolver-dialog';
import { SeccionError } from '@/components/ficha/seccion-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { dashboard } from '@/routes';
import { index as clientesIndex } from '@/routes/clientes';
import type { Excepcion, SeveridadExcepcion } from '@/types/excepciones';
import type { FichaEncabezado } from '@/types/expediente';

const SEVERIDAD: Record<
    SeveridadExcepcion,
    { variant: 'destructive' | 'secondary' | 'outline'; icono: LucideIcon }
> = {
    bloqueante: { variant: 'destructive', icono: OctagonAlert },
    revision: { variant: 'secondary', icono: TriangleAlert },
    informativa: { variant: 'outline', icono: Info },
};

// La lista y los conteos del encabezado cambian al resolver una excepción.
const RECARGAR = ['excepciones', 'ficha'];

function ExcepcionesSkeleton() {
    const { t } = useTranslation();

    return (
        <div
            className="space-y-3"
            role="status"
            aria-busy="true"
            aria-label={t('excepciones.cargando')}
        >
            {[0, 1, 2].map((i) => (
                <Skeleton key={i} className="h-40 w-full rounded-xl" />
            ))}
        </div>
    );
}

function Vacio() {
    const { t } = useTranslation();

    return (
        <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed px-4 py-12 text-center">
            <PartyPopper
                className="size-8 text-muted-foreground"
                aria-hidden="true"
            />
            <h2 className="text-base font-medium">
                {t('ficha.excepciones.vacioTitulo')}
            </h2>
            <p className="max-w-md text-sm text-muted-foreground">
                {t('ficha.excepciones.vacioDescripcion')}
            </p>
        </div>
    );
}

/** La confianza en palabras: título, explicación en un tooltip y en texto para lectores de pantalla. */
function MotivoConfianza({
    motivo,
}: {
    motivo: NonNullable<Excepcion['motivo_confianza']>;
}) {
    const { t } = useTranslation();

    return (
        <div className="flex flex-wrap items-center gap-1">
            <Badge variant="outline">{motivo.titulo}</Badge>
            <Tooltip>
                <TooltipTrigger asChild>
                    <button
                        type="button"
                        className="inline-flex size-11 items-center justify-center rounded-full text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none sm:size-8"
                        aria-label={t('excepciones.motivo.porQue')}
                    >
                        <CircleHelp className="size-4" aria-hidden="true" />
                    </button>
                </TooltipTrigger>
                <TooltipContent className="max-w-xs">
                    {motivo.explicacion}
                </TooltipContent>
            </Tooltip>
            <span className="sr-only">{motivo.explicacion}</span>
        </div>
    );
}

function TarjetaExcepcion({ excepcion: e }: { excepcion: Excepcion }) {
    const { t, i18n } = useTranslation();
    const { variant, icono: Icono } = SEVERIDAD[e.severidad];
    const fecha = new Intl.DateTimeFormat(i18n.language, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(e.creada_en));

    return (
        <li className="space-y-3 rounded-xl border bg-card p-4 text-card-foreground">
            <div className="flex flex-wrap items-center gap-2">
                <Badge variant={variant}>
                    <Icono aria-hidden="true" />
                    {t(`excepciones.severidad.${e.severidad}`)}
                </Badge>
                {e.estado === 'consultada' ? (
                    <Badge variant="outline">
                        {t('excepciones.estado.consultada')}
                    </Badge>
                ) : null}
                <span className="font-mono text-xs font-medium">
                    {e.regla.codigo}
                </span>
                <time
                    dateTime={e.creada_en}
                    className="text-xs text-muted-foreground sm:ml-auto"
                >
                    {fecha}
                </time>
            </div>

            <div className="space-y-1.5">
                <p className="text-sm text-foreground">{e.caso}</p>
                <p
                    className="text-xs text-muted-foreground"
                    title={e.regla.descripcion}
                >
                    {t('excepciones.queRevisa')} {e.regla.descripcion}
                </p>
                {e.que_hacer ? (
                    <p className="text-xs text-muted-foreground">
                        <span className="font-medium">
                            {t('excepciones.queHacer')}
                        </span>{' '}
                        {e.que_hacer}
                    </p>
                ) : null}
            </div>

            <p className="text-sm text-muted-foreground">
                {e.item ? (
                    <>
                        <span className="text-foreground">{e.item.nombre}</span>
                        {e.casilla
                            ? ` · ${t('excepciones.casilla', { casilla: e.casilla })}`
                            : null}
                    </>
                ) : (
                    t('excepciones.sinDocumento')
                )}
            </p>

            {e.motivo_confianza ? (
                <div className="space-y-0.5">
                    <MotivoConfianza motivo={e.motivo_confianza} />
                    {e.enlaces.corregir ? (
                        <Link
                            href={e.enlaces.corregir}
                            className="text-xs text-muted-foreground underline-offset-4 hover:text-foreground hover:underline focus-visible:underline"
                        >
                            {t('excepciones.motivo.verCalculo')}
                        </Link>
                    ) : null}
                </div>
            ) : null}

            <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-start">
                {e.enlaces.corregir ? (
                    <Button asChild className="h-11 sm:h-9">
                        <Link href={e.enlaces.corregir}>
                            {t('excepciones.acciones.corregir')}
                        </Link>
                    </Button>
                ) : (
                    <div className="flex flex-col gap-0.5">
                        <Button disabled className="h-11 sm:h-9">
                            {t('excepciones.acciones.corregir')}
                        </Button>
                        <span className="text-xs text-muted-foreground">
                            {t('excepciones.acciones.sinDocumento')}
                        </span>
                    </div>
                )}
                <ResolverExcepcionDialog
                    action={e.enlaces.aceptar}
                    recargar={RECARGAR}
                    campo="nota"
                    titulo={t('excepciones.aceptar.titulo')}
                    descripcion={t('excepciones.aceptar.descripcion', {
                        regla: e.regla.codigo,
                        cliente: e.cliente.nombre,
                        caso: e.caso,
                    })}
                    etiqueta={t('excepciones.aceptar.etiqueta')}
                    placeholder={t('excepciones.aceptar.placeholder')}
                    enviar={t('excepciones.aceptar.enviar')}
                    trigger={
                        <Button variant="outline" className="h-11 sm:h-9">
                            {t('excepciones.acciones.aceptar')}
                        </Button>
                    }
                />
                {e.enlaces.consultar ? (
                    <ResolverExcepcionDialog
                        action={e.enlaces.consultar}
                        recargar={RECARGAR}
                        campo="texto"
                        titulo={t('excepciones.consultar.titulo')}
                        descripcion={t('excepciones.consultar.descripcion', {
                            cliente: e.cliente.nombre,
                        })}
                        etiqueta={t('excepciones.consultar.etiqueta')}
                        placeholder={t('excepciones.consultar.placeholder')}
                        enviar={t('excepciones.consultar.enviar')}
                        trigger={
                            <Button variant="ghost" className="h-11 sm:h-9">
                                {t('excepciones.acciones.consultar')}
                            </Button>
                        }
                    />
                ) : (
                    <div className="flex flex-col gap-0.5">
                        <Button
                            variant="ghost"
                            disabled
                            className="h-11 sm:h-9"
                        >
                            {t('excepciones.acciones.consultar')}
                        </Button>
                        <span className="max-w-64 text-xs text-muted-foreground">
                            {t('excepciones.acciones.consultarNo')}
                        </span>
                    </div>
                )}
            </div>
        </li>
    );
}

/** Pestaña Excepciones: las abiertas del cliente y del año, con sus tres salidas. */
export default function FichaExcepciones({
    ficha,
    excepciones,
}: {
    ficha: FichaEncabezado;
    excepciones?: Excepcion[];
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head
                title={`${t('ficha.pestanas.excepciones')} · ${ficha.cliente.name}`}
            />
            <div className="space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-base font-semibold text-foreground">
                        {t('ficha.excepciones.titulo', {
                            anio: ficha.tax_year,
                        })}
                    </h2>
                    <Button asChild variant="link" className="h-11 sm:h-9">
                        <Link href="/excepciones">
                            {t('ficha.excepciones.verCola')}
                        </Link>
                    </Button>
                </div>
                <Deferred
                    data="excepciones"
                    fallback={<ExcepcionesSkeleton />}
                    rescue={<SeccionError only={['excepciones']} />}
                >
                    {(excepciones ?? []).length === 0 ? (
                        <Vacio />
                    ) : (
                        <ul className="space-y-3">
                            {(excepciones ?? []).map((e) => (
                                <TarjetaExcepcion key={e.id} excepcion={e} />
                            ))}
                        </ul>
                    )}
                </Deferred>
            </div>
        </>
    );
}

FichaExcepciones.layout = {
    breadcrumbs: [
        { title: 'nav.dashboard', href: dashboard() },
        { title: 'nav.clients', href: clientesIndex() },
    ],
};
