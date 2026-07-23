import { Head, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useState } from 'react';
import CatalogoController from '@/actions/App/Http/Controllers/CatalogoController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DataTable } from '@/components/ui/data-table';
import { DataTableColumnHeader } from '@/components/ui/data-table-column-header';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { index as catalogoIndex } from '@/routes/catalogo';
import type { CampoCatalogo, FormaOption } from '@/types';

type Errors = Partial<
    Record<
        | 'clave'
        | 'tipo_campo'
        | 'tipo_dato'
        | 'formatos_aceptados'
        | 'subcampos'
        | 'obligatorio'
        | 'sensible',
        string
    >
>;

function CampoForm({
    forma,
    campo,
    onDone,
}: {
    forma: string;
    campo?: CampoCatalogo;
    onDone: () => void;
}) {
    const [clave, setClave] = useState(campo?.clave ?? '');
    const [tipoCampo, setTipoCampo] = useState(campo?.tipo_campo ?? 'dato');
    const [tipoDato, setTipoDato] = useState(campo?.tipo_dato ?? 'string');
    const [formatos, setFormatos] = useState(
        campo?.formatos_aceptados?.join(', ') ?? '',
    );
    const [subcampos, setSubcampos] = useState(
        campo?.subcampos?.join(', ') ?? '',
    );
    const [obligatorio, setObligatorio] = useState(campo?.obligatorio ?? true);
    const [sensible, setSensible] = useState(campo?.sensible ?? false);
    const [errors, setErrors] = useState<Errors>({});
    const [processing, setProcessing] = useState(false);

    const submit = () => {
        setProcessing(true);

        const payload = {
            forma,
            clave,
            tipo_campo: tipoCampo,
            tipo_dato: tipoCampo === 'documento' ? null : tipoDato,
            formatos_aceptados:
                tipoCampo === 'dato'
                    ? null
                    : formatos
                          .split(',')
                          .map((f) => f.trim())
                          .filter(Boolean),
            subcampos: subcampos
                .split(',')
                .map((s) => s.trim())
                .filter(Boolean),
            obligatorio,
            sensible,
        };

        const options = {
            onError: (e: Errors) => setErrors(e),
            onSuccess: () => onDone(),
            onFinish: () => setProcessing(false),
        };

        if (campo) {
            router.patch(
                CatalogoController.update(campo.id).url,
                payload,
                options,
            );
        } else {
            router.post(CatalogoController.store().url, payload, options);
        }
    };

    return (
        <div className="space-y-4">
            <div className="grid gap-2">
                <Label htmlFor="clave">Clave del campo</Label>
                <Input
                    id="clave"
                    value={clave}
                    onChange={(e) => setClave(e.target.value)}
                    placeholder="ej. ingresos_negocio"
                />
                <InputError message={errors.clave} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="tipo_campo">Tipo de campo</Label>
                <select
                    id="tipo_campo"
                    className="rounded border bg-background p-2 text-sm"
                    value={tipoCampo}
                    onChange={(e) =>
                        setTipoCampo(
                            e.target.value as CampoCatalogo['tipo_campo'],
                        )
                    }
                >
                    <option value="documento">documento (solo archivo)</option>
                    <option value="dato">dato (solo texto/número)</option>
                    <option value="mixto">mixto (archivo o texto)</option>
                </select>
                <InputError message={errors.tipo_campo} />
            </div>

            {tipoCampo !== 'documento' && (
                <div className="grid gap-2">
                    <Label htmlFor="tipo_dato">Tipo de dato</Label>
                    <select
                        id="tipo_dato"
                        className="rounded border bg-background p-2 text-sm"
                        value={tipoDato ?? 'string'}
                        onChange={(e) =>
                            setTipoDato(
                                e.target.value as NonNullable<
                                    CampoCatalogo['tipo_dato']
                                >,
                            )
                        }
                    >
                        <option value="string">string</option>
                        <option value="number">number</option>
                        <option value="object">object</option>
                        <option value="array_string">array_string</option>
                        <option value="array_object">array_object</option>
                    </select>
                    <InputError message={errors.tipo_dato} />
                </div>
            )}

            {tipoCampo !== 'dato' && (
                <div className="grid gap-2">
                    <Label htmlFor="formatos">
                        Formatos aceptados (separados por coma)
                    </Label>
                    <Input
                        id="formatos"
                        value={formatos}
                        onChange={(e) => setFormatos(e.target.value)}
                        placeholder="pdf, jpg, png"
                    />
                    <InputError message={errors.formatos_aceptados} />
                </div>
            )}

            <div className="grid gap-2">
                <Label htmlFor="subcampos">
                    Subcampos (opcional, para object/array_object)
                </Label>
                <Input
                    id="subcampos"
                    value={subcampos}
                    onChange={(e) => setSubcampos(e.target.value)}
                    placeholder="nombre_completo, fecha_nacimiento, ssn"
                />
            </div>

            <div className="flex items-center gap-2">
                <Checkbox
                    id="obligatorio"
                    checked={obligatorio}
                    onCheckedChange={(v) => setObligatorio(v === true)}
                />
                <Label htmlFor="obligatorio">
                    Obligatorio para completar la forma
                </Label>
            </div>

            <div className="flex items-center gap-2">
                <Checkbox
                    id="sensible"
                    checked={sensible}
                    onCheckedChange={(v) => setSensible(v === true)}
                />
                <Label htmlFor="sensible">
                    Sensible (se cifra y se enmascara)
                </Label>
            </div>

            <DialogFooter>
                <Button onClick={submit} disabled={processing}>
                    Guardar
                </Button>
            </DialogFooter>
        </div>
    );
}

function CampoRowActions({ campo }: { campo: CampoCatalogo }) {
    const [editar, setEditar] = useState(false);

    return (
        <div className="flex justify-end gap-1">
            <Dialog open={editar} onOpenChange={setEditar}>
                <DialogTrigger asChild>
                    <Button variant="ghost" size="sm">
                        Editar
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogTitle>Editar «{campo.clave}»</DialogTitle>
                    <CampoForm
                        forma={campo.forma}
                        campo={campo}
                        onDone={() => setEditar(false)}
                    />
                </DialogContent>
            </Dialog>
            <Button
                variant="ghost"
                size="sm"
                className="text-destructive hover:text-destructive"
                onClick={() => {
                    if (
                        confirm(
                            `¿Eliminar «${campo.clave}»? Los datos ya cargados de clientes no se borran.`,
                        )
                    ) {
                        router.delete(CatalogoController.destroy(campo.id).url);
                    }
                }}
            >
                Eliminar
            </Button>
        </div>
    );
}

function NuevoCampoDialog({ formas }: { formas: FormaOption[] }) {
    const [open, setOpen] = useState(false);
    const [forma, setForma] = useState<string>(String(formas[0]?.value ?? ''));

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>Nuevo campo</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Nuevo campo</DialogTitle>
                <div className="grid gap-2">
                    <Label htmlFor="forma_nuevo">Forma</Label>
                    <select
                        id="forma_nuevo"
                        className="rounded border bg-background p-2 text-sm"
                        value={forma}
                        onChange={(e) => setForma(e.target.value)}
                    >
                        {formas.map((f) => (
                            <option key={f.value} value={f.value}>
                                {f.label}
                            </option>
                        ))}
                    </select>
                </div>
                <CampoForm forma={forma} onDone={() => setOpen(false)} />
            </DialogContent>
        </Dialog>
    );
}

function buildColumns(
    formaLabel: (value: string) => string,
): ColumnDef<CampoCatalogo>[] {
    return [
        {
            accessorKey: 'clave',
            id: 'clave',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Clave" />
            ),
            cell: ({ row }) => (
                <span className="font-medium text-foreground">
                    {row.original.clave}
                </span>
            ),
            enableHiding: false,
        },
        {
            accessorKey: 'forma',
            id: 'forma',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Forma" />
            ),
            cell: ({ row }) => (
                <span className="text-sm text-muted-foreground">
                    {formaLabel(row.original.forma)}
                </span>
            ),
            filterFn: (row, id, value) =>
                (value as string[]).includes(row.getValue<string>(id)),
        },
        {
            accessorKey: 'tipo_campo',
            id: 'tipo',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Tipo" />
            ),
            cell: ({ row }) => {
                const c = row.original;

                return (
                    <span className="text-xs text-muted-foreground">
                        {c.tipo_campo}
                        {c.tipo_dato ? ` · ${c.tipo_dato}` : ''}
                        {c.formatos_aceptados?.length
                            ? ` · ${c.formatos_aceptados.join('/')}`
                            : ''}
                    </span>
                );
            },
            filterFn: (row, id, value) =>
                (value as string[]).includes(row.getValue<string>(id)),
        },
        {
            id: 'obligatorio',
            accessorFn: (c) => (c.obligatorio ? 'si' : 'no'),
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Obligatorio" />
            ),
            cell: ({ row }) =>
                row.original.obligatorio ? (
                    <Badge variant="default">Sí</Badge>
                ) : (
                    <Badge variant="outline">No</Badge>
                ),
            filterFn: (row, id, value) =>
                (value as string[]).includes(row.getValue<string>(id)),
        },
        {
            id: 'sensible',
            accessorFn: (c) => (c.sensible ? 'si' : 'no'),
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Sensible" />
            ),
            cell: ({ row }) =>
                row.original.sensible ? (
                    <Badge variant="destructive">Sensible</Badge>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
            filterFn: (row, id, value) =>
                (value as string[]).includes(row.getValue<string>(id)),
        },
        {
            id: 'acciones',
            header: () => <span className="sr-only">Acciones</span>,
            cell: ({ row }) => <CampoRowActions campo={row.original} />,
            enableHiding: false,
            enableSorting: false,
        },
    ];
}

export default function CatalogoIndex({
    formas,
    campos,
}: {
    formas: FormaOption[];
    campos: CampoCatalogo[];
}) {
    const formaLabel = (value: string) =>
        formas.find((f) => String(f.value) === value)?.label ?? value;

    const columns = buildColumns(formaLabel);

    return (
        <>
            <Head title="Catálogo" />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-1">
                    <h1 className="text-xl font-semibold">Catálogo de campos</h1>
                    <p className="text-sm text-muted-foreground">
                        Qué campos pide cada formulario. Eliminar una definición
                        no borra los datos ya cargados de clientes — solo deja de
                        pedirse y de contar para la completitud.
                    </p>
                </div>

                <DataTable
                    columns={columns}
                    data={campos}
                    searchPlaceholder="Buscar por clave…"
                    emptyMessage="Sin campos definidos."
                    initialPageSize={20}
                    facetedFilters={[
                        {
                            columnId: 'forma',
                            title: 'Forma',
                            options: formas.map((f) => ({
                                label: f.label,
                                value: String(f.value),
                            })),
                        },
                        {
                            columnId: 'tipo',
                            title: 'Tipo',
                            options: [
                                { label: 'documento', value: 'documento' },
                                { label: 'dato', value: 'dato' },
                                { label: 'mixto', value: 'mixto' },
                            ],
                        },
                        {
                            columnId: 'obligatorio',
                            title: 'Obligatorio',
                            options: [
                                { label: 'Sí', value: 'si' },
                                { label: 'No', value: 'no' },
                            ],
                        },
                        {
                            columnId: 'sensible',
                            title: 'Sensible',
                            options: [
                                { label: 'Sensible', value: 'si' },
                                { label: 'No sensible', value: 'no' },
                            ],
                        },
                    ]}
                    toolbarActions={<NuevoCampoDialog formas={formas} />}
                />
            </div>
        </>
    );
}

CatalogoIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Catálogo', href: catalogoIndex() },
    ],
};
