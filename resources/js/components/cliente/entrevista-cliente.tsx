import { useForm } from '@inertiajs/react';
import { Eye, History, Pencil, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { show as confirmPasswordShow } from '@/actions/Laravel/Fortify/Http/Controllers/ConfirmablePasswordController';
import AlertError from '@/components/alert-error';
import { useFormatearValor } from '@/components/entrevista/formatear-valor';
import { Pregunta } from '@/components/entrevista/pregunta';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import {
    historial as rutaHistorial,
    revelar as rutaRevelar,
    update as rutaCorregir,
} from '@/routes/clientes/entrevista/respuestas';
import type {
    CambioRespuesta,
    RespuestaPreparador,
    ValorEntrevista,
    VistaEntrevista,
} from '@/types/entrevista';
import { AccionIcono } from './utilidades';

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
                {entrevista.avisos.length > 0 ? (
                    <Alert>
                        <TriangleAlert aria-hidden="true" />
                        <AlertTitle>
                            {t('clienteShow.entrevista.avisos.titulo')}
                        </AlertTitle>
                        <AlertDescription>
                            <ul className="space-y-1">
                                {entrevista.avisos.map((aviso) => (
                                    <li key={aviso.tipo}>
                                        <span className="font-medium text-foreground">
                                            {aviso.detalle
                                                ? t(
                                                      'clienteShow.entrevista.avisos.aniosSinDeclararDetalle',
                                                      { anios: aviso.detalle },
                                                  )
                                                : t(
                                                      'clienteShow.entrevista.avisos.aniosSinDeclarar',
                                                  )}
                                        </span>
                                        .{' '}
                                        {t(
                                            'clienteShow.entrevista.avisos.aniosSinDeclararAyuda',
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                ) : null}
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
            // Solo la entrevista y el encabezado (sus conteos cambian): así no
            // vuelve al esqueleto y no se pierde la posición en la ficha.
            only: ['entrevista', 'ficha'],
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
