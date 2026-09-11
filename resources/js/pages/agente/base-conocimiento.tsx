import { Head, router, useForm } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';
import {
    destroy as destroyDocumento,
    index as baseConocimientoIndex,
    store as storeDocumento,
} from '@/routes/agente/base-conocimiento';
import type { BaseConocimientoDocumento } from '@/types';

const MAX_UPLOAD_MB = 20;
const MAX_UPLOAD_BYTES = MAX_UPLOAD_MB * 1024 * 1024;

function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function UploadForm() {
    const { t } = useTranslation();
    const inputId = useId();
    const [error, setError] = useState<string | null>(null);
    const form = useForm<{ file: File | null }>({ file: null });

    const seleccionar = (file: File | null) => {
        setError(null);

        if (!file) {
            form.setData('file', null);

            return;
        }

        if (file.size > MAX_UPLOAD_BYTES) {
            setError(
                t('agente.baseConocimiento.upload.errorSize', {
                    max: MAX_UPLOAD_MB,
                }),
            );
            form.setData('file', null);

            return;
        }

        if (!file.name.toLowerCase().endsWith('.pdf')) {
            setError(t('agente.baseConocimiento.upload.errorFormat'));
            form.setData('file', null);

            return;
        }

        form.setData('file', file);
    };

    const submit = () => {
        if (!form.data.file) {
            return;
        }

        form.post(storeDocumento().url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const subiendo = form.progress !== null && form.progress !== undefined;

    return (
        <div className="grid gap-3">
            <label
                htmlFor={inputId}
                className="flex cursor-pointer flex-col items-center gap-2 rounded-lg border-2 border-dashed border-input bg-secondary/30 px-4 py-6 text-center transition-colors hover:bg-secondary/60"
            >
                <Upload className="size-6 text-muted-foreground" />
                <span className="text-sm font-medium">
                    {form.data.file
                        ? form.data.file.name
                        : t('agente.baseConocimiento.upload.selectFile')}
                </span>
                <span className="text-xs text-muted-foreground">
                    {form.data.file
                        ? formatBytes(form.data.file.size)
                        : t('agente.baseConocimiento.upload.hint', {
                              max: MAX_UPLOAD_MB,
                          })}
                </span>
                <input
                    id={inputId}
                    type="file"
                    accept=".pdf"
                    className="sr-only"
                    onChange={(e) => seleccionar(e.target.files?.[0] ?? null)}
                />
            </label>

            {(error || form.errors.file) && (
                <p className="text-sm text-destructive">
                    {error ?? form.errors.file}
                </p>
            )}

            {subiendo && (
                <div
                    className="h-2 w-full overflow-hidden rounded-full bg-secondary"
                    role="progressbar"
                    aria-valuenow={form.progress?.percentage ?? 0}
                    aria-valuemin={0}
                    aria-valuemax={100}
                >
                    <div
                        className="h-full bg-primary transition-all"
                        style={{ width: `${form.progress?.percentage ?? 0}%` }}
                    />
                </div>
            )}

            <Button
                onClick={submit}
                disabled={!form.data.file || form.processing}
                className="w-fit"
            >
                {form.processing
                    ? t('agente.baseConocimiento.upload.uploading')
                    : t('agente.baseConocimiento.upload.submit')}
            </Button>
        </div>
    );
}

export default function AgenteBaseConocimiento({
    documentos,
}: {
    documentos: BaseConocimientoDocumento[];
}) {
    const { t, i18n } = useTranslation();
    const [aEliminar, setAEliminar] =
        useState<BaseConocimientoDocumento | null>(null);
    const [eliminando, setEliminando] = useState(false);

    const formatDate = (iso: string) =>
        new Intl.DateTimeFormat(i18n.language, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(iso));

    const confirmarEliminar = () => {
        if (!aEliminar) {
            return;
        }

        setEliminando(true);

        router.delete(destroyDocumento(aEliminar.id).url, {
            preserveScroll: true,
            onFinish: () => {
                setEliminando(false);
                setAEliminar(null);
            },
        });
    };

    return (
        <>
            <Head title={t('agente.baseConocimiento.title')} />

            <div className="flex flex-col gap-1">
                <h1 className="text-xl font-semibold">
                    {t('agente.baseConocimiento.title')}
                </h1>
                <p className="text-sm text-muted-foreground">
                    {t('agente.baseConocimiento.subtitle')}
                </p>
            </div>

            <UploadForm />

            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>
                            {t('agente.baseConocimiento.columns.name')}
                        </TableHead>
                        <TableHead>
                            {t('agente.baseConocimiento.columns.size')}
                        </TableHead>
                        <TableHead>
                            {t('agente.baseConocimiento.columns.status')}
                        </TableHead>
                        <TableHead>
                            {t('agente.baseConocimiento.columns.uploadedBy')}
                        </TableHead>
                        <TableHead>
                            {t('agente.baseConocimiento.columns.date')}
                        </TableHead>
                        <TableHead className="w-24" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {documentos.length === 0 && (
                        <TableRow>
                            <TableCell
                                colSpan={6}
                                className="text-center text-muted-foreground"
                            >
                                {t('agente.baseConocimiento.empty')}
                            </TableCell>
                        </TableRow>
                    )}

                    {documentos.map((doc) => (
                        <TableRow key={doc.id}>
                            <TableCell className="font-medium">
                                {doc.nombre_original}
                            </TableCell>
                            <TableCell className="text-sm text-muted-foreground">
                                {formatBytes(doc.tamano)}
                            </TableCell>
                            <TableCell>
                                {doc.estado === 'procesado' ? (
                                    <Badge variant="secondary">
                                        {t(
                                            'agente.baseConocimiento.estado.procesado',
                                        )}
                                    </Badge>
                                ) : (
                                    <Badge
                                        variant="destructive"
                                        title={doc.error_mensaje ?? undefined}
                                    >
                                        {t(
                                            'agente.baseConocimiento.estado.error',
                                        )}
                                    </Badge>
                                )}
                            </TableCell>
                            <TableCell className="text-sm text-muted-foreground">
                                {doc.subido_por ?? '—'}
                            </TableCell>
                            <TableCell className="text-sm text-muted-foreground">
                                {formatDate(doc.created_at)}
                            </TableCell>
                            <TableCell>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setAEliminar(doc)}
                                >
                                    {t('common.delete')}
                                </Button>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>

            <Dialog
                open={aEliminar !== null}
                onOpenChange={(open) => !open && setAEliminar(null)}
            >
                <DialogContent>
                    <DialogTitle>
                        {t('agente.baseConocimiento.confirmarEliminar.title')}
                    </DialogTitle>
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'agente.baseConocimiento.confirmarEliminar.description',
                            { nombre: aEliminar?.nombre_original },
                        )}
                    </p>
                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setAEliminar(null)}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={confirmarEliminar}
                            disabled={eliminando}
                        >
                            {t(
                                'agente.baseConocimiento.confirmarEliminar.confirm',
                            )}
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

AgenteBaseConocimiento.layout = {
    breadcrumbs: [
        { title: 'nav.dashboard', href: dashboard() },
        {
            title: 'agente.nav.baseConocimiento',
            href: baseConocimientoIndex(),
        },
    ],
};
