import { Link, useForm } from '@inertiajs/react';
import {
    Check,
    CircleX,
    Eye,
    FileText,
    MessageSquareWarning,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DocumentoEstado } from '@/components/entrevista/documento-estado';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { visor as rutaVisor } from '@/routes/clientes/documentos';
import { update as rutaRevisar } from '@/routes/clientes/entrevista/documentos';
import type {
    DocumentoRevision,
    ResumenDocumentos,
    VistaEntrevista,
} from '@/types/entrevista';
import { AccionIcono } from './utilidades';

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

/** El conteo de excepciones abiertas, con enlace a la cola filtrada (F4.7b). */
function ExcepcionesAbiertasFila({
    clienteId,
    excepciones,
}: {
    clienteId: number;
    excepciones: { total: number; bloqueantes: number };
}) {
    const { t } = useTranslation();

    if (excepciones.total === 0) {
        return null;
    }

    return (
        <Link
            href={`/excepciones?cliente=${clienteId}`}
            className={cn(
                'flex flex-wrap items-center gap-x-2 gap-y-1 rounded-md border px-3 py-2 text-sm transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                excepciones.bloqueantes > 0
                    ? 'border-amber-300 bg-amber-50 text-amber-900 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200 dark:hover:bg-amber-950/70'
                    : 'hover:bg-accent',
            )}
        >
            <span className="font-medium">
                {t('excepciones.ficha.abiertas', { count: excepciones.total })}
            </span>
            {excepciones.bloqueantes > 0 ? (
                <span>
                    {t('excepciones.ficha.bloqueantes', {
                        count: excepciones.bloqueantes,
                    })}
                </span>
            ) : null}
            <span className="ml-auto underline underline-offset-4">
                {t('excepciones.ficha.ver')}
            </span>
        </Link>
    );
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
            // Solo la entrevista y el encabezado (sus conteos cambian): así no
            // vuelve al esqueleto y no se pierde la posición en la ficha.
            only: ['entrevista', 'ficha'],
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
                {d.nota_cliente ? (
                    <p className="text-xs whitespace-pre-line text-muted-foreground">
                        {t('clienteShow.entrevista.notaCliente', {
                            texto: d.nota_cliente,
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
                <DocumentoEstado estado={d.estado} vista="preparador" />
                {['leido', 'no_se_pudo_leer', 'validado'].includes(d.estado) ? (
                    <Button variant="outline" size="sm" asChild>
                        <Link
                            href={rutaVisor({ cliente: clienteId, item: d.id })}
                            aria-label={t('visor.verDatosAria', { nombre })}
                        >
                            <Eye aria-hidden="true" />
                            {t('visor.verDatos')}
                        </Link>
                    </Button>
                ) : null}
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
                // Solo la entrevista y el encabezado (sus conteos cambian): así
                // no vuelve al esqueleto y no se pierde la posición.
                only: ['entrevista', 'ficha'],
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

/**
 * Los documentos que pidió la entrevista en la ficha: resumen, excepciones
 * abiertas con su enlace a la cola y la lista con revisión y "Ver datos".
 */
export function DocumentosCliente({
    clienteId,
    entrevista,
}: {
    clienteId: number;
    entrevista: VistaEntrevista | null;
}) {
    const { t } = useTranslation();

    if (entrevista === null) {
        return (
            <p className="rounded-lg border border-dashed px-4 py-10 text-center text-sm text-muted-foreground">
                {t('ficha.documentos.sinEntrevista')}
            </p>
        );
    }

    return (
        <div className="space-y-4">
            <ExcepcionesAbiertasFila
                clienteId={clienteId}
                excepciones={entrevista.excepciones_abiertas}
            />
            <Documentos
                clienteId={clienteId}
                documentos={entrevista.documentos}
                resumen={entrevista.documentos_resumen}
            />
        </div>
    );
}
