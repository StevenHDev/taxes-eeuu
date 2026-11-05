import { Deferred, Head, usePage } from '@inertiajs/react';
import type { TFunction } from 'i18next';
import { Download, FileSpreadsheet, Info } from 'lucide-react';
import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { BotonImprimir } from '@/components/ficha/boton-imprimir';
import { SeccionError } from '@/components/ficha/seccion-error';
import { SeccionHoja } from '@/components/hoja/seccion-hoja';
import { TotalesHoja } from '@/components/hoja/totales-hoja';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { ConfianzaPanel } from '@/components/visor/confianza-panel';
import { dashboard } from '@/routes';
import { index as clientesIndex } from '@/routes/clientes';
import type { FichaEncabezado } from '@/types/expediente';
import type { Hoja } from '@/types/hoja-taxwise';

type Props = {
    ficha: FichaEncabezado;
    hoja?: Hoja;
    csv_url: string;
};

function HojaSkeleton() {
    const { t } = useTranslation();

    return (
        <div
            className="space-y-6"
            role="status"
            aria-busy="true"
            aria-label={t('ficha.cargando')}
        >
            {[0, 1, 2].map((i) => (
                <div key={i} className="space-y-2">
                    <Skeleton className="h-6 w-48" />
                    <Skeleton className="h-20 w-full md:h-11" />
                    <Skeleton className="h-20 w-full md:h-11" />
                    <Skeleton className="h-20 w-full md:h-11" />
                </div>
            ))}
        </div>
    );
}

function HojaVacia() {
    const { t } = useTranslation();

    return (
        <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed p-8 text-center">
            <FileSpreadsheet
                className="size-8 text-muted-foreground"
                aria-hidden="true"
            />
            <p className="text-sm text-muted-foreground">{t('hoja.vacio')}</p>
        </div>
    );
}

/** El resumen en palabras: solo lo que no es cero. */
function textoConteos(hoja: Hoja, t: TFunction) {
    const c = hoja.conteos;

    return [
        t('hoja.conteos.filas', { count: c.filas }),
        c.pendientes > 0 &&
            t('hoja.conteos.pendientes', { count: c.pendientes }),
        c.baja_confianza > 0 &&
            t('hoja.conteos.dudas', { count: c.baja_confianza }),
        c.con_excepcion > 0 &&
            t('hoja.conteos.excepcion', { count: c.con_excepcion }),
        c.sin_validar > 0 &&
            t('hoja.conteos.sinValidar', { count: c.sin_validar }),
    ]
        .filter(Boolean)
        .join(' · ');
}

function ContenidoHoja({
    hoja,
    soloPendiente,
}: {
    hoja: Hoja;
    soloPendiente: boolean;
}) {
    const { t, i18n } = useTranslation();
    const panelRef = useRef<HTMLDivElement>(null);
    const hayConfianza = hoja.secciones.some((s) =>
        s.grupos.some((g) => g.filas.some((f) => f.confianza !== null)),
    );

    // Un solo panel de confianza para toda la página: el botón de cada fila
    // abre el mismo (su disparador vive en la barra de arriba).
    const abrirConfianza = () => {
        panelRef.current?.querySelector('button')?.click();
    };

    if (hoja.secciones.length === 0 && hoja.totales.length === 0) {
        return <HojaVacia />;
    }

    return (
        <div className="space-y-6">
            <div className="space-y-2">
                <p className="text-sm text-muted-foreground">
                    {t('hoja.generada', {
                        fecha: new Date(hoja.generado_en).toLocaleString(
                            i18n.language,
                            { dateStyle: 'medium', timeStyle: 'short' },
                        ),
                    })}
                </p>
                <p className="text-sm font-medium">{textoConteos(hoja, t)}</p>
                {hayConfianza ? (
                    <div ref={panelRef} className="print:hidden">
                        <ConfianzaPanel
                            tabla={hoja.confianza_tabla}
                            umbral={hoja.umbral}
                        />
                    </div>
                ) : null}
            </div>

            {soloPendiente &&
            hoja.conteos.pendientes +
                hoja.conteos.baja_confianza +
                hoja.conteos.con_excepcion +
                hoja.conteos.sin_validar ===
                0 ? (
                <p
                    role="status"
                    className="rounded-lg border p-3 text-sm text-muted-foreground print:hidden"
                >
                    {t('hoja.nadaPendiente')}
                </p>
            ) : null}

            {hoja.secciones.map((seccion) => (
                <SeccionHoja
                    key={seccion.codigo}
                    seccion={seccion}
                    soloPendiente={soloPendiente}
                    alAbrirConfianza={abrirConfianza}
                />
            ))}
            <TotalesHoja totales={hoja.totales} />
        </div>
    );
}

export default function FichaHojaTaxwise() {
    const { t } = useTranslation();
    const { ficha, csv_url } = usePage<Props>().props;
    const [soloPendiente, setSoloPendiente] = useState(false);

    return (
        <>
            <Head title={t('hoja.titulo')} />
            <div className="space-y-6">
                <header className="space-y-3">
                    <h2 className="font-display text-xl font-semibold">
                        {t('hoja.tituloAnio', { anio: ficha.tax_year })}
                    </h2>
                    <p className="flex items-start gap-2 text-sm text-muted-foreground">
                        <Info
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        {t('hoja.nota')}
                    </p>
                    <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center print:hidden">
                        <div className="flex min-h-11 items-center gap-2">
                            <Checkbox
                                id="hoja-solo-pendiente"
                                checked={soloPendiente}
                                onCheckedChange={(v) =>
                                    setSoloPendiente(v === true)
                                }
                            />
                            <Label htmlFor="hoja-solo-pendiente">
                                {t('hoja.soloPendiente')}
                            </Label>
                        </div>
                        <div className="flex flex-col gap-2 sm:ml-auto sm:flex-row">
                            <Button
                                asChild
                                variant="outline"
                                className="min-h-11 print:hidden"
                            >
                                <a href={csv_url}>
                                    <Download aria-hidden="true" />
                                    {t('hoja.descargarCsv')}
                                </a>
                            </Button>
                            <BotonImprimir etiqueta={t('hoja.imprimir')} />
                        </div>
                    </div>
                </header>

                <Deferred
                    data="hoja"
                    fallback={<HojaSkeleton />}
                    rescue={
                        <SeccionError
                            only={['hoja']}
                            titulo={t('hoja.errorTitulo')}
                            detalle={t('hoja.error')}
                        />
                    }
                >
                    <HojaConDatos soloPendiente={soloPendiente} />
                </Deferred>
            </div>
        </>
    );
}

function HojaConDatos({ soloPendiente }: { soloPendiente: boolean }) {
    const { hoja } = usePage<Props>().props;

    return hoja ? (
        <ContenidoHoja hoja={hoja} soloPendiente={soloPendiente} />
    ) : (
        <HojaSkeleton />
    );
}

FichaHojaTaxwise.layout = {
    breadcrumbs: [
        { title: 'nav.dashboard', href: dashboard() },
        { title: 'nav.clients', href: clientesIndex() },
    ],
};
