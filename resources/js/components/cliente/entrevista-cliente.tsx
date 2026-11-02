import { useForm } from '@inertiajs/react';
import {
    Check,
    CircleX,
    Eye,
    FileText,
    History,
    MessageSquareWarning,
    Pencil,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { show as confirmPasswordShow } from '@/actions/Laravel/Fortify/Http/Controllers/ConfirmablePasswordController';
import AlertError from '@/components/alert-error';
import { DocumentoEstado } from '@/components/entrevista/documento-estado';
import { useFormatearValor } from '@/components/entrevista/formatear-valor';
import { Pregunta } from '@/components/entrevista/pregunta';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { update as rutaRevisar } from '@/routes/clientes/entrevista/documentos';
import {
    historial as rutaHistorial,
    revelar as rutaRevelar,
    update as rutaCorregir,
} from '@/routes/clientes/entrevista/respuestas';
import type {
    CambioRespuesta,
    DocumentoRevision,
    RespuestaPreparador,
    ResumenDocumentos,
    ValorEntrevista,
    VistaEntrevista,
} from '@/types/entrevista';

const ESTADO_VARIANT: Record<
    VistaEntrevista['estado'],
    'default' | 'secondary' | 'outline'
> = {
    en_curso: 'outline',
    confirmada: 'default',
    reabierta: 'secondary',
};

const QUIEN = 'clienteShow.entrevista.quien';

function useFecha() {
    const { i18n } = useTranslation();

    return (iso: string, conHora = false) =>
        new Intl.DateTimeFormat(i18n.language, {
            dateStyle: 'medium',
            timeStyle: conHora ? 'short' : undefined,
        }).format(new Date(iso));
}

function csrf(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

/** Botón de solo ícono con su nombre en `aria-label` y en un tooltip. */
function AccionIcono({
    etiqueta,
    onClick,
    disabled,
    children,
}: {
    etiqueta: string;
    onClick: () => void;
    disabled?: boolean;
    children: ReactNode;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={etiqueta}
                    disabled={disabled}
                    onClick={onClick}
                >
                    {children}
                </Button>
            </TooltipTrigger>
            <TooltipContent>{etiqueta}</TooltipContent>
        </Tooltip>
    );
}

export function EntrevistaClienteSkeleton() {
    return (
        <Card aria-busy="true">
            <CardHeader>
                <Skeleton className="h-6 w-48" />
                <Skeleton className="h-4 w-72" />
            </CardHeader>
            <CardContent className="space-y-3">
                {[0, 1, 2, 3].map((i) => (
                    <Skeleton key={i} className="h-10 w-full" />
                ))}
            </CardContent>
        </Card>
    );
}

export function EntrevistaClienteError() {
    const { t } = useTranslation();

    return <AlertError errors={[t('clienteShow.entrevista.error')]} />;
}

/**
 * La entrevista del cliente en su ficha (F2.9): estado, consentimiento,
 * respuestas por pantalla con canal y fecha, y los documentos que pidió.
 * Lo sensible se revela una respuesta a la vez, con contraseña.
 */
export function EntrevistaCliente({
    clienteId,
    taxYear,
    entrevista,
}: {
    clienteId: number;
    taxYear: number;
    entrevista: VistaEntrevista | null;
}) {
    const { t } = useTranslation();
    const fecha = useFecha();
    const [corrigiendo, setCorrigiendo] = useState<{
        pregunta: RespuestaPreparador;
        valor: ValorEntrevista;
    } | null>(null);
    const [historial, setHistorial] = useState<Historial | null>(null);
    // Lo revelado vive solo en esta página: al recargar vuelve a ocultarse.
    const [revelados, setRevelados] = useState<Record<number, ValorEntrevista>>(
        {},
    );

    if (entrevista === null) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>
                        {t('clienteShow.entrevista.titulo', { anio: taxYear })}
                    </CardTitle>
                    <CardDescription>
                        {t('clienteShow.entrevista.vacio', { anio: taxYear })}
                    </CardDescription>
                </CardHeader>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader className="gap-2">
                <div className="flex flex-wrap items-center gap-2">
                    <CardTitle>
                        {t('clienteShow.entrevista.titulo', {
                            anio: entrevista.tax_year,
                        })}
                    </CardTitle>
                    <Badge variant={ESTADO_VARIANT[entrevista.estado]}>
                        {t(
                            `clienteShow.entrevista.estado.${entrevista.estado}`,
                        )}
                    </Badge>
                </div>
                <CardDescription className="space-y-1">
                    <span className="block">
                        {t('clienteShow.entrevista.iniciada', {
                            fecha: fecha(entrevista.iniciada_en),
                            canal: t(
                                `clienteShow.entrevista.canal.${entrevista.canal_inicio}`,
                            ),
                        })}
                        {entrevista.confirmada_en
                            ? ` · ${t('clienteShow.entrevista.confirmada', { fecha: fecha(entrevista.confirmada_en) })}`
                            : null}
                    </span>
                    <span className="block">
                        {entrevista.consentimiento
                            ? t('clienteShow.entrevista.consentimiento', {
                                  version: entrevista.consentimiento.version,
                                  fecha: fecha(
                                      entrevista.consentimiento.aceptado_en,
                                  ),
                              }) +
                              (entrevista.consentimiento.provisional
                                  ? ` (${t('clienteShow.entrevista.consentimientoProvisional')})`
                                  : '')
                            : t('clienteShow.entrevista.sinConsentimiento')}
                    </span>
                </CardDescription>
                <div className="flex flex-wrap items-center gap-1.5">
                    <span className="text-xs text-muted-foreground">
                        {t('clienteShow.entrevista.formularios')}:
                    </span>
                    {entrevista.formularios.map((f) => (
                        <Badge
                            key={f}
                            variant="secondary"
                            className="font-mono"
                        >
                            {f}
                        </Badge>
                    ))}
                </div>
            </CardHeader>

            <CardContent className="space-y-6">
                {entrevista.pantallas.map((pantalla) => (
                    <section
                        key={pantalla.numero}
                        aria-labelledby={`entrevista-pantalla-${pantalla.numero}`}
                    >
                        <h3
                            id={`entrevista-pantalla-${pantalla.numero}`}
                            className="flex items-baseline justify-between gap-2 border-b pb-1 text-sm font-semibold text-foreground"
                        >
                            <span>
                                {pantalla.numero}.{' '}
                                {t(
                                    `clienteShow.entrevista.pantallas.${pantalla.numero}`,
                                )}
                            </span>
                            <span className="text-xs font-normal text-muted-foreground">
                                {t('clienteShow.entrevista.avance', {
                                    respondidas: pantalla.respondidas,
                                    total: pantalla.total,
                                })}
                            </span>
                        </h3>
                        <dl className="divide-y">
                            {pantalla.preguntas.map((p) => (
                                <FilaRespuesta
                                    key={`${p.codigo}#${p.persona_id ?? ''}`}
                                    clienteId={clienteId}
                                    pregunta={p}
                                    revelado={
                                        p.respuesta_id !== null &&
                                        p.respuesta_id in revelados
                                            ? revelados[p.respuesta_id]
                                            : undefined
                                    }
                                    onRevelado={(id, valor) =>
                                        setRevelados((r) => ({
                                            ...r,
                                            [id]: valor,
                                        }))
                                    }
                                    onCorregir={(valor) =>
                                        setCorrigiendo({ pregunta: p, valor })
                                    }
                                    onHistorial={() =>
                                        abrirHistorial(
                                            clienteId,
                                            p,
                                            setHistorial,
                                        )
                                    }
                                />
                            ))}
                        </dl>
                    </section>
                ))}

                <Documentos
                    clienteId={clienteId}
                    documentos={entrevista.documentos}
                    resumen={entrevista.documentos_resumen}
                />
            </CardContent>

            {corrigiendo ? (
                <CorregirDialog
                    key={corrigiendo.pregunta.respuesta_id}
                    clienteId={clienteId}
                    pregunta={corrigiendo.pregunta}
                    valorInicial={corrigiendo.valor}
                    conConyuge={entrevista.con_conyuge}
                    onCerrar={() => setCorrigiendo(null)}
                    onGuardado={(id) =>
                        setRevelados((r) => {
                            const copia = { ...r };
                            delete copia[id];

                            return copia;
                        })
                    }
                />
            ) : null}

            <HistorialSheet
                historial={historial}
                onCerrar={() => setHistorial(null)}
            />
        </Card>
    );
}

function FilaRespuesta({
    clienteId,
    pregunta,
    revelado,
    onRevelado,
    onCorregir,
    onHistorial,
}: {
    clienteId: number;
    pregunta: RespuestaPreparador;
    revelado: ValorEntrevista | undefined;
    onRevelado: (respuestaId: number, valor: ValorEntrevista) => void;
    onCorregir: (valor: ValorEntrevista) => void;
    onHistorial: () => void;
}) {
    const { t } = useTranslation();
    const formatear = useFormatearValor(QUIEN);
    const fecha = useFecha();
    const [revelando, setRevelando] = useState(false);
    const [pideContrasena, setPideContrasena] = useState(false);
    const id = pregunta.respuesta_id;
    const oculta =
        pregunta.sensible && pregunta.respondida && revelado === undefined;

    const revelar = async () => {
        if (id === null) {
            return;
        }

        setRevelando(true);

        try {
            const respuesta = await fetch(
                rutaRevelar({ cliente: clienteId, respuesta: id }).url,
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf(),
                    },
                },
            );

            if (respuesta.status === 423) {
                setPideContrasena(true);

                return;
            }

            if (!respuesta.ok) {
                toast.error(t('clienteShow.entrevista.error'));

                return;
            }

            onRevelado(id, (await respuesta.json()).valor);
        } finally {
            setRevelando(false);
        }
    };

    let valor: ReactNode;

    if (!pregunta.respondida) {
        valor = (
            <span className="text-amber-700 dark:text-amber-400">
                {t('clienteShow.entrevista.sinResponder')}
            </span>
        );
    } else if (oculta) {
        valor = (
            <span
                className="font-mono tracking-widest text-muted-foreground"
                aria-label={t('clienteShow.entrevista.sensibleOculta')}
            >
                ••••••
            </span>
        );
    } else {
        valor = formatear({
            ...pregunta,
            valor: revelado !== undefined ? revelado : pregunta.valor,
        });
    }

    return (
        <div className="grid grid-cols-[minmax(0,1fr)_auto] gap-x-2 gap-y-1 py-2.5 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_7.5rem] sm:items-start sm:gap-4">
            <dt className="col-span-2 text-sm text-muted-foreground sm:col-span-1">
                <span className="mr-1.5 font-mono text-xs text-foreground">
                    {pregunta.codigo}
                </span>
                {pregunta.persona_orden !== null
                    ? `${t('clienteShow.entrevista.persona', { orden: pregunta.persona_orden })} · `
                    : null}
                {pregunta.texto}
            </dt>
            <dd className="flex flex-col gap-1">
                <span className="text-sm font-medium break-words text-foreground">
                    {valor}
                </span>
                {pregunta.canal && pregunta.respondida_en ? (
                    <span className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                        <Badge variant="outline" className="font-normal">
                            {t(
                                `clienteShow.entrevista.canal.${pregunta.canal}`,
                            )}
                        </Badge>
                        {fecha(pregunta.respondida_en, true)}
                        {pregunta.cambios > 0
                            ? ` · ${t('clienteShow.entrevista.cambios', { count: pregunta.cambios })}`
                            : null}
                    </span>
                ) : null}
                {pideContrasena ? (
                    <a
                        href={confirmPasswordShow().url}
                        className="text-xs text-amber-700 underline dark:text-amber-400"
                    >
                        {t('clienteShow.entrevista.confirmarContrasena')}
                    </a>
                ) : null}
            </dd>
            {id !== null ? (
                <dd className="flex shrink-0 items-start justify-end gap-0.5">
                    {oculta ? (
                        <AccionIcono
                            etiqueta={t('clienteShow.entrevista.revelar', {
                                codigo: pregunta.codigo,
                            })}
                            onClick={revelar}
                            disabled={revelando}
                        >
                            {revelando ? (
                                <Spinner aria-hidden="true" />
                            ) : (
                                <Eye aria-hidden="true" />
                            )}
                        </AccionIcono>
                    ) : null}
                    <AccionIcono
                        etiqueta={t('clienteShow.entrevista.corregir', {
                            codigo: pregunta.codigo,
                        })}
                        onClick={() =>
                            // Una sensible sin revelar se corrige escribiéndola
                            // de nuevo: el valor actual no se manda al navegador.
                            onCorregir(
                                revelado !== undefined
                                    ? revelado
                                    : pregunta.valor,
                            )
                        }
                    >
                        <Pencil aria-hidden="true" />
                    </AccionIcono>
                    <AccionIcono
                        etiqueta={t('clienteShow.entrevista.historial', {
                            codigo: pregunta.codigo,
                        })}
                        onClick={onHistorial}
                    >
                        <History aria-hidden="true" />
                    </AccionIcono>
                </dd>
            ) : null}
        </div>
    );
}

function CorregirDialog({
    clienteId,
    pregunta,
    valorInicial,
    conConyuge,
    onCerrar,
    onGuardado,
}: {
    clienteId: number;
    pregunta: RespuestaPreparador;
    valorInicial: ValorEntrevista;
    conConyuge: boolean;
    onCerrar: () => void;
    onGuardado: (respuestaId: number) => void;
}) {
    const { t } = useTranslation();
    const form = useForm<{ valor: ValorEntrevista }>({ valor: valorInicial });
    const id = pregunta.respuesta_id as number;

    const guardar = () =>
        form.put(rutaCorregir({ cliente: clienteId, respuesta: id }).url, {
            preserveScroll: true,
            // Solo la entrevista: así no vuelve al esqueleto y no se pierde
            // la posición en la ficha.
            only: ['entrevista'],
            onSuccess: () => {
                toast.success(t('clienteShow.entrevista.corregida'));
                onGuardado(id);
                onCerrar();
            },
        });

    return (
        <Dialog open onOpenChange={(abierto) => !abierto && onCerrar()}>
            <DialogContent className="sm:max-w-lg">
                <DialogTitle>
                    {t('clienteShow.entrevista.corregirTitulo', {
                        codigo: pregunta.codigo,
                    })}
                </DialogTitle>
                <DialogDescription>
                    {t('clienteShow.entrevista.corregirDescripcion')}
                </DialogDescription>
                <form
                    id={`corregir-${id}`}
                    onSubmit={(e) => {
                        e.preventDefault();
                        guardar();
                    }}
                >
                    <Pregunta
                        pregunta={{
                            ...pregunta,
                            casilla: false,
                            // La nota de canal es para el cliente; aquí sobra.
                            canal: null,
                            valor: form.data.valor,
                        }}
                        valor={form.data.valor}
                        conConyuge={conConyuge}
                        error={form.errors.valor}
                        onCambiar={(valor) => form.setData('valor', valor)}
                    />
                </form>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onCerrar}>
                        {t('clienteShow.entrevista.cancelar')}
                    </Button>
                    <Button
                        type="submit"
                        form={`corregir-${id}`}
                        disabled={
                            form.processing ||
                            form.data.valor === null ||
                            form.data.valor === ''
                        }
                    >
                        {form.processing ? (
                            <Spinner aria-hidden="true" />
                        ) : null}
                        {t('clienteShow.entrevista.guardar')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

type Historial = {
    pregunta: RespuestaPreparador;
    cambios: CambioRespuesta[] | 'cargando' | 'error';
};

/** Abre el historial y lo trae; si mientras tanto se abrió otro, no lo pisa. */
async function abrirHistorial(
    clienteId: number,
    pregunta: RespuestaPreparador,
    setHistorial: (cambio: (h: Historial | null) => Historial | null) => void,
) {
    setHistorial(() => ({ pregunta, cambios: 'cargando' }));

    let cambios: Historial['cambios'];

    try {
        const respuesta = await fetch(
            rutaHistorial({
                cliente: clienteId,
                respuesta: pregunta.respuesta_id as number,
            }).url,
            { headers: { Accept: 'application/json' } },
        );

        cambios = respuesta.ok ? (await respuesta.json()).cambios : 'error';
    } catch {
        cambios = 'error';
    }

    setHistorial((h) => (h?.pregunta === pregunta ? { pregunta, cambios } : h));
}

function HistorialSheet({
    historial,
    onCerrar,
}: {
    historial: Historial | null;
    onCerrar: () => void;
}) {
    const { t } = useTranslation();

    return (
        <Sheet open={historial !== null} onOpenChange={(a) => !a && onCerrar()}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-md">
                {historial ? (
                    <>
                        <SheetHeader>
                            <SheetTitle>
                                {t('clienteShow.entrevista.historialTitulo', {
                                    codigo: historial.pregunta.codigo,
                                })}
                            </SheetTitle>
                            <SheetDescription>
                                {historial.pregunta.texto}
                            </SheetDescription>
                        </SheetHeader>
                        <ListaCambios historial={historial} />
                    </>
                ) : null}
            </SheetContent>
        </Sheet>
    );
}

function ListaCambios({ historial }: { historial: Historial }) {
    const { t } = useTranslation();
    const fecha = useFecha();
    const formatear = useFormatearValor(QUIEN);
    const { pregunta, cambios } = historial;

    if (cambios === 'cargando') {
        return (
            <div className="space-y-3 px-4" aria-busy="true">
                {[0, 1, 2].map((i) => (
                    <Skeleton key={i} className="h-20 w-full" />
                ))}
            </div>
        );
    }

    if (cambios === 'error') {
        return (
            <div className="px-4">
                <AlertError
                    errors={[t('clienteShow.entrevista.historialError')]}
                />
            </div>
        );
    }

    if (cambios.length === 0) {
        return (
            <p className="px-4 text-sm text-muted-foreground">
                {t('clienteShow.entrevista.historialVacio')}
            </p>
        );
    }

    return (
        <ol className="space-y-3 px-4 pb-4">
            {cambios.map((c) => (
                <li key={c.id} className="rounded-lg border p-3">
                    <div className="mb-2 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        <Badge variant="outline" className="font-normal">
                            {t(`clienteShow.entrevista.canal.${c.canal}`)}
                        </Badge>
                        <span>{fecha(c.creado_en, true)}</span>
                        {c.actor ? <span>· {c.actor}</span> : null}
                    </div>
                    {c.sensible ? (
                        <p className="text-sm text-muted-foreground">
                            {t('clienteShow.entrevista.valorOculto')}
                        </p>
                    ) : (
                        <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                            <dt className="text-xs text-muted-foreground">
                                {t('clienteShow.entrevista.antes')}
                            </dt>
                            <dd>
                                {c.valor_anterior === null
                                    ? '—'
                                    : formatear({
                                          ...pregunta,
                                          valor: c.valor_anterior,
                                      })}
                            </dd>
                            <dt className="text-xs text-muted-foreground">
                                {t('clienteShow.entrevista.despues')}
                            </dt>
                            <dd className="font-medium">
                                {formatear({
                                    ...pregunta,
                                    valor: c.valor_nuevo,
                                })}
                            </dd>
                        </dl>
                    )}
                </li>
            ))}
        </ol>
    );
}

type AccionRevision = 'validar' | 'observar' | 'rechazar_no_aplica';

/** Acciones que el preparador puede hacer desde cada estado (lo valida el servidor). */
function accionesPermitidas(d: DocumentoRevision): AccionRevision[] {
    const acciones: AccionRevision[] = [];

    if (
        d.archivos.length > 0 &&
        ['subido', 'leido', 'con_observacion'].includes(d.estado)
    ) {
        acciones.push('validar');
    }

    if (['subido', 'leido', 'no_se_pudo_leer', 'validado'].includes(d.estado)) {
        acciones.push('observar');
    }

    if (d.estado === 'no_aplica') {
        acciones.push('rechazar_no_aplica');
    }

    return acciones;
}

function ResumenDocs({ resumen }: { resumen: ResumenDocumentos }) {
    const { t } = useTranslation();

    return (
        <p className="flex flex-wrap items-center gap-x-2 gap-y-1 py-2 text-xs text-muted-foreground">
            {resumen.completo ? (
                <Badge>{t('clienteShow.entrevista.docsCompleto')}</Badge>
            ) : null}
            <span>
                {t('clienteShow.entrevista.docsResumen', {
                    faltan: resumen.faltan,
                    porRevisar: resumen.por_revisar,
                    validados: resumen.validados,
                    noAplica: resumen.no_aplica,
                })}
            </span>
        </p>
    );
}

function Documentos({
    clienteId,
    documentos,
    resumen,
}: {
    clienteId: number;
    documentos: VistaEntrevista['documentos'];
    resumen: ResumenDocumentos;
}) {
    const { t } = useTranslation();
    const [revisando, setRevisando] = useState<{
        documento: DocumentoRevision;
        accion: 'observar' | 'rechazar_no_aplica';
    } | null>(null);

    return (
        <section aria-labelledby="entrevista-documentos">
            <h3
                id="entrevista-documentos"
                className="border-b pb-1 text-sm font-semibold text-foreground"
            >
                {t('clienteShow.entrevista.documentos')}
            </h3>
            {documentos.length === 0 ? (
                <p className="py-2.5 text-sm text-muted-foreground">
                    {t('clienteShow.entrevista.sinDocumentos')}
                </p>
            ) : (
                <>
                    <ResumenDocs resumen={resumen} />
                    <ul className="divide-y">
                        {documentos.map((d) => (
                            <DocumentoFilaPreparador
                                key={d.id}
                                clienteId={clienteId}
                                documento={d}
                                onRevisar={(accion) =>
                                    setRevisando({ documento: d, accion })
                                }
                            />
                        ))}
                    </ul>
                </>
            )}
            {revisando ? (
                <RevisionDialog
                    key={`${revisando.documento.id}-${revisando.accion}`}
                    clienteId={clienteId}
                    documento={revisando.documento}
                    accion={revisando.accion}
                    onCerrar={() => setRevisando(null)}
                />
            ) : null}
        </section>
    );
}

type Traducir = (clave: string, opciones?: Record<string, unknown>) => string;

function nombreDocumento(d: DocumentoRevision, t: Traducir): string {
    return [
        d.persona_orden !== null
            ? t('clienteShow.entrevista.persona', { orden: d.persona_orden })
            : null,
        d.numero !== null ? `${d.nombre} #${d.numero}` : d.nombre,
    ]
        .filter(Boolean)
        .join(' · ');
}

function DocumentoFilaPreparador({
    clienteId,
    documento: d,
    onRevisar,
}: {
    clienteId: number;
    documento: DocumentoRevision;
    onRevisar: (accion: 'observar' | 'rechazar_no_aplica') => void;
}) {
    const { t, i18n } = useTranslation();
    const form = useForm<{ accion: AccionRevision }>({ accion: 'validar' });
    const acciones = accionesPermitidas(d);
    const nombre = nombreDocumento(d, t);

    const validar = () =>
        form.patch(rutaRevisar({ cliente: clienteId, item: d.id }).url, {
            preserveScroll: true,
            // Solo la entrevista: así no vuelve al esqueleto y no se pierde
            // la posición en la ficha.
            only: ['entrevista'],
        });

    const revisadoEn = d.revisado_en
        ? new Intl.DateTimeFormat(i18n.language, {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(d.revisado_en))
        : null;

    return (
        <li className="flex flex-col gap-2 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
            <div className="min-w-0 space-y-1">
                <p className="text-sm text-foreground">{nombre}</p>
                {d.nombre_oficial ? (
                    <p className="text-xs text-muted-foreground">
                        {d.nombre_oficial}
                    </p>
                ) : null}
                {d.archivos.length > 0 ? (
                    <div className="flex flex-wrap gap-1">
                        {d.archivos.map((a) => (
                            <Button
                                key={a.id}
                                variant="ghost"
                                size="sm"
                                asChild
                            >
                                <a
                                    href={a.url}
                                    target="_blank"
                                    rel="noreferrer"
                                    aria-label={t(
                                        'clienteShow.entrevista.verArchivo',
                                        { nombre: a.nombre },
                                    )}
                                >
                                    <FileText aria-hidden="true" />
                                    <span className="max-w-40 truncate">
                                        {a.nombre}
                                    </span>
                                </a>
                            </Button>
                        ))}
                    </div>
                ) : null}
                {d.motivo_no_aplica ? (
                    <p className="text-xs text-muted-foreground">
                        {t('clienteShow.entrevista.motivoNoAplica', {
                            motivo: d.motivo_no_aplica,
                        })}
                    </p>
                ) : null}
                {d.observacion ? (
                    <p className="text-xs text-amber-700 dark:text-amber-400">
                        {t('clienteShow.entrevista.observacionActual', {
                            texto: d.observacion,
                        })}
                    </p>
                ) : null}
                {d.revisado_por && revisadoEn ? (
                    <p className="text-xs text-muted-foreground">
                        {t('clienteShow.entrevista.revisadoPor', {
                            nombre: d.revisado_por,
                            fecha: revisadoEn,
                        })}
                    </p>
                ) : null}
                {form.errors.accion ? (
                    <p role="alert" className="text-xs text-destructive">
                        {form.errors.accion}
                    </p>
                ) : null}
            </div>
            <div className="flex shrink-0 items-center gap-1">
                <DocumentoEstado estado={d.estado} />
                {acciones.includes('validar') ? (
                    <AccionIcono
                        etiqueta={t('clienteShow.entrevista.validarDoc', {
                            nombre,
                        })}
                        onClick={validar}
                        disabled={form.processing}
                    >
                        {form.processing ? (
                            <Spinner aria-hidden="true" />
                        ) : (
                            <Check aria-hidden="true" />
                        )}
                    </AccionIcono>
                ) : null}
                {acciones.includes('observar') ? (
                    <AccionIcono
                        etiqueta={t('clienteShow.entrevista.observarDoc', {
                            nombre,
                        })}
                        onClick={() => onRevisar('observar')}
                        disabled={form.processing}
                    >
                        <MessageSquareWarning aria-hidden="true" />
                    </AccionIcono>
                ) : null}
                {acciones.includes('rechazar_no_aplica') ? (
                    <AccionIcono
                        etiqueta={t('clienteShow.entrevista.rechazarNoAplica', {
                            nombre,
                        })}
                        onClick={() => onRevisar('rechazar_no_aplica')}
                    >
                        <CircleX aria-hidden="true" />
                    </AccionIcono>
                ) : null}
            </div>
        </li>
    );
}

function RevisionDialog({
    clienteId,
    documento,
    accion,
    onCerrar,
}: {
    clienteId: number;
    documento: DocumentoRevision;
    accion: 'observar' | 'rechazar_no_aplica';
    onCerrar: () => void;
}) {
    const { t } = useTranslation();
    const form = useForm<{ accion: AccionRevision; texto: string }>({
        accion,
        texto: '',
    });
    const nombre = nombreDocumento(documento, t);
    const campoId = `revision-${documento.id}-texto`;
    const errorId = `${campoId}-error`;
    const error = form.errors.texto ?? form.errors.accion;
    const clave =
        accion === 'observar'
            ? 'clienteShow.entrevista.observarDialog'
            : 'clienteShow.entrevista.rechazarDialog';

    const enviar = () =>
        form.patch(
            rutaRevisar({ cliente: clienteId, item: documento.id }).url,
            {
                preserveScroll: true,
                // Solo la entrevista: así no vuelve al esqueleto y no se pierde
                // la posición en la ficha.
                only: ['entrevista'],
                onSuccess: onCerrar,
            },
        );

    return (
        <Dialog open onOpenChange={(abierto) => !abierto && onCerrar()}>
            <DialogContent className="sm:max-w-lg">
                <DialogTitle>{t(`${clave}.titulo`, { nombre })}</DialogTitle>
                <DialogDescription>
                    {t(`${clave}.descripcion`)}
                </DialogDescription>
                <form
                    id={`revision-${documento.id}`}
                    className="space-y-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        enviar();
                    }}
                >
                    <Label htmlFor={campoId}>{t(`${clave}.campo`)}</Label>
                    <Textarea
                        id={campoId}
                        value={form.data.texto}
                        maxLength={1000}
                        required
                        rows={4}
                        aria-invalid={error ? true : undefined}
                        aria-describedby={error ? errorId : undefined}
                        onChange={(e) => form.setData('texto', e.target.value)}
                    />
                    {error ? (
                        <p
                            id={errorId}
                            role="alert"
                            className="text-sm text-destructive"
                        >
                            {error}
                        </p>
                    ) : null}
                </form>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onCerrar}>
                        {t('clienteShow.entrevista.cancelar')}
                    </Button>
                    <Button
                        type="submit"
                        form={`revision-${documento.id}`}
                        variant={
                            accion === 'rechazar_no_aplica'
                                ? 'destructive'
                                : 'default'
                        }
                        disabled={form.processing || !form.data.texto.trim()}
                    >
                        {form.processing ? (
                            <Spinner aria-hidden="true" />
                        ) : null}
                        {t(`${clave}.enviar`)}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
