import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
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
import { Textarea } from '@/components/ui/textarea';
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
    if (typeof valor === 'number') return 'number';
    if (Array.isArray(valor)) {
        return valor.every((v) => typeof v === 'string')
            ? 'array_string'
            : 'array_object';
    }
    if (valor !== null && typeof valor === 'object') return 'object';
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

function EditCampoDialog({
    clienteId,
    campo,
}: {
    clienteId: number;
    campo: CampoCliente;
}) {
    const [raw, setRaw] = useState(
        campo.valor !== null && campo.valor !== undefined
            ? JSON.stringify(campo.valor)
            : '',
    );
    const [file, setFile] = useState<File | null>(null);
    const esArchivo = campo.tipo_campo === 'documento';

    const submit = () => {
        const contenido = parseContenido(raw);

        router.patch(
            campoUpdate({ cliente: clienteId, campo: campo.campo }).url +
                `?forma=${campo.forma}`,
            esArchivo
                ? { modo: 'archivo', file }
                : {
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
                    Corregir
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Corregir «{campo.campo}»</DialogTitle>
                <DialogDescription>
                    Forma: {campo.forma}. Este cambio queda registrado en el
                    historial.
                </DialogDescription>

                {esArchivo ? (
                    <input
                        type="file"
                        onChange={(e) =>
                            setFile(e.target.files?.[0] ?? null)
                        }
                    />
                ) : (
                    <Textarea
                        value={raw}
                        onChange={(e) => setRaw(e.target.value)}
                        placeholder='Texto, número, o JSON para objetos/listas (ej. {"nombre_completo":"..."})'
                        rows={4}
                    />
                )}

                <DialogFooter>
                    <Button onClick={submit}>Guardar corrección</Button>
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
    const [forma, setForma] = useState<string>(
        disponibles[0]?.forma ?? '',
    );
    const camposDeForma = disponibles.filter((d) => d.forma === forma);
    const [campo, setCampo] = useState(camposDeForma[0]?.campo ?? '');
    const [raw, setRaw] = useState('');
    const [file, setFile] = useState<File | null>(null);

    const tipoCampo = disponibles.find(
        (d) => d.forma === forma && d.campo === campo,
    )?.tipo_campo;
    const esArchivo = tipoCampo === 'documento';

    if (disponibles.length === 0) {
        return null;
    }

    const cambiarForma = (nuevaForma: string) => {
        setForma(nuevaForma);
        const primero = disponibles.find((d) => d.forma === nuevaForma);
        setCampo(primero?.campo ?? '');
    };

    const submit = () => {
        const contenido = parseContenido(raw);

        router.patch(
            campoUpdate({ cliente: clienteId, campo }).url +
                `?forma=${forma}`,
            esArchivo
                ? { modo: 'archivo', file }
                : {
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
                <Button variant="secondary">Agregar campo</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Agregar campo</DialogTitle>
                <DialogDescription>
                    Carga a mano un campo que el agente todavía no envió.
                </DialogDescription>

                <div className="grid gap-2">
                    <label className="text-sm font-medium" htmlFor="forma">
                        Forma
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
                        Campo
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
                        <input
                            type="file"
                            onChange={(e) =>
                                setFile(e.target.files?.[0] ?? null)
                            }
                        />
                    ) : (
                        <Textarea
                            value={raw}
                            onChange={(e) => setRaw(e.target.value)}
                            placeholder='Texto, número, o JSON para objetos/listas (ej. {"nombre_completo":"..."})'
                            rows={4}
                        />
                    )}
                </div>

                <DialogFooter className="mt-4">
                    <Button onClick={submit}>Guardar</Button>
                </DialogFooter>
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
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm" className="text-red-600">
                    Eliminar
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>¿Eliminar «{campo.campo}»?</DialogTitle>
                <DialogDescription>
                    Se borra el valor cargado (y el archivo, si tenía). Queda
                    registrado en el historial.
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
                        Eliminar
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
                    Historial
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Historial de «{campo.campo}»</DialogTitle>
                <div className="max-h-80 space-y-3 overflow-y-auto text-sm">
                    {items === null && <p>Cargando…</p>}
                    {items?.length === 0 && (
                        <p className="text-muted-foreground">
                            Sin cambios registrados todavía.
                        </p>
                    )}
                    {items?.map((h, i) => (
                        <div key={i} className="rounded border p-2">
                            <div className="text-xs text-muted-foreground">
                                {h.created_at} · {h.source}
                                {h.modificado_por ? ` · ${h.modificado_por}` : ''}
                            </div>
                            <div className="mt-1 grid grid-cols-2 gap-2 text-xs">
                                <div>
                                    <span className="font-medium">Antes:</span>{' '}
                                    {JSON.stringify(h.valor_anterior)}
                                </div>
                                <div>
                                    <span className="font-medium">
                                        Después:
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

    if (needsPassword) {
        return (
            <a
                href={confirmPasswordShow().url}
                className="text-xs text-amber-600 underline"
            >
                Confirma tu contraseña para revelar
            </a>
        );
    }

    if (valor !== null) {
        return <code className="text-xs">{JSON.stringify(valor)}</code>;
    }

    return (
        <Button variant="ghost" size="sm" onClick={reveal}>
            Revelar
        </Button>
    );
}

export default function ClienteShow({
    cliente,
    formas,
    campos,
    catalogoDisponible,
}: {
    cliente: { id: number; name: string; email: string };
    formas: ClienteForma[];
    campos: CampoCliente[];
    catalogoDisponible: CatalogoDisponibleItem[];
}) {
    const { auth } = usePage<PageProps>().props;
    const esAdministrador = auth.user.role === 'administrator';

    return (
        <>
            <Head title={cliente.name} />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            {cliente.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {cliente.email}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <a href={clienteExport(cliente.id).url}>
                            <Button variant="secondary">Exportar ZIP</Button>
                        </a>
                        {esAdministrador && (
                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button variant="destructive">
                                        Eliminar cliente
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogTitle>
                                        ¿Eliminar a {cliente.name}?
                                    </DialogTitle>
                                    <DialogDescription>
                                        Se borran todos sus campos,
                                        documentos e historial. Esta acción no
                                        se puede deshacer.
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
                                            Eliminar definitivamente
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
                                    ? 'Completo'
                                    : 'En progreso'}
                            </Badge>
                            {f.revisado_en ? (
                                <Badge variant="outline">Revisado</Badge>
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
                                    Marcar revisado
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

                <div className="overflow-hidden rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Forma</TableHead>
                                <TableHead>Campo</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead>Valor</TableHead>
                                <TableHead className="text-right">
                                    Acciones
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
                                            {campo.estado}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="max-w-xs truncate text-sm">
                                        {campo.documento ? (
                                            campo.documento.download_url ? (
                                                <a
                                                    href={
                                                        campo.documento
                                                            .download_url
                                                    }
                                                    className="underline"
                                                >
                                                    {
                                                        campo.documento
                                                            .file_original_name
                                                    }
                                                </a>
                                            ) : (
                                                campo.documento
                                                    .file_original_name
                                            )
                                        ) : campo.es_sensible ? (
                                            <RevealButton
                                                clienteId={cliente.id}
                                                campo={campo}
                                            />
                                        ) : (
                                            JSON.stringify(campo.valor)
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <HistorialDialog
                                            clienteId={cliente.id}
                                            campo={campo}
                                        />
                                        <EditCampoDialog
                                            clienteId={cliente.id}
                                            campo={campo}
                                        />
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
                                        Todavía no se ha recolectado ningún
                                        campo.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

ClienteShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Clientes', href: clientesIndex() },
    ],
};
