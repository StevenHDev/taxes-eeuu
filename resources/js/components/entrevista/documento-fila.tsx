import { useForm } from '@inertiajs/react';
import { Download, FileText, MessageSquareWarning, Plus } from 'lucide-react';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { DocumentoEstado } from '@/components/entrevista/documento-estado';
import { DocumentoNoAplica } from '@/components/entrevista/documento-no-aplica';
import { DocumentoNota } from '@/components/entrevista/documento-nota';
import { DocumentoSubir } from '@/components/entrevista/documento-subir';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { agregar } from '@/routes/portal/entrevista/documentos/otro';
import type { DocumentoEntrevista } from '@/types/entrevista';

/**
 * Una fecha ISO (AAAA-MM-DD) escrita en el idioma del cliente, sin que la
 * zona horaria la corra de día.
 */
function fechaLarga(iso: string, idioma: string): string {
    return new Intl.DateTimeFormat(idioma, {
        dateStyle: 'long',
        timeZone: 'UTC',
    }).format(new Date(`${iso}T00:00:00Z`));
}

function Dato({ titulo, texto }: { titulo: string; texto: string }) {
    return (
        <div className="space-y-0.5">
            <dt className="text-xs font-medium text-muted-foreground">
                {titulo}
            </dt>
            <dd className="text-sm text-foreground">{texto}</dd>
        </div>
    );
}

/**
 * Un documento de la lista (F3.3): nombre sencillo y oficial, persona o
 * número, por qué se pide, quién lo envía, cuándo llega, qué hacer si no
 * llega, formatos, archivos y estado, con las acciones del cliente. Se usa en
 * la página de documentos y dentro de los pasos de la entrevista.
 */
export function DocumentoFila({
    documento,
}: {
    documento: DocumentoEntrevista;
}) {
    const { t, i18n } = useTranslation();
    const ayudaId = useId();
    const otro = useForm({ agregar: '' });

    const {
        estado,
        archivos,
        observacion,
        motivo_no_aplica: motivoNoAplica,
    } = documento;
    const recibido = archivos.length > 0;
    const devuelto =
        estado === 'con_observacion' || estado === 'no_aplica_rechazado';
    const cerradoSinArchivos = estado === 'no_aplica';

    const titulo = [
        documento.nombre,
        documento.numero !== null ? `#${documento.numero}` : null,
        documento.persona_orden !== null
            ? `· ${t('entrevista.personas.nombre', { orden: documento.persona_orden })}`
            : null,
    ]
        .filter(Boolean)
        .join(' ');

    const porQue = documento.siempre
        ? t('entrevista.documentos.siempre')
        : documento.por_que.length > 0
          ? t('entrevista.documentos.porQue', {
                pregunta: documento.por_que[0],
            })
          : null;

    const accionSubir = devuelto
        ? t('entrevista.documentos.subirDeNuevo')
        : documento.archivos.length > 0
          ? t('entrevista.documentos.agregarArchivo')
          : t('entrevista.documentos.subir');

    const agregarOtro = () => {
        otro.post(agregar(documento.id).url, { preserveScroll: true });
    };

    return (
        <li
            className={`space-y-3 rounded-lg border bg-card p-4 ${devuelto ? 'border-destructive/60' : 'border-border'}`}
        >
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0 space-y-0.5">
                    <p className="text-sm font-medium text-foreground">
                        {titulo}
                    </p>
                    {documento.nombre_oficial ? (
                        <p className="text-xs text-muted-foreground">
                            {documento.nombre_oficial}
                        </p>
                    ) : null}
                </div>
                <DocumentoEstado estado={estado} />
            </div>

            {devuelto && observacion ? (
                <div
                    role="note"
                    className="flex gap-2 rounded-md border border-destructive/40 bg-destructive/5 p-3 text-sm text-foreground"
                >
                    <MessageSquareWarning
                        className="mt-0.5 size-4 shrink-0 text-destructive"
                        aria-hidden="true"
                    />
                    <div className="space-y-0.5">
                        <p className="font-medium">
                            {estado === 'con_observacion'
                                ? t('entrevista.documentos.observacion')
                                : t('entrevista.documentos.noAplicaRechazado')}
                        </p>
                        <p className="whitespace-pre-line">{observacion}</p>
                    </div>
                </div>
            ) : null}

            {cerradoSinArchivos && motivoNoAplica ? (
                <p className="text-sm text-muted-foreground">
                    {t('entrevista.documentos.tuMotivo', {
                        motivo: motivoNoAplica,
                    })}
                </p>
            ) : null}

            <dl id={ayudaId} className="grid gap-2 sm:grid-cols-2">
                {porQue ? (
                    <div className="sm:col-span-2">
                        <Dato
                            titulo={t('entrevista.documentos.porQueTitulo')}
                            texto={porQue}
                        />
                    </div>
                ) : null}
                {documento.emisor ? (
                    <Dato
                        titulo={t('entrevista.documentos.quienLoEnvia')}
                        texto={documento.emisor}
                    />
                ) : null}
                {documento.fecha_llegada ? (
                    <Dato
                        titulo={t('entrevista.documentos.cuandoLlega')}
                        texto={fechaLarga(
                            documento.fecha_llegada,
                            i18n.language,
                        )}
                    />
                ) : null}
                {/* En los registros propios nadie los envía: "Qué subir" lo reemplaza. */}
                {documento.si_no_llega && !documento.que_subir ? (
                    <div className="sm:col-span-2">
                        <Dato
                            titulo={t('entrevista.documentos.siNoLlega')}
                            texto={documento.si_no_llega}
                        />
                    </div>
                ) : null}
            </dl>

            {documento.que_subir ? (
                <div className="space-y-4 rounded-md border border-border bg-muted/40 p-3">
                    <Dato
                        titulo={t('entrevista.documentos.queSubir')}
                        texto={documento.que_subir}
                    />
                    <DocumentoNota
                        id={documento.id}
                        nombre={titulo}
                        nota={documento.nota_cliente}
                    />
                    {documento.plantillas ? (
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                            <span className="text-muted-foreground">
                                {t('entrevista.documentos.plantilla')}
                            </span>
                            {(['xlsx', 'pdf'] as const).map((formato) => (
                                <Button
                                    key={formato}
                                    variant="link"
                                    size="sm"
                                    asChild
                                >
                                    <a
                                        href={documento.plantillas?.[formato]}
                                        download
                                        aria-label={t(
                                            'entrevista.documentos.descargarPlantilla',
                                            { formato: formato.toUpperCase() },
                                        )}
                                    >
                                        <Download aria-hidden="true" />
                                        {formato === 'xlsx' ? 'Excel' : 'PDF'}
                                    </a>
                                </Button>
                            ))}
                        </div>
                    ) : null}
                </div>
            ) : null}

            {recibido ? (
                <ul
                    className="space-y-1"
                    aria-label={t('entrevista.documentos.archivosDe', {
                        nombre: titulo,
                    })}
                >
                    {archivos.map((a) => (
                        <li
                            key={a.id}
                            className="flex items-center gap-2 text-sm"
                        >
                            <FileText
                                className="size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <a
                                href={a.url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="truncate underline-offset-4 hover:underline focus-visible:underline"
                            >
                                {a.nombre}
                            </a>
                        </li>
                    ))}
                </ul>
            ) : null}

            <div className="flex flex-wrap items-center gap-2">
                <DocumentoSubir
                    id={documento.id}
                    nombre={titulo}
                    formatos={documento.formatos}
                    etiqueta={accionSubir}
                    descripcionId={ayudaId}
                />
                {estado === 'pendiente' || estado === 'con_observacion' ? (
                    <DocumentoNoAplica id={documento.id} nombre={titulo} />
                ) : null}
                {documento.puede_agregar_otro ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        disabled={otro.processing}
                        aria-label={`${t('entrevista.documentos.agregarOtroDe')}: ${titulo}`}
                        onClick={agregarOtro}
                    >
                        {otro.processing ? (
                            <Spinner aria-hidden="true" />
                        ) : (
                            <Plus aria-hidden="true" />
                        )}
                        {t('entrevista.documentos.agregarOtroDe')}
                    </Button>
                ) : null}
            </div>
            <InputError message={otro.errors.agregar} />

            <p className="text-xs text-muted-foreground">
                {t('entrevista.documentos.formatos')}
            </p>
        </li>
    );
}
