import { Head, router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, Check, Circle, Upload } from 'lucide-react';
import { Fragment, useCallback, useEffect, useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { show as confirmPasswordShow } from '@/actions/Laravel/Fortify/Http/Controllers/ConfirmablePasswordController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    show as clienteShowRoute,
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

// ── Presentación de estado ───────────────────────────────────────────────
// Recorrer cientos de campos con pills de color termina en ruido: todos pesan
// lo mismo y ninguno destaca. Acá el estado se dice dos veces, en dos canales
// distintos: un riel de color al borde de la fila, que se lee de reojo sin
// leer, y una etiqueta en versalitas monoespaciadas que confirma al detenerse.

const ESTADO_RIEL: Record<CampoCliente['estado'], string> = {
    pendiente: 'border-l-state-pendiente',
    recibido: 'border-l-state-recibido',
    invalido: 'border-l-state-invalido',
};

const ESTADO_FONDO: Record<CampoCliente['estado'], string> = {
    pendiente: 'bg-state-pendiente',
    recibido: 'bg-state-recibido',
    invalido: 'bg-state-invalido',
};

const ESTADO_TINTA: Record<CampoCliente['estado'], string> = {
    pendiente: 'text-muted-foreground',
    recibido: 'text-foreground',
    invalido: 'text-destructive',
};

const ESTADO_ICONO: Record<CampoCliente['estado'], typeof Circle> = {
    pendiente: Circle,
    recibido: Check,
    invalido: AlertTriangle,
};

function EstadoTag({ estado }: { estado: CampoCliente['estado'] }) {
    const { t } = useTranslation();
    const Icono = ESTADO_ICONO[estado];

    return (
        <span
            className={`inline-flex items-center gap-1.5 font-mono text-micro uppercase ${ESTADO_TINTA[estado]}`}
        >
            <Icono className="size-3 shrink-0" aria-hidden />
            {t(`clienteShow.fieldState.${estado}`)}
        </span>
    );
}

// Los campos únicos por cliente se guardan bajo esta forma canónica; no es una
// forma fiscal sino la identidad del cliente (SSN, cónyuge, dependientes).
const TRANSVERSAL = 'transversal';

// El código es como los preparadores nombran el trabajo — «el 1040», «la
// Schedule C» — así que encabeza la sección en vez de quedar sepultado en una
// columna. Sale del valor del enum, que es el identificador estable.
function codigoForma(forma: string): string {
    if (forma === TRANSVERSAL) {
        return 'IDENT';
    }

    return forma
        .replace(/^form_/, '')
        .replace(/^schedule_/, 'sch ')
        .replace(/_/g, '-')
        .toUpperCase();
}

// La etiqueta del backend ya trae el código («Form 1040 (Individual)»), que
// arriba se muestra aparte. Acá queda la parte en lenguaje llano.
function descriptorForma(label: string): string {
    return label.match(/\(([^)]+)\)\s*$/)?.[1] ?? label;
}

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

// Convierte una clave técnica (snake_case) en una etiqueta legible:
// "nombre_completo" -> "Nombre completo", "ssn" -> "SSN", "w2" -> "W2".
const ACRONIMOS = new Set([
    'ssn',
    'itin',
    'rfc',
    'ein',
    'w2',
    'id',
    'irs',
    'usa',
]);

function humanizarClave(clave: string): string {
    return clave
        .split(/[_\s]+/)
        .filter(Boolean)
        .map((palabra, i) => {
            if (ACRONIMOS.has(palabra.toLowerCase())) {
                return palabra.toUpperCase();
            }

            return i === 0
                ? palabra.charAt(0).toUpperCase() + palabra.slice(1)
                : palabra;
        })
        .join(' ');
}

// Renderiza cualquier valor recolectado de forma amigable para un usuario no
// técnico: sin corchetes ni llaves. Strings/números tal cual, listas como
// etiquetas, objetos como pares "campo: valor", y listas de objetos como fichas.
function FieldValue({ value }: { value: unknown }) {
    const { t } = useTranslation();

    if (
        value === null ||
        value === undefined ||
        (typeof value === 'string' && value.trim() === '')
    ) {
        return (
            <span className="text-muted-foreground">{t('common.none')}</span>
        );
    }

    if (typeof value === 'boolean') {
        return <span>{value ? t('common.yes') : t('common.no')}</span>;
    }

    if (typeof value === 'number' || typeof value === 'string') {
        // Monoespaciada solo donde aporta: cifras alineadas y 0/O
        // inconfundibles en SSN, montos, fechas y valores enmascarados. Un
        // nombre o una dirección se leen peor en mono, así que el texto sin
        // dígitos se queda en la sans.
        const esDato = typeof value === 'number' || /\d/.test(value);

        return (
            <span
                className={`wrap-break-word whitespace-pre-wrap ${
                    esDato ? 'font-mono tabular-nums' : ''
                }`}
            >
                {String(value)}
            </span>
        );
    }

    if (Array.isArray(value)) {
        if (value.length === 0) {
            return (
                <span className="text-muted-foreground">
                    {t('clienteShow.value.emptyList')}
                </span>
            );
        }

        const soloPrimitivos = value.every(
            (v) => v === null || typeof v !== 'object',
        );

        if (soloPrimitivos) {
            return (
                <div className="flex flex-wrap gap-1">
                    {value.map((v, i) => (
                        <Badge
                            key={i}
                            variant="secondary"
                            className="font-normal"
                        >
                            {String(v)}
                        </Badge>
                    ))}
                </div>
            );
        }

        return (
            <div className="space-y-2">
                {value.map((v, i) => (
                    <div key={i} className="rounded-md border bg-muted/30 p-2">
                        <div className="mb-1 text-xs font-medium text-muted-foreground">
                            {t('clienteShow.value.record', { n: i + 1 })}
                        </div>
                        <FieldValue value={v} />
                    </div>
                ))}
            </div>
        );
    }

    if (typeof value === 'object') {
        const entries = Object.entries(value as Record<string, unknown>);

        if (entries.length === 0) {
            return (
                <span className="text-muted-foreground">
                    {t('clienteShow.value.emptyList')}
                </span>
            );
        }

        return (
            <dl className="grid gap-x-3 gap-y-1 sm:grid-cols-[minmax(0,auto)_1fr]">
                {entries.map(([k, v]) => (
                    <Fragment key={k}>
                        <dt className="text-xs font-medium text-muted-foreground sm:text-right">
                            {humanizarClave(k)}
                        </dt>
                        <dd className="text-sm">
                            <FieldValue value={v} />
                        </dd>
                    </Fragment>
                ))}
            </dl>
        );
    }

    return <span>{String(value)}</span>;
}

// Un valor es "complejo" (objeto o lista de objetos) si merece verse en un
// popup ordenado en vez de amontonado dentro de la celda. Los escalares y las
// listas de valores simples se muestran bien en línea.
function esComplejo(value: unknown): boolean {
    if (value === null || typeof value !== 'object') {
        return false;
    }

    if (Array.isArray(value)) {
        return value.some((v) => v !== null && typeof v === 'object');
    }

    return true;
}

function esObjetoPlano(v: unknown): boolean {
    return (
        !!v &&
        typeof v === 'object' &&
        !Array.isArray(v) &&
        Object.values(v).every((x) => x === null || typeof x !== 'object')
    );
}

type EditorKind =
    'scalar' | 'stringList' | 'object' | 'objectList' | 'advanced';

// Elige el editor más amigable según la forma del valor actual:
// escalar -> un campo de texto; lista simple -> uno por línea; objeto plano ->
// un campo por atributo; lista de objetos -> una card por registro; datos
// anidados -> editor JSON avanzado.
function editorKindFor(v: unknown): EditorKind {
    if (v === null || v === undefined || typeof v !== 'object') {
        return 'scalar';
    }

    if (Array.isArray(v)) {
        if (v.every((x) => x === null || typeof x !== 'object')) {
            return 'stringList';
        }

        return v.every((x) => esObjetoPlano(x)) ? 'objectList' : 'advanced';
    }

    return esObjetoPlano(v) ? 'object' : 'advanced';
}

function scalarToString(v: unknown): string {
    if (v === null || v === undefined || typeof v === 'object') {
        return '';
    }

    return String(v);
}

function objToStrings(v: unknown): Record<string, string> {
    if (!v || typeof v !== 'object' || Array.isArray(v)) {
        return {};
    }

    const out: Record<string, string> = {};

    for (const [k, val] of Object.entries(v)) {
        out[k] = val === null || val === undefined ? '' : String(val);
    }

    return out;
}

// Editor de valores que reporta el contenido editado (y su validez) al padre.
// Evita exponer JSON al usuario salvo en datos anidados (caso "advanced").
function ValueEditor({
    initial,
    onChange,
    onValidityChange,
}: {
    initial: unknown;
    onChange: (v: Json) => void;
    onValidityChange: (ok: boolean) => void;
}) {
    const { t } = useTranslation();
    const [kind] = useState<EditorKind>(() => editorKindFor(initial));
    const [scalar, setScalar] = useState(() => scalarToString(initial));
    const [list, setList] = useState(() =>
        Array.isArray(initial) ? initial.map((x) => String(x)).join('\n') : '',
    );
    const [obj, setObj] = useState<Record<string, string>>(() =>
        objToStrings(initial),
    );
    const [items, setItems] = useState<Record<string, string>[]>(() =>
        Array.isArray(initial) ? initial.map((x) => objToStrings(x)) : [],
    );
    // Claves de cada registro de la lista, inferidas del valor inicial. Sirven
    // de plantilla al agregar un registro nuevo (vacío).
    const [plantilla] = useState<string[]>(() => {
        const claves = new Set<string>();

        (Array.isArray(initial) ? initial : []).forEach((x) => {
            if (x && typeof x === 'object' && !Array.isArray(x)) {
                Object.keys(x).forEach((k) => claves.add(k));
            }
        });

        return [...claves];
    });
    const [raw, setRaw] = useState(() =>
        initial === null || initial === undefined
            ? ''
            : JSON.stringify(initial, null, 2),
    );

    useEffect(() => {
        if (kind !== 'scalar') {
            return;
        }

        onChange(scalar);
        onValidityChange(true);
    }, [kind, scalar, onChange, onValidityChange]);

    useEffect(() => {
        if (kind !== 'stringList') {
            return;
        }

        onChange(
            list
                .split('\n')
                .map((s) => s.trim())
                .filter((s) => s.length > 0),
        );
        onValidityChange(true);
    }, [kind, list, onChange, onValidityChange]);

    useEffect(() => {
        if (kind !== 'object') {
            return;
        }

        onChange(obj);
        onValidityChange(true);
    }, [kind, obj, onChange, onValidityChange]);

    useEffect(() => {
        if (kind !== 'objectList') {
            return;
        }

        onChange(items);
        onValidityChange(true);
    }, [kind, items, onChange, onValidityChange]);

    useEffect(() => {
        if (kind !== 'advanced') {
            return;
        }

        try {
            onChange(JSON.parse(raw) as Json);
            onValidityChange(true);
        } catch {
            onValidityChange(false);
        }
    }, [kind, raw, onChange, onValidityChange]);

    if (kind === 'scalar') {
        return (
            <Input
                value={scalar}
                onChange={(e) => setScalar(e.target.value)}
                placeholder={t('clienteShow.edit.valuePlaceholder')}
            />
        );
    }

    if (kind === 'stringList') {
        return (
            <div className="grid gap-1.5">
                <Textarea
                    value={list}
                    onChange={(e) => setList(e.target.value)}
                    rows={4}
                    placeholder={t('clienteShow.edit.listPlaceholder')}
                />
                <p className="text-xs text-muted-foreground">
                    {t('clienteShow.edit.listHint')}
                </p>
            </div>
        );
    }

    if (kind === 'object') {
        return (
            <div className="grid gap-3">
                {Object.keys(obj).map((k) => (
                    <div key={k} className="grid gap-1.5">
                        <Label htmlFor={`campo-${k}`}>
                            {humanizarClave(k)}
                        </Label>
                        <Input
                            id={`campo-${k}`}
                            value={obj[k]}
                            onChange={(e) =>
                                setObj((prev) => ({
                                    ...prev,
                                    [k]: e.target.value,
                                }))
                            }
                        />
                    </div>
                ))}
            </div>
        );
    }

    if (kind === 'objectList') {
        const actualizar = (idx: number, clave: string, valor: string) =>
            setItems((prev) =>
                prev.map((registro, i) =>
                    i === idx ? { ...registro, [clave]: valor } : registro,
                ),
            );
        const eliminar = (idx: number) =>
            setItems((prev) => prev.filter((_, i) => i !== idx));
        const agregar = () =>
            setItems((prev) => [
                ...prev,
                Object.fromEntries(plantilla.map((k) => [k, ''])),
            ]);

        return (
            <div className="grid gap-3">
                {items.map((registro, idx) => (
                    <div key={idx} className="space-y-3 rounded-lg border p-3">
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-medium">
                                {t('clienteShow.value.record', { n: idx + 1 })}
                            </span>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="text-destructive hover:text-destructive"
                                onClick={() => eliminar(idx)}
                            >
                                {t('common.delete')}
                            </Button>
                        </div>
                        {plantilla.map((k) => (
                            <div key={k} className="grid gap-1.5">
                                <Label htmlFor={`item-${idx}-${k}`}>
                                    {humanizarClave(k)}
                                </Label>
                                <Input
                                    id={`item-${idx}-${k}`}
                                    value={registro[k] ?? ''}
                                    onChange={(e) =>
                                        actualizar(idx, k, e.target.value)
                                    }
                                />
                            </div>
                        ))}
                    </div>
                ))}

                {items.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        {t('clienteShow.value.emptyList')}
                    </p>
                )}

                <Button
                    variant="secondary"
                    size="sm"
                    className="justify-self-start"
                    onClick={agregar}
                >
                    {t('clienteShow.edit.addRecord')}
                </Button>
            </div>
        );
    }

    return (
        <div className="grid gap-1.5">
            <Textarea
                value={raw}
                onChange={(e) => setRaw(e.target.value)}
                rows={8}
                className="font-mono text-xs"
            />
            <p className="text-xs text-muted-foreground">
                {t('clienteShow.edit.advancedHint')}
            </p>
        </div>
    );
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
    taxYear,
    campo,
}: {
    clienteId: number;
    taxYear: number;
    campo: CampoCliente;
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const yaHayArchivo = campo.documento !== null;
    const url =
        campoUpdate({ cliente: clienteId, campo: campo.campo }).url +
        `?forma=${campo.forma}&tax_year=${taxYear}`;

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
    taxYear,
    campo,
    formaLabel,
}: {
    clienteId: number;
    taxYear: number;
    campo: CampoCliente;
    formaLabel: string;
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [contenido, setContenido] = useState<Json>(
        () => (campo.valor ?? '') as Json,
    );
    const [valido, setValido] = useState(true);
    const onChange = useCallback((v: Json) => setContenido(v), []);
    const onValidityChange = useCallback((ok: boolean) => setValido(ok), []);

    // Edición del valor de texto. Los archivos se cargan/reemplazan con
    // SubirDocumentoDialog (subida multipart con progreso y validación).
    const submit = () => {
        router.patch(
            campoUpdate({ cliente: clienteId, campo: campo.campo }).url +
                `?forma=${campo.forma}&tax_year=${taxYear}`,
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
                <Button variant="ghost" size="sm">
                    {t('clienteShow.edit.trigger')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    {t('clienteShow.edit.title', {
                        campo: humanizarClave(campo.campo),
                    })}
                </DialogTitle>
                <DialogDescription>
                    {t('clienteShow.edit.description', { forma: formaLabel })}
                </DialogDescription>

                {/* key: al reabrir, reinicia el editor con el valor actual */}
                <div className="max-h-[65vh] overflow-y-auto pr-1">
                    <ValueEditor
                        key={open ? 'abierto' : 'cerrado'}
                        initial={campo.valor}
                        onChange={onChange}
                        onValidityChange={onValidityChange}
                    />
                </div>

                <DialogFooter>
                    <Button onClick={submit} disabled={!valido}>
                        {t('clienteShow.edit.save')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function AgregarCampoDialog({
    clienteId,
    taxYear,
    disponibles,
}: {
    clienteId: number;
    taxYear: number;
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
        campoUpdate({ cliente: clienteId, campo }).url +
        `?forma=${forma}&tax_year=${taxYear}`;

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
                                    {f === 'transversal'
                                        ? t('clienteShow.transversalLabel')
                                        : f}
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
    taxYear,
    campo,
}: {
    clienteId: number;
    taxYear: number;
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
                                }).url +
                                    `?forma=${campo.forma}&tax_year=${taxYear}`,
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

const SOURCE_VARIANT: Record<
    HistorialCambio['source'],
    'default' | 'secondary' | 'outline'
> = {
    agente_ia: 'default',
    preparador: 'secondary',
    administrador: 'outline',
};

function HistorialDialog({
    clienteId,
    taxYear,
    campo,
}: {
    clienteId: number;
    taxYear: number;
    campo: CampoCliente;
}) {
    const { t, i18n } = useTranslation();
    const [items, setItems] = useState<HistorialCambio[] | null>(null);

    const formatDate = (iso: string | null): string => {
        if (!iso) {
            return '';
        }

        const fecha = new Date(iso);

        if (Number.isNaN(fecha.getTime())) {
            return iso;
        }

        return new Intl.DateTimeFormat(i18n.language, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(fecha);
    };

    const load = async () => {
        const response = await fetch(
            campoHistorial({ cliente: clienteId, campo: campo.campo }).url +
                `?forma=${campo.forma}&tax_year=${taxYear}`,
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
            <DialogContent className="sm:max-w-lg">
                <DialogTitle>
                    {t('clienteShow.history.title', {
                        campo: humanizarClave(campo.campo),
                    })}
                </DialogTitle>
                <div className="max-h-[70vh] space-y-3 overflow-y-auto">
                    {items === null && (
                        <p className="text-sm text-muted-foreground">
                            {t('common.loading')}
                        </p>
                    )}
                    {items?.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            {t('clienteShow.history.empty')}
                        </p>
                    )}
                    {items?.map((h, i) => (
                        <div key={i} className="rounded-lg border p-3">
                            <div className="mb-2 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                <Badge
                                    variant={SOURCE_VARIANT[h.source]}
                                    className="font-normal"
                                >
                                    {t(
                                        `clienteShow.history.source.${h.source}`,
                                    )}
                                </Badge>
                                <span>{formatDate(h.created_at)}</span>
                                {h.modificado_por && (
                                    <span>· {h.modificado_por}</span>
                                )}
                            </div>
                            <div className="space-y-2">
                                <div>
                                    <div className="text-xs font-medium text-muted-foreground">
                                        {t('clienteShow.history.before')}
                                    </div>
                                    <div className="mt-0.5 text-sm">
                                        <FieldValue value={h.valor_anterior} />
                                    </div>
                                </div>
                                <div>
                                    <div className="text-xs font-medium text-muted-foreground">
                                        {t('clienteShow.history.after')}
                                    </div>
                                    <div className="mt-0.5 text-sm">
                                        <FieldValue value={h.valor_nuevo} />
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </DialogContent>
        </Dialog>
    );
}

// Muestra el valor recolectado de un campo. Los valores simples se ven en línea;
// los complejos (objeto o lista de objetos) se resumen con un botón que abre un
// popup ordenado. Integra el "Revelar" de los campos sensibles.
function ValorCampo({
    clienteId,
    taxYear,
    campo,
    formaLabel,
}: {
    clienteId: number;
    taxYear: number;
    campo: CampoCliente;
    formaLabel: string;
}) {
    const { t } = useTranslation();
    const [revelado, setRevelado] = useState<unknown>(undefined);
    const [needsPassword, setNeedsPassword] = useState(false);

    const reveal = async () => {
        const response = await fetch(
            campoReveal({ cliente: clienteId, campo: campo.campo }).url +
                `?forma=${campo.forma}&tax_year=${taxYear}`,
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
        setRevelado(json.valor);
    };

    // El valor enmascarado (ej. "***-**-6789") ya viene del backend en campo.valor
    // — siempre se muestra, para que se vea que hay algo cargado sin revelarlo.
    const valor = revelado !== undefined ? revelado : campo.valor;

    if (
        valor === null ||
        valor === undefined ||
        (typeof valor === 'string' && valor.trim() === '')
    ) {
        return (
            <span className="text-muted-foreground">{t('common.none')}</span>
        );
    }

    const sensibleOculto = campo.es_sensible && revelado === undefined;
    const revealControl = sensibleOculto ? (
        needsPassword ? (
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
        )
    ) : null;

    // Simple → en línea.
    if (!esComplejo(valor)) {
        return (
            <div className="flex flex-wrap items-center gap-2">
                <FieldValue value={valor} />
                {revealControl}
            </div>
        );
    }

    // Complejo → resumen + popup ordenado.
    const resumen = Array.isArray(valor)
        ? t('clienteShow.value.recordCount', { count: valor.length })
        : t('clienteShow.value.fieldCount', {
              count: Object.keys(valor as object).length,
          });

    return (
        <div className="flex flex-wrap items-center gap-2">
            <Dialog>
                <DialogTrigger asChild>
                    <Button variant="outline" size="sm">
                        {t('clienteShow.value.view')}
                        <span className="ml-1 text-muted-foreground">
                            · {resumen}
                        </span>
                    </Button>
                </DialogTrigger>
                <DialogContent className="sm:max-w-lg">
                    <DialogTitle>{humanizarClave(campo.campo)}</DialogTitle>
                    <DialogDescription>{formaLabel}</DialogDescription>
                    <div className="max-h-[70vh] overflow-y-auto pr-1">
                        <FieldValue value={valor} />
                    </div>
                </DialogContent>
            </Dialog>
            {revealControl}
        </div>
    );
}

// Una forma por sección: el preparador trabaja «el 1040», no una tabla plana
// donde la forma es una columna más que hay que ir leyendo fila por fila.
function FormaSection({
    clienteId,
    taxYear,
    forma,
    label,
    info,
    campos,
    faltantes,
}: {
    clienteId: number;
    taxYear: number;
    forma: string;
    label: string;
    info: ClienteForma | undefined;
    campos: CampoCliente[];
    // Campos del catálogo para esta forma que el cliente todavía no tiene
    // cargados (ni siquiera como fila pendiente) — no están en `campos`
    // porque no existe CampoCliente hasta que llega un primer valor. Sin
    // esto, "recibidos" se medía contra sí mismo: un 1040 con un solo campo
    // cargado mostraba «1/1», aunque el catálogo real tenga cinco.
    faltantes: number;
}) {
    const { t } = useTranslation();
    const tituloId = useId();
    const recibidos = campos.filter((c) => c.estado === 'recibido').length;
    const total = campos.length + faltantes;

    return (
        <section aria-labelledby={tituloId}>
            <header className="flex flex-wrap items-end justify-between gap-x-6 gap-y-3 border-b pb-2.5">
                <div className="min-w-0">
                    <p className="font-mono text-micro text-muted-foreground uppercase">
                        {codigoForma(forma)}
                    </p>
                    <h2
                        id={tituloId}
                        className="text-title font-semibold text-foreground"
                    >
                        {descriptorForma(label)}
                    </h2>
                </div>

                <div className="flex items-center gap-3">
                    {/*
                     * Mini-mapa: un segmento por campo del catálogo, con la
                     * misma rampa que los rieles de abajo — incluyendo los que
                     * todavía no se cargaron (sin fila propia en `campos`),
                     * pintados como pendiente. Muestra la forma de lo que
                     * falta, no solo cuánto — dos huecos al final no es lo
                     * mismo que dos huecos salteados. El texto de al lado lo
                     * dice para quien no puede verlo.
                     */}
                    <div
                        aria-hidden
                        className="flex w-20 gap-px overflow-hidden rounded-full sm:w-28"
                    >
                        {campos.map((c) => (
                            <span
                                key={`${c.forma}-${c.campo}`}
                                className={`h-1.5 flex-1 ${ESTADO_FONDO[c.estado]}`}
                            />
                        ))}
                        {Array.from({ length: faltantes }).map((_, i) => (
                            <span
                                key={`faltante-${i}`}
                                className={`h-1.5 flex-1 ${ESTADO_FONDO.pendiente}`}
                            />
                        ))}
                    </div>

                    <span className="font-mono text-micro text-muted-foreground tabular-nums">
                        {t('clienteShow.section.received', {
                            recibidos,
                            total,
                        })}
                    </span>

                    {info &&
                        (info.revisado_en ? (
                            <span className="inline-flex items-center gap-1.5 font-mono text-micro text-foreground uppercase">
                                <Check
                                    className="size-3 shrink-0"
                                    aria-hidden
                                />
                                {t('clienteShow.reviewed')}
                            </span>
                        ) : (
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() =>
                                    router.post(
                                        marcarRevisado({
                                            cliente: clienteId,
                                            forma,
                                        }).url,
                                        { tax_year: taxYear },
                                    )
                                }
                            >
                                {t('clienteShow.markReviewed')}
                            </Button>
                        ))}
                </div>
            </header>

            <Table>
                {/*
                 * El header de <TableHeader> de fábrica es un bloque navy
                 * sólido — pensado para una tabla suelta, no para repetirse
                 * una vez por forma. Acá se apaga a una fila utilitaria (borde
                 * inferior, sin relleno): el navy queda para el riel de
                 * estado, que es el único lugar donde este color debe hablar
                 * dentro de la sección.
                 */}
                <TableHeader className="bg-transparent [&_th]:text-muted-foreground [&_tr]:border-border">
                    <TableRow className="hover:bg-transparent">
                        <TableHead className="font-mono text-micro uppercase">
                            {t('clienteShow.table.field')}
                        </TableHead>
                        <TableHead className="font-mono text-micro uppercase">
                            {t('clienteShow.table.value')}
                        </TableHead>
                        <TableHead className="font-mono text-micro uppercase">
                            {t('clienteShow.table.status')}
                        </TableHead>
                        <TableHead className="text-right font-mono text-micro uppercase">
                            {t('common.actions')}
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {campos.map((campo) => (
                        <TableRow key={`${campo.forma}-${campo.campo}`}>
                            <TableCell
                                className={`border-l-[3px] align-top font-medium ${ESTADO_RIEL[campo.estado]}`}
                            >
                                {humanizarClave(campo.campo)}
                            </TableCell>
                            <TableCell className="max-w-sm align-top text-sm">
                                {campo.documento ? (
                                    <DocumentoViewerDialog
                                        documento={campo.documento}
                                    />
                                ) : (
                                    <ValorCampo
                                        clienteId={clienteId}
                                        taxYear={taxYear}
                                        campo={campo}
                                        formaLabel={label}
                                    />
                                )}
                            </TableCell>
                            <TableCell className="align-top">
                                <EstadoTag estado={campo.estado} />
                            </TableCell>
                            <TableCell className="text-right align-top">
                                <HistorialDialog
                                    clienteId={clienteId}
                                    taxYear={taxYear}
                                    campo={campo}
                                />
                                {(campo.tipo_campo === 'documento' ||
                                    campo.tipo_campo === 'mixto') && (
                                    <SubirDocumentoDialog
                                        clienteId={clienteId}
                                        taxYear={taxYear}
                                        campo={campo}
                                    />
                                )}
                                {(campo.tipo_campo === 'dato' ||
                                    campo.tipo_campo === 'mixto') && (
                                    <EditCampoDialog
                                        clienteId={clienteId}
                                        taxYear={taxYear}
                                        campo={campo}
                                        formaLabel={label}
                                    />
                                )}
                                <EliminarCampoButton
                                    clienteId={clienteId}
                                    taxYear={taxYear}
                                    campo={campo}
                                />
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </section>
    );
}

export default function ClienteShow({
    cliente,
    formas,
    campos,
    catalogoDisponible,
    taxYearActual,
}: {
    cliente: { id: number; name: string; email: string; phone: string | null };
    formas: ClienteForma[];
    campos: CampoCliente[];
    catalogoDisponible: CatalogoDisponibleItem[];
    taxYearActual: number;
}) {
    const { t } = useTranslation();
    const { auth } = usePage<PageProps>().props;
    const esAdministrador = auth.user.role === 'administrator';

    // Rango simple alrededor del año actual — alcanza para elegir un año
    // distinto al que trae el backend sin depender de otro prop adicional.
    const anosSeleccionables = Array.from(
        new Set([taxYearActual - 1, taxYearActual, taxYearActual + 1]),
    ).sort((a, b) => b - a);

    const cambiarAno = (nuevoAno: number) => {
        router.get(
            clienteShowRoute(cliente.id).url,
            { tax_year: nuevoAno },
            { preserveState: true, preserveScroll: true },
        );
    };

    const formaLabel = (forma: string) =>
        forma === TRANSVERSAL
            ? t('clienteShow.transversalLabel')
            : (formas.find((f) => f.forma === forma)?.forma_label ?? forma);

    // Agrupado por forma, preservando el orden por campo que ya trae el backend.
    const porForma = new Map<string, CampoCliente[]>();

    for (const campo of campos) {
        const lista = porForma.get(campo.forma);

        if (lista) {
            lista.push(campo);
        } else {
            porForma.set(campo.forma, [campo]);
        }
    }

    // Campos del catálogo que el cliente todavía no tiene cargados (ni
    // siquiera como fila pendiente): son el resto del total real de cada
    // forma, el que "recibidos" necesita para no medirse contra sí mismo.
    const faltantesPorForma = new Map<string, number>();

    for (const item of catalogoDisponible) {
        faltantesPorForma.set(
            item.forma,
            (faltantesPorForma.get(item.forma) ?? 0) + 1,
        );
    }

    // Los datos del cliente van primero: son su identidad (SSN, cónyuge,
    // dependientes) y no pertenecen a ninguna forma en particular. Después las
    // formas en el orden del backend, y al final cualquier forma con campos que
    // no esté declarada — no debería pasar, pero no la escondemos si pasa.
    const declaradas = [TRANSVERSAL, ...formas.map((f) => f.forma)];
    const secciones = [
        ...declaradas,
        ...[...porForma.keys()].filter((f) => !declaradas.includes(f)),
    ].filter((forma) => porForma.has(forma));

    return (
        <>
            <Head title={cliente.name} />

            <div className="space-y-8 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0">
                        <h1 className="font-display text-display text-foreground">
                            {cliente.name}
                        </h1>
                        {/* Correo y teléfono son identificadores, no prosa. */}
                        <p className="mt-1.5 font-mono text-xs text-muted-foreground">
                            {cliente.email}
                            {cliente.phone ? ` · ${cliente.phone}` : ''}
                        </p>
                    </div>
                    <div className="flex items-end gap-2">
                        <div className="grid gap-2">
                            <Label htmlFor="tax_year_selector">
                                {t('catalogo.form.taxYear')}
                            </Label>
                            <select
                                id="tax_year_selector"
                                className="rounded border bg-background p-2 text-sm"
                                value={taxYearActual}
                                onChange={(e) =>
                                    cambiarAno(Number(e.target.value))
                                }
                            >
                                {anosSeleccionables.map((ano) => (
                                    <option key={ano} value={ano}>
                                        {ano}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <a
                            href={
                                clienteExport(cliente.id).url +
                                `?tax_year=${taxYearActual}`
                            }
                        >
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

                <div className="flex justify-end">
                    <AgregarCampoDialog
                        clienteId={cliente.id}
                        taxYear={taxYearActual}
                        disponibles={catalogoDisponible}
                    />
                </div>

                {secciones.length === 0 ? (
                    <p className="border-t pt-6 text-sm text-muted-foreground">
                        {t('clienteShow.table.empty')}
                    </p>
                ) : (
                    <div className="space-y-10">
                        {secciones.map((forma) => (
                            <FormaSection
                                key={forma}
                                clienteId={cliente.id}
                                taxYear={taxYearActual}
                                forma={forma}
                                label={formaLabel(forma)}
                                info={formas.find((f) => f.forma === forma)}
                                campos={porForma.get(forma) ?? []}
                                faltantes={faltantesPorForma.get(forma) ?? 0}
                            />
                        ))}
                    </div>
                )}
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
