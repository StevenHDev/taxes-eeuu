import { useForm } from '@inertiajs/react';
import { CircleAlert, CircleCheck, FileText, Upload } from 'lucide-react';
import { useId, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/portal/entrevista/documentos';
import type { DocumentoEntrevista } from '@/types/entrevista';

const FORMATOS_MIME: Record<string, string> = {
    pdf: '.pdf,application/pdf',
    jpg: '.jpg,.jpeg,image/jpeg',
    png: '.png,image/png',
    heic: '.heic,image/heic',
};

/**
 * Un documento de la lista (F2.6): nombre sencillo, por qué se pide, estado
 * con texto e ícono, los archivos ya subidos y el botón para subir uno o
 * varios. Se usa en la página de documentos y dentro de los pasos de la
 * entrevista (identidad y cada persona).
 */
export function DocumentoFila({
    documento,
}: {
    documento: DocumentoEntrevista;
}) {
    const { t } = useTranslation();
    const inputRef = useRef<HTMLInputElement>(null);
    const ayudaId = useId();
    const form = useForm<{ archivos: File[]; persona_id: number | null }>({
        archivos: [],
        persona_id: documento.persona_id,
    });

    const recibido = documento.archivos.length > 0;
    const error =
        form.errors.archivos ??
        Object.entries(form.errors).find(([clave]) =>
            clave.startsWith('archivos.'),
        )?.[1];

    const subir = (archivos: FileList | null) => {
        if (!archivos || archivos.length === 0) {
            return;
        }

        form.transform((d) => ({ ...d, archivos: Array.from(archivos) }));
        form.post(store({ codigo: documento.codigo }).url, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                if (inputRef.current) {
                    inputRef.current.value = '';
                }
            },
        });
    };

    const nombre =
        documento.persona_orden !== null
            ? `${documento.nombre} · ${t('entrevista.personas.nombre', { orden: documento.persona_orden })}`
            : documento.nombre;

    return (
        <li className="space-y-3 rounded-lg border border-border bg-card p-4">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0 space-y-1">
                    <p className="text-sm font-medium text-foreground">
                        {nombre}
                    </p>
                    <p id={ayudaId} className="text-xs text-muted-foreground">
                        {documento.siempre
                            ? t('entrevista.documentos.siempre')
                            : documento.por_que.length > 0
                              ? t('entrevista.documentos.porQue', {
                                    pregunta: documento.por_que[0],
                                })
                              : null}
                    </p>
                </div>
                <Badge
                    variant={recibido ? 'secondary' : 'outline'}
                    className="shrink-0"
                >
                    {recibido ? (
                        <CircleCheck aria-hidden="true" />
                    ) : (
                        <CircleAlert aria-hidden="true" />
                    )}
                    {recibido
                        ? t('entrevista.documentos.recibido', {
                              count: documento.archivos.length,
                          })
                        : t('entrevista.documentos.falta')}
                </Badge>
            </div>

            {recibido ? (
                <ul className="space-y-1">
                    {documento.archivos.map((a) => (
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

            <div className="space-y-2">
                <input
                    ref={inputRef}
                    type="file"
                    multiple
                    className="sr-only"
                    tabIndex={-1}
                    aria-hidden="true"
                    accept={documento.formatos
                        .map((f) => FORMATOS_MIME[f] ?? `.${f}`)
                        .join(',')}
                    onChange={(e) => subir(e.target.files)}
                />
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={form.processing}
                    aria-describedby={ayudaId}
                    aria-label={`${recibido ? t('entrevista.documentos.agregarOtro') : t('entrevista.documentos.subir')}: ${nombre}`}
                    onClick={() => inputRef.current?.click()}
                >
                    {form.processing ? (
                        <Spinner aria-hidden="true" />
                    ) : (
                        <Upload aria-hidden="true" />
                    )}
                    {form.processing
                        ? form.progress
                            ? t('entrevista.documentos.subiendoPorcentaje', {
                                  porcentaje: form.progress.percentage ?? 0,
                              })
                            : t('entrevista.documentos.subiendo')
                        : recibido
                          ? t('entrevista.documentos.agregarOtro')
                          : t('entrevista.documentos.subir')}
                </Button>
                <p className="text-xs text-muted-foreground">
                    {t('entrevista.documentos.formatos')}
                </p>
                <InputError message={error as string | undefined} />
            </div>
        </li>
    );
}
