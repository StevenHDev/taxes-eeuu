import { Plus, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

/**
 * Campos de los formularios de edición del catálogo fiscal (F1.5). Cada
 * uno lleva su etiqueta, su ayuda y su error enlazados al control
 * (aria-describedby, aria-invalid) para lectores de pantalla.
 */
function Campo({
    id,
    etiqueta,
    ayuda,
    error,
    children,
}: {
    id: string;
    etiqueta: string;
    ayuda?: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id}>{etiqueta}</Label>
            {children}
            {ayuda ? (
                <p id={`${id}-ayuda`} className="text-xs text-muted-foreground">
                    {ayuda}
                </p>
            ) : null}
            <InputError id={`${id}-error`} message={error} />
        </div>
    );
}

function describedBy(id: string, ayuda?: string, error?: string) {
    return (
        [ayuda ? `${id}-ayuda` : null, error ? `${id}-error` : null]
            .filter(Boolean)
            .join(' ') || undefined
    );
}

export function CampoTexto({
    id,
    etiqueta,
    valor,
    onCambio,
    error,
    ayuda,
    multilinea = false,
    tipo = 'text',
    requerido = false,
    placeholder,
}: {
    id: string;
    etiqueta: string;
    valor: string;
    onCambio: (valor: string) => void;
    error?: string;
    ayuda?: string;
    multilinea?: boolean;
    tipo?: 'text' | 'url' | 'date' | 'number';
    requerido?: boolean;
    placeholder?: string;
}) {
    const comunes = {
        id,
        value: valor,
        required: requerido,
        placeholder,
        'aria-invalid': error ? true : undefined,
        'aria-describedby': describedBy(id, ayuda, error),
    };

    return (
        <Campo id={id} etiqueta={etiqueta} ayuda={ayuda} error={error}>
            {multilinea ? (
                <Textarea
                    {...comunes}
                    rows={3}
                    onChange={(e) => onCambio(e.target.value)}
                />
            ) : (
                <Input
                    {...comunes}
                    type={tipo}
                    step={tipo === 'number' ? 'any' : undefined}
                    onChange={(e) => onCambio(e.target.value)}
                />
            )}
        </Campo>
    );
}

export function CampoSelect({
    id,
    etiqueta,
    valor,
    onCambio,
    opciones,
    error,
    ayuda,
}: {
    id: string;
    etiqueta: string;
    valor: string;
    onCambio: (valor: string) => void;
    opciones: { valor: string; texto: string }[];
    error?: string;
    ayuda?: string;
}) {
    return (
        <Campo id={id} etiqueta={etiqueta} ayuda={ayuda} error={error}>
            <Select value={valor} onValueChange={onCambio}>
                <SelectTrigger
                    id={id}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={describedBy(id, ayuda, error)}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {opciones.map((o) => (
                        <SelectItem key={o.valor} value={o.valor}>
                            {o.texto}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </Campo>
    );
}

export function CampoCasilla({
    id,
    etiqueta,
    marcado,
    onCambio,
    ayuda,
}: {
    id: string;
    etiqueta: string;
    marcado: boolean;
    onCambio: (marcado: boolean) => void;
    ayuda?: string;
}) {
    return (
        <div className="flex items-start gap-2">
            <Checkbox
                id={id}
                checked={marcado}
                onCheckedChange={(v) => onCambio(v === true)}
                aria-describedby={ayuda ? `${id}-ayuda` : undefined}
            />
            <div className="grid gap-0.5">
                <Label htmlFor={id} className="font-normal">
                    {etiqueta}
                </Label>
                {ayuda ? (
                    <p
                        id={`${id}-ayuda`}
                        className="text-xs text-muted-foreground"
                    >
                        {ayuda}
                    </p>
                ) : null}
            </div>
        </div>
    );
}

/**
 * Lista de filas editables (opciones de una pregunta, casillas de un
 * documento, líneas de un formulario, tramos de una tabla): agregar,
 * quitar y editar cada fila. Los errores de cada celda vienen como
 * `${nombre}.${i}.${campo}`.
 */
export function FilasEditables<T extends Record<string, unknown>>({
    nombre,
    titulo,
    filas,
    onCambio,
    nueva,
    columnas,
    errores,
    textoAgregar,
}: {
    nombre: string;
    titulo: string;
    filas: T[];
    onCambio: (filas: T[]) => void;
    nueva: () => T;
    columnas: {
        campo: keyof T & string;
        etiqueta: string;
        render: (
            valor: T[keyof T],
            cambiar: (v: T[keyof T]) => void,
            id: string,
            invalido: boolean,
        ) => ReactNode;
        ancho?: string;
    }[];
    errores: Record<string, string | undefined>;
    textoAgregar: string;
}) {
    const { t } = useTranslation();
    const errorGeneral = errores[nombre];

    const cambiar = (i: number, campo: keyof T, valor: T[keyof T]) =>
        onCambio(filas.map((f, j) => (j === i ? { ...f, [campo]: valor } : f)));

    return (
        <fieldset className="grid gap-2">
            <legend className="mb-1 text-sm font-medium">{titulo}</legend>
            {filas.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('catalogoFiscal.edicion.sinFilas')}
                </p>
            ) : null}
            <ul className="grid gap-3">
                {filas.map((fila, i) => (
                    <li
                        key={i}
                        className="grid gap-2 rounded-lg border border-border/60 p-3 sm:grid-cols-[repeat(auto-fit,minmax(8rem,1fr))_auto] sm:items-end"
                    >
                        {columnas.map((c) => {
                            const id = `${nombre}-${i}-${c.campo}`;
                            const error = errores[`${nombre}.${i}.${c.campo}`];

                            return (
                                <div
                                    key={c.campo}
                                    className={`grid gap-1 ${c.ancho ?? ''}`}
                                >
                                    <Label htmlFor={id} className="text-xs">
                                        {c.etiqueta}
                                    </Label>
                                    {c.render(
                                        fila[c.campo],
                                        (v) => cambiar(i, c.campo, v),
                                        id,
                                        Boolean(error),
                                    )}
                                    <InputError
                                        message={error}
                                        className="text-xs"
                                    />
                                </div>
                            );
                        })}
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() =>
                                onCambio(filas.filter((_, j) => j !== i))
                            }
                            aria-label={t('catalogoFiscal.edicion.quitarFila', {
                                numero: i + 1,
                            })}
                        >
                            <Trash2 className="size-4" aria-hidden="true" />
                        </Button>
                    </li>
                ))}
            </ul>
            <InputError message={errorGeneral} />
            <div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => onCambio([...filas, nueva()])}
                >
                    <Plus className="size-4" aria-hidden="true" />
                    {textoAgregar}
                </Button>
            </div>
        </fieldset>
    );
}
