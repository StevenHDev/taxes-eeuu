import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { show as confirmPasswordShow } from '@/actions/Laravel/Fortify/Http/Controllers/ConfirmablePasswordController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';
import {
    index as clientesIndex,
    destroy as clienteDestroy,
    exportMethod as clienteExport,
    marcarRevisado,
} from '@/routes/clientes';
import {
    destroy as campoDestroy,
    historial as campoHistorial,
    reveal as campoReveal,
    update as campoUpdate,
} from '@/routes/clientes/campos';
import type {
    CampoCliente,
    CampoDocumento,
    CatalogoDisponibleItem,
    ClienteForma,
    HistorialCambio,
} from '@/types';

type PageProps = {
    auth: { user: { role: 'client' | 'preparer' | 'administrator' } };
};

const ESTADO_VARIANT: Record<
    CampoCliente['estado'],
    'outline' | 'secondary' | 'default' | 'destructive'
> = {
    pendiente: 'outline',
    recibido: 'default',
    invalido: 'destructive',
};

function guessTipoDato(valor: unknown): string {
    if (typeof valor === 'number') {
        return 'number';
    }

    if (Array.isArray(valor)) {
        return valor.every((v) => typeof v === 'string')
            ? 'array_string'
            : 'array_object';
    }

    if (valor !== null && typeof valor === 'object') {
        return 'object';
    }

    return 'string';
}

type Json = string | number | boolean | null | Json[] | { [key: string]: Json };

function parseContenido(raw: string): Json {
    try {
        return JSON.parse(raw) as Json;
    } catch {
        return raw;
    }
}

const MAX_UPLOAD_MB = 10;
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

// Formulario de subida de archivo reutilizable (cargar/reemplazar documento y
// agregar campo tipo archivo). Usa useForm de Inertia para:
//  - method spoofing (POST + _method=patch): sin esto PHP no parsea el archivo
//    en un PATCH multipart y la subida "no hace nada".
//  - barra de progreso (form.progress.percentage).
// Valida en el cliente el peso (<=10MB) y el formato antes de subir.
function DocumentoUploadForm({
    url,
    formatos,
    submitLabel,
    onDone,
}: {
    url: string;
    formatos: string[] | null;
    submitLabel: string;
    onDone: () => void;
}) {
    const { t } = useTranslation();
    const inputId = useId();
    const [error, setError] = useState<string | null>(null);
    const form = useForm<{
        _method: string;
        modo: string;
        file: File | null;
    }>({
        _method: 'patch',
        modo: 'archivo',
        file: null,
    });

    const validar = (file: File): string | null => {
        if (file.size > MAX_UPLOAD_BYTES) {
            return t('clienteShow.upload.errorSize', { max: MAX_UPLOAD_MB });
        }

        const extension = file.name.split('.').pop()?.toLowerCase() ?? '';

        if (formatos && formatos.length > 0 && !formatos.includes(extension)) {
            return t('clienteShow.upload.errorFormat', {
                formats: formatos.join(', '),
            });
        }

        return null;
    };

    const seleccionar = (file: File | null) => {
        setError(null);

        if (!file) {
            form.setData('file', null);

            return;
        }

        const problema = validar(file);

        if (problema) {
            setError(problema);
            form.setData('file', null);

            return;
        }

        form.setData('file', file);
    };

    const submit = () => {
        if (!form.data.file) {
            return;
        }

        form.post(url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onDone();
            },
        });
    };

    const accept =
        formatos && formatos.length > 0
            ? formatos.map((f) => `.${f}`).join(',')
            : undefined;
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
                        : t('clienteShow.upload.selectFile')}
                </span>
                <span className="text-xs text-muted-foreground">
                    {form.data.file
                        ? formatBytes(form.data.file.size)
                        : t('clienteShow.upload.hint', {
                              max: MAX_UPLOAD_MB,
                              formats:
                                  formatos && formatos.length > 0
                                      ? formatos.join(', ').toUpperCase()
                                      : t('clienteShow.upload.anyFormat'),
                          })}
                </span>
                <input
                    id={inputId}
                    type="file"
                    accept={accept}
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

            <DialogFooter>
                <Button
                    onClick={submit}
                    disabled={!form.data.file || form.processing}
                >
                    {form.processing
                        ? t('clienteShow.upload.uploading')
                        : submitLabel}
                </Button>
            </DialogFooter>
        </div>
    );
}

// Visor de documentos a pantalla completa (100% ancho/alto) con botón de descarga.
// El archivo se sirve inline vía URL firmada temporal (preview_url); los PDFs y
// formatos que el navegador entiende se renderizan en el iframe, las imágenes con <img>.
function DocumentoViewerDialog({ documento }: { documento: CampoDocumento }) {
    const { t } = useTranslation();
    const esImagen = documento.file_mime_type?.startsWith('image/');

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    variant="link"
                    className="h-auto max-w-full justify-start truncate p-0 underline"
                    title={t('clienteShow.viewer.viewTitle', {
                        name: documento.file_original_name,
                    })}
                >
                    {documento.file_original_name}
                </Button>
            </DialogTrigger>
            <DialogContent className="flex h-screen w-screen max-w-none flex-col gap-0 rounded-none border-0 p-0 sm:max-w-none">
                <DialogTitle className="sr-only">
                    {documento.file_original_name}
                </DialogTitle>
                <div className="flex items-center justify-between gap-4 border-b bg-background px-4 py-3">
                    <span className="truncate text-sm font-medium">
                        {documento.file_original_name}
                    </span>
                    <div className="flex items-center gap-2 pr-10">
                        {documento.download_url && (
                            <a
                                href={documento.download_url}
                                download={documento.file_original_name}
                            >
                                <Button size="sm">
                                    {t('common.download')}
                                </Button>
                            </a>
                        )}
                    </div>
                </div>
                <div className="min-h-0 flex-1 bg-muted">
                    {documento.preview_url ? (
                        esImagen ? (
                            <div className="flex h-full w-full items-center justify-center overflow-auto p-4">
                                <img
                                    src={documento.preview_url}
                                    alt={documento.file_original_name}
                                    className="max-h-full max-w-full object-contain"
                                />
                            </div>
                        ) : (
                            <iframe
                                src={documento.preview_url}
                                title={documento.file_original_name}
                                className="h-full w-full border-0"
                            />
                        )
                    ) : (
                        <div className="flex h-full items-center justify-center p-6 text-center text-sm text-muted-foreground">
                            {t('clienteShow.viewer.noPreview')}
                            {documento.download_url && (
                                <>
                                    {' '}
                                    <a
                                        href={documento.download_url}
                                        className="underline"
                                    >
                                        {t('clienteShow.viewer.downloadFile')}
                                    </a>
                                    .
                                </>
                            )}
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}

// Carga (o reemplazo) de un documento directamente desde el dashboard. Reutiliza
// el mismo endpoint de corrección manual en modo "archivo".
function SubirDocumentoDialog({
    clienteId,
    campo,
}: {
    clienteId: number;
    campo: CampoCliente;
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const yaHayArchivo = campo.documento !== null;
    const url =
        campoUpdate({ cliente: clienteId, campo: campo.campo }).url +
        `?forma=${campo.forma}`;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm">
                    {yaHayArchivo ? t('common.replace') : t('common.upload')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    {yaHayArchivo
                        ? t('clienteShow.upload.replaceTitle', {
                              campo: campo.campo,
                          })
                        : t('clienteShow.upload.uploadTitle', {
                              campo: campo.campo,
                          })}
                </DialogTitle>
                <DialogDescription>
                    {t('clienteShow.upload.description', {
                        forma: campo.forma,
                    })}
                </DialogDescription>

                <DocumentoUploadForm
                    url={url}
                    formatos={campo.formatos_aceptados}
                    submitLabel={
                        yaHayArchivo ? t('common.replace') : t('common.upload')
                    }
                    onDone={() => setOpen(false)}
                />
            </DialogContent>
        </Dialog>
    );
}

function EditCampoDialog({
    clienteId,
    campo,
}: {
    clienteId: number;
    campo: CampoCliente;
}) {
    const { t } = useTranslation();
    const [raw, setRaw] = useState(
        campo.valor !== null && campo.valor !== undefined
            ? JSON.stringify(campo.valor)
            : '',
    );

    // Edición del valor de texto. Los archivos se cargan/reemplazan con
    // SubirDocumentoDialog (subida multipart con progreso y validación).
    const submit = () => {
        const contenido = parseContenido(raw);

        router.patch(
            campoUpdate({ cliente: clienteId, campo: campo.campo }).url +
                `?forma=${campo.forma}`,
            {
                modo: 'texto',
                tipo_dato: guessTipoDato(contenido),
                contenido,
            },
            { preserveScroll: true },
        );
    };

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm">
                    {t('clienteShow.edit.trigger')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    {t('clienteShow.edit.title', { campo: campo.campo })}
                </DialogTitle>
                <DialogDescription>
                    {t('clienteShow.edit.description', { forma: campo.forma })}
                </DialogDescription>

                <Textarea
                    value={raw}
                    onChange={(e) => setRaw(e.target.value)}
                    placeholder={t('clienteShow.edit.placeholder')}
                    rows={4}
                />

                <DialogFooter>
                    <Button onClick={submit}>
                        {t('clienteShow.edit.save')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function AgregarCampoDialog({
    clienteId,
    disponibles,
}: {
    clienteId: number;
    disponibles: CatalogoDisponibleItem[];
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [forma, setForma] = useState<string>(disponibles[0]?.forma ?? '');
    const camposDeForma = disponibles.filter((d) => d.forma === forma);
    const [campo, setCampo] = useState(camposDeForma[0]?.campo ?? '');
    const [raw, setRaw] = useState('');

    if (disponibles.length === 0) {
        return null;
    }

    const seleccionado = disponibles.find(
        (d) => d.forma === forma && d.campo === campo,
    );
    const esArchivo = seleccionado?.tipo_campo === 'documento';
    const url =
        campoUpdate({ cliente: clienteId, campo }).url + `?forma=${forma}`;

    const cambiarForma = (nuevaForma: string) => {
        setForma(nuevaForma);
        const primero = disponibles.find((d) => d.forma === nuevaForma);
        setCampo(primero?.campo ?? '');
    };

    const submitTexto = () => {
        const contenido = parseContenido(raw);

        router.patch(
            url,
            {
                modo: 'texto',
                tipo_dato: guessTipoDato(contenido),
                contenido,
            },
            { preserveScroll: true, onSuccess: () => setOpen(false) },
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="secondary">
                    {t('clienteShow.addField.trigger')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>{t('clienteShow.addField.title')}</DialogTitle>
                <DialogDescription>
                    {t('clienteShow.addField.description')}
                </DialogDescription>

                <div className="grid gap-2">
                    <label className="text-sm font-medium" htmlFor="forma">
                        {t('common.form')}
                    </label>
                    <select
                        id="forma"
                        className="rounded border bg-background p-2 text-sm"
                        value={forma}
                        onChange={(e) => cambiarForma(e.target.value)}
                    >
                        {[...new Set(disponibles.map((d) => d.forma))].map(
                            (f) => (
                                <option key={f} value={f}>
                                    {f}
                                </option>
                            ),
                        )}
                    </select>
                </div>

                <div className="mt-2 grid gap-2">
                    <label className="text-sm font-medium" htmlFor="campo">
                        {t('clienteShow.addField.fieldLabel')}
                    </label>
                    <select
                        id="campo"
                        className="rounded border bg-background p-2 text-sm"
                        value={campo}
                        onChange={(e) => setCampo(e.target.value)}
                    >
                        {camposDeForma.map((d) => (
                            <option key={d.campo} value={d.campo}>
                                {d.campo}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="mt-4">
                    {esArchivo ? (
                        <DocumentoUploadForm
                            url={url}
                            formatos={seleccionado?.formatos_aceptados ?? null}
                            submitLabel={t('common.save')}
                            onDone={() => setOpen(false)}
                        />
                    ) : (
                        <>
                            <Textarea
                                value={raw}
                                onChange={(e) => setRaw(e.target.value)}
                                placeholder={t('clienteShow.edit.placeholder')}
                                rows={4}
                            />

                            <DialogFooter className="mt-4">
                                <Button onClick={submitTexto}>
                                    {t('common.save')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}

function EliminarCampoButton({
    clienteId,
    campo,
}: {
    clienteId: number;
    campo: CampoCliente;
}) {
    const { t } = useTranslation();

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm" className="text-red-600">
                    {t('common.delete')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    {t('clienteShow.deleteField.title', { campo: campo.campo })}
                </DialogTitle>
                <DialogDescription>
                    {t('clienteShow.deleteField.description')}
                </DialogDescription>
                <DialogFooter>
                    <Button
                        variant="destructive"
                        onClick={() =>
                            router.delete(
                                campoDestroy({
                                    cliente: clienteId,
                                    campo: campo.campo,
                                }).url + `?forma=${campo.forma}`,
                                { preserveScroll: true },
                            )
                        }
                    >
                        {t('common.delete')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function HistorialDialog({
    clienteId,
    campo,
}: {
    clienteId: number;
    campo: CampoCliente;
}) {
    const { t } = useTranslation();
    const [items, setItems] = useState<HistorialCambio[] | null>(null);

    const load = async () => {
        const response = await fetch(
            campoHistorial({ cliente: clienteId, campo: campo.campo }).url +
                `?forma=${campo.forma}`,
            { headers: { Accept: 'application/json' } },
        );
        const json = await response.json();
        setItems(json.historial ?? json.data ?? []);
    };

    return (
        <Dialog onOpenChange={(open) => open && load()}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm">
                    {t('clienteShow.history.trigger')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    {t('clienteShow.history.title', { campo: campo.campo })}
                </DialogTitle>
                <div className="max-h-80 space-y-3 overflow-y-auto text-sm">
                    {items === null && <p>{t('common.loading')}</p>}
                    {items?.length === 0 && (
                        <p className="text-muted-foreground">
                            {t('clienteShow.history.empty')}
                        </p>
                    )}
                    {items?.map((h, i) => (
                        <div key={i} className="rounded border p-2">
                            <div className="text-xs text-muted-foreground">
                                {h.created_at} · {h.source}
                                {h.modificado_por
                                    ? ` · ${h.modificado_por}`
                                    : ''}
                            </div>
                            <div className="mt-1 grid grid-cols-2 gap-2 text-xs">
                                <div>
                                    <span className="font-medium">
                                        {t('clienteShow.history.before')}
                                    </span>{' '}
                                    {JSON.stringify(h.valor_anterior)}
                                </div>
                                <div>
                                    <span className="font-medium">
                                        {t('clienteShow.history.after')}
                                    </span>{' '}
                                    {JSON.stringify(h.valor_nuevo)}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </DialogContent>
        </Dialog>
    );
}

function RevealButton({
    clienteId,
    campo,
}: {
    clienteId: number;
    campo: CampoCliente;
}) {
    const { t } = useTranslation();
    const [valor, setValor] = useState<unknown>(null);
    const [needsPassword, setNeedsPassword] = useState(false);

    const reveal = async () => {
        const response = await fetch(
            campoReveal({ cliente: clienteId, campo: campo.campo }).url +
                `?forma=${campo.forma}`,
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') ?? '',
                },
            },
        );

        if (response.status === 423) {
            setNeedsPassword(true);

            return;
        }

        const json = await response.json();
        setValor(json.valor);
    };

    // El valor enmascarado (ej. "***-**-6789") ya viene calculado del backend en
    // campo.valor — siempre se muestra, para que se vea que hay algo cargado sin
    // tener que revelarlo. "Revelar" es una acción aparte, no la única forma de ver
    // que el dato existe.
    if (campo.valor === null || campo.valor === undefined) {
        return (
            <span className="text-muted-foreground">{t('common.none')}</span>
        );
    }

    return (
        <div className="flex items-center gap-2">
            <code className="text-xs">
                {JSON.stringify(valor ?? campo.valor)}
            </code>
            {valor === null &&
                (needsPassword ? (
                    <a
                        href={confirmPasswordShow().url}
                        className="text-xs text-amber-600 underline"
                    >
                        {t('clienteShow.reveal.confirmPassword')}
                    </a>
                ) : (
                    <Button variant="ghost" size="sm" onClick={reveal}>
                        {t('clienteShow.reveal.reveal')}
                    </Button>
                ))}
        </div>
    );
}

export default function ClienteShow({
    cliente,
    formas,
    campos,
    catalogoDisponible,
}: {
    cliente: { id: number; name: string; email: string; phone: string | null };
    formas: ClienteForma[];
    campos: CampoCliente[];
    catalogoDisponible: CatalogoDisponibleItem[];
}) {
    const { t } = useTranslation();
    const { auth } = usePage<PageProps>().props;
    const esAdministrador = auth.user.role === 'administrator';

    return (
        <>
            <Head title={cliente.name} />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            {cliente.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {cliente.email}
                            {cliente.phone ? ` · ${cliente.phone}` : ''}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <a href={clienteExport(cliente.id).url}>
                            <Button variant="secondary">
                                {t('clienteShow.exportZip')}
                            </Button>
                        </a>
                        {esAdministrador && (
                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button variant="destructive">
                                        {t('clienteShow.deleteClient.trigger')}
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogTitle>
                                        {t('clienteShow.deleteClient.title', {
                                            name: cliente.name,
                                        })}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {t(
                                            'clienteShow.deleteClient.description',
                                        )}
                                    </DialogDescription>
                                    <DialogFooter>
                                        <Button
                                            variant="destructive"
                                            onClick={() =>
                                                router.delete(
                                                    clienteDestroy(cliente.id)
                                                        .url,
                                                )
                                            }
                                        >
                                            {t(
                                                'clienteShow.deleteClient.confirm',
                                            )}
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        )}
                    </div>
                </div>

                <div className="flex flex-wrap gap-2">
                    {formas.map((f) => (
                        <div
                            key={f.forma}
                            className="flex items-center gap-2 rounded-lg border p-2"
                        >
                            <span className="text-sm font-medium">
                                {f.forma_label}
                            </span>
                            <Badge
                                variant={
                                    f.estado === 'completo'
                                        ? 'default'
                                        : 'secondary'
                                }
                            >
                                {f.estado === 'completo'
                                    ? t('clienteShow.formState.complete')
                                    : t('clienteShow.formState.inProgress')}
                            </Badge>
                            {f.revisado_en ? (
                                <Badge variant="outline">
                                    {t('clienteShow.reviewed')}
                                </Badge>
                            ) : (
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() =>
                                        router.post(
                                            marcarRevisado({
                                                cliente: cliente.id,
                                                forma: f.forma,
                                            }).url,
                                        )
                                    }
                                >
                                    {t('clienteShow.markReviewed')}
                                </Button>
                            )}
                        </div>
                    ))}
                </div>

                <div className="flex justify-end">
                    <AgregarCampoDialog
                        clienteId={cliente.id}
                        disponibles={catalogoDisponible}
                    />
                </div>

                <Card className="overflow-hidden py-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('common.form')}</TableHead>
                                <TableHead>
                                    {t('clienteShow.table.field')}
                                </TableHead>
                                <TableHead>
                                    {t('clienteShow.table.status')}
                                </TableHead>
                                <TableHead>
                                    {t('clienteShow.table.value')}
                                </TableHead>
                                <TableHead className="text-right">
                                    {t('common.actions')}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {campos.map((campo) => (
                                <TableRow key={`${campo.forma}-${campo.campo}`}>
                                    <TableCell className="text-xs text-muted-foreground">
                                        {campo.forma}
                                    </TableCell>
                                    <TableCell>{campo.campo}</TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                ESTADO_VARIANT[campo.estado]
                                            }
                                        >
                                            {t(
                                                `clienteShow.fieldState.${campo.estado}`,
                                            )}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="max-w-xs truncate text-sm">
                                        {campo.documento ? (
                                            <DocumentoViewerDialog
                                                documento={campo.documento}
                                            />
                                        ) : campo.es_sensible ? (
                                            <RevealButton
                                                clienteId={cliente.id}
                                                campo={campo}
                                            />
                                        ) : campo.valor === null ||
                                          campo.valor === undefined ? (
                                            <span className="text-muted-foreground">
                                                {t('common.none')}
                                            </span>
                                        ) : (
                                            JSON.stringify(campo.valor)
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <HistorialDialog
                                            clienteId={cliente.id}
                                            campo={campo}
                                        />
                                        {(campo.tipo_campo === 'documento' ||
                                            campo.tipo_campo === 'mixto') && (
                                            <SubirDocumentoDialog
                                                clienteId={cliente.id}
                                                campo={campo}
                                            />
                                        )}
                                        {(campo.tipo_campo === 'dato' ||
                                            campo.tipo_campo === 'mixto') && (
                                            <EditCampoDialog
                                                clienteId={cliente.id}
                                                campo={campo}
                                            />
                                        )}
                                        <EliminarCampoButton
                                            clienteId={cliente.id}
                                            campo={campo}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}

                            {campos.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="text-center text-muted-foreground"
                                    >
                                        {t('clienteShow.table.empty')}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </Card>
            </div>
        </>
    );
}

ClienteShow.layout = {
    breadcrumbs: [
        { title: 'nav.dashboard', href: dashboard() },
        { title: 'nav.clients', href: clientesIndex() },
    ],
};
