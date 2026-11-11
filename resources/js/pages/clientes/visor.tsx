import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, FileSearch } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DocumentoEstado } from '@/components/entrevista/documento-estado';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { ConfianzaPanel } from '@/components/visor/confianza-panel';
import { CorregirDatoDialog } from '@/components/visor/corregir-dato-dialog';
import { ExcepcionesDocumento } from '@/components/visor/excepciones-documento';
import { ListaDatos } from '@/components/visor/lista-datos';
import { PaginaDocumento } from '@/components/visor/pagina-documento';
import { dashboard } from '@/routes';
import { index as clientesIndex, show as clienteShow } from '@/routes/clientes';
import type { EstadoDocumento } from '@/types/entrevista';
import type {
    ArchivoVisor,
    DatoLeido,
    ExcepcionDocumento,
    FilaConfianza,
    Hoja,
    ItemVisor,
    LecturaVisor,
    ValorRevelado,
} from '@/types/lectura';

type Props = {
    cliente: { id: number; name: string };
    item: ItemVisor;
    archivos: ArchivoVisor[];
    lecturas: LecturaVisor[];
    excepciones: ExcepcionDocumento[];
    datos: DatoLeido[];
    dato_inicial: number | null;
    confianza_tabla: FilaConfianza[];
    umbral: number;
};

/** La hoja donde está un dato (el mismo orden que `hojas`), o 0 si no hay. */
function indiceDeHoja(
    datoId: number | null,
    datos: DatoLeido[],
    lecturas: LecturaVisor[],
): number {
    const dato = datos.find((d) => d.id === datoId);

    if (!dato) {
        return 0;
    }

    let antes = 0;

    for (const l of lecturas) {
        // Un archivo sin imágenes (hoja xlsx o csv) ocupa una sola hoja.
        const cuantas = Math.max(l.imagenes.length, 1);

        if (l.id === dato.lectura_id) {
            if (l.imagenes.length === 0) {
                return antes;
            }

            return dato.pagina && dato.pagina <= l.imagenes.length
                ? antes + dato.pagina - 1
                : 0;
        }

        antes += cuantas;
    }

    return 0;
}

/**
 * El visor de un documento leído (F4.6): la imagen de cada hoja al lado de
 * los datos que se leyeron (arriba y abajo en el celular). Al elegir un dato
 * se resalta su recuadro; el preparador revela lo sensible y corrige.
 */
export default function VisorDocumento({
    cliente,
    item,
    archivos,
    lecturas,
    excepciones,
    datos,
    dato_inicial: datoInicial,
    confianza_tabla: tabla,
    umbral,
}: Props) {
    const { t } = useTranslation();
    const [seleccionadoId, setSeleccionadoId] = useState<number | null>(
        () => datoInicial,
    );
    const [indiceHoja, setIndiceHoja] = useState(() =>
        indiceDeHoja(datoInicial, datos, lecturas),
    );
    const [revelados, setRevelados] = useState<Record<number, ValorRevelado>>(
        {},
    );
    const [corrigiendo, setCorrigiendo] = useState<DatoLeido | null>(null);

    // El dato pedido en la URL recibe el foco al montar (ref callback, sin efecto).
    const enfocarInicial = useCallback(
        (seccion: HTMLElement | null) => {
            if (seccion && datoInicial !== null) {
                seccion
                    .querySelector<HTMLButtonElement>(
                        `button[data-dato="${datoInicial}"]`,
                    )
                    ?.focus();
            }
        },
        [datoInicial],
    );

    const hojas = useMemo<Hoja[]>(
        () =>
            lecturas.flatMap((l): Hoja[] => {
                const original = archivos.find((a) => a.id === l.documento_id);
                const archivo = original?.nombre ?? item.nombre;
                const archivoUrl = original?.url ?? null;

                // Una hoja xlsx o csv se lee sin imágenes: una sola "hoja" con
                // el enlace al archivo original en vez de la imagen.
                if (l.imagenes.length === 0) {
                    return [
                        {
                            lecturaId: l.id,
                            pagina: null,
                            url: null,
                            archivo,
                            archivoUrl,
                        },
                    ];
                }

                return l.imagenes.map((url, i) => ({
                    lecturaId: l.id,
                    pagina: i + 1,
                    url,
                    archivo,
                    archivoUrl,
                }));
            }),
        [lecturas, archivos, item.nombre],
    );

    // Con varios archivos, cada dato dice de cuál salió.
    const archivoDe = useMemo(() => {
        if (archivos.length < 2) {
            return null;
        }

        const nombres = new Map(archivos.map((a) => [a.id, a.nombre]));

        return new Map(
            lecturas.map((l) => [
                l.id,
                nombres.get(l.documento_id) ?? item.nombre,
            ]),
        );
    }, [archivos, lecturas, item.nombre]);

    const seleccionado = datos.find((d) => d.id === seleccionadoId) ?? null;
    const revelado = seleccionado ? revelados[seleccionado.id] : undefined;
    const evidencia = seleccionado
        ? seleccionado.sensible
            ? (revelado?.evidencia ?? null)
            : seleccionado.evidencia
        : null;
    const valorVisible = seleccionado
        ? seleccionado.sensible
            ? (revelado?.valor ?? null)
            : seleccionado.valor
        : null;

    const elegir = (id: number) => {
        setSeleccionadoId(id);
        const dato = datos.find((d) => d.id === id);

        if (!dato) {
            return;
        }

        // Su página; en un archivo sin imágenes, la hoja de ese archivo.
        const i = hojas.findIndex(
            (h) =>
                h.lecturaId === dato.lectura_id &&
                (h.pagina === null || h.pagina === dato.pagina),
        );

        if (i !== -1) {
            setIndiceHoja(i);
        }
    };

    // Desde una excepción: elige el dato, lo trae a la vista y le da el foco.
    const irADato = (id: number) => {
        elegir(id);
        requestAnimationFrame(() => {
            const boton = document.querySelector<HTMLButtonElement>(
                `button[data-dato="${id}"]`,
            );

            boton?.scrollIntoView({ block: 'center', behavior: 'smooth' });
            boton?.focus({ preventScroll: true });
        });
    };

    // Solo se dibuja el recuadro si es de la hoja que se está viendo.
    const hojaActual = hojas[indiceHoja];
    const recuadro =
        seleccionado?.recuadro &&
        hojaActual &&
        seleccionado.lectura_id === hojaActual.lecturaId &&
        seleccionado.pagina === hojaActual.pagina
            ? seleccionado.recuadro
            : null;

    const anuncio = seleccionado
        ? [
              t('visor.resaltado', {
                  casilla: seleccionado.casilla,
                  descripcion: seleccionado.descripcion,
              }),
              valorVisible,
              seleccionado.recuadro
                  ? t('visor.enPagina', { pagina: seleccionado.pagina ?? 1 })
                  : t('visor.sinRecuadro'),
          ]
              .filter(Boolean)
              .join('. ')
        : '';

    const sinLectura = lecturas.length === 0;
    const fallo = item.estado === 'no_se_pudo_leer';

    return (
        <>
            <Head title={t('visor.titulo', { nombre: item.nombre })} />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            asChild
                            className="-ml-3"
                        >
                            <Link href={clienteShow(cliente.id).url}>
                                <ArrowLeft aria-hidden="true" />
                                {t('visor.volver', { nombre: cliente.name })}
                            </Link>
                        </Button>
                        <h1 className="text-xl font-semibold">{item.nombre}</h1>
                        <p className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            {item.nombre_oficial ? (
                                <span>{item.nombre_oficial}</span>
                            ) : null}
                            <span>{item.tax_year}</span>
                            <DocumentoEstado
                                estado={item.estado as EstadoDocumento}
                                vista="preparador"
                            />
                        </p>
                    </div>
                    <ConfianzaPanel tabla={tabla} umbral={umbral} />
                </div>

                <ExcepcionesDocumento
                    excepciones={excepciones}
                    datos={datos}
                    onIrADato={irADato}
                />

                {fallo ? (
                    <Alert variant="destructive">
                        <AlertTriangle aria-hidden="true" />
                        <AlertTitle>{t('visor.falloTitulo')}</AlertTitle>
                        <AlertDescription>
                            {item.observacion ?? t('visor.falloDescripcion')}
                        </AlertDescription>
                    </Alert>
                ) : null}

                {sinLectura ? (
                    <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed p-10 text-center">
                        <FileSearch
                            className="size-8 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <h2 className="font-medium">
                            {t('visor.vacioTitulo')}
                        </h2>
                        <p className="max-w-md text-sm text-muted-foreground">
                            {t('visor.vacioDescripcion')}
                        </p>
                        {archivos.length > 0 ? (
                            <div className="flex flex-wrap justify-center gap-1">
                                {archivos.map((a) => (
                                    <Button
                                        key={a.id}
                                        variant="outline"
                                        size="sm"
                                        asChild
                                    >
                                        <a
                                            href={a.url}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            {t('visor.verArchivo', {
                                                nombre: a.nombre,
                                            })}
                                        </a>
                                    </Button>
                                ))}
                            </div>
                        ) : null}
                    </div>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2 lg:items-start">
                        <div className="lg:sticky lg:top-4">
                            <PaginaDocumento
                                hojas={hojas}
                                indice={indiceHoja}
                                onCambiar={setIndiceHoja}
                                recuadro={recuadro}
                            />
                            {seleccionado && !seleccionado.recuadro ? (
                                <p className="mt-3 rounded-md bg-muted p-3 text-sm">
                                    {evidencia
                                        ? t('visor.seLeyo', {
                                              texto: evidencia,
                                          })
                                        : t('visor.sinRecuadroTexto')}
                                </p>
                            ) : null}
                        </div>
                        <section
                            ref={enfocarInicial}
                            aria-labelledby="visor-datos"
                            className="space-y-2"
                        >
                            <h2
                                id="visor-datos"
                                className="text-sm font-medium"
                            >
                                {t('visor.datos', { count: datos.length })}
                            </h2>
                            {datos.length === 0 ? (
                                <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                                    {t('visor.sinDatos')}
                                </p>
                            ) : (
                                <>
                                    <p className="text-xs text-muted-foreground">
                                        {t('visor.ayudaTeclado')}
                                    </p>
                                    <ListaDatos
                                        clienteId={cliente.id}
                                        itemId={item.id}
                                        datos={datos}
                                        archivoDe={archivoDe}
                                        umbral={umbral}
                                        seleccionadoId={seleccionadoId}
                                        revelados={revelados}
                                        onSeleccionar={elegir}
                                        onRevelado={(id, r) =>
                                            setRevelados((prev) => ({
                                                ...prev,
                                                [id]: r,
                                            }))
                                        }
                                        onCorregir={setCorrigiendo}
                                    />
                                </>
                            )}
                        </section>
                    </div>
                )}
                <p className="sr-only" aria-live="polite">
                    {anuncio}
                </p>
            </div>
            {corrigiendo ? (
                <CorregirDatoDialog
                    key={corrigiendo.id}
                    clienteId={cliente.id}
                    itemId={item.id}
                    dato={corrigiendo}
                    valorInicial={
                        corrigiendo.sensible
                            ? (revelados[corrigiendo.id]?.valor ?? '')
                            : (corrigiendo.valor ?? '')
                    }
                    onCerrar={() => setCorrigiendo(null)}
                />
            ) : null}
        </>
    );
}

VisorDocumento.layout = {
    breadcrumbs: [
        { title: 'nav.dashboard', href: dashboard() },
        { title: 'nav.clients', href: clientesIndex() },
    ],
};
