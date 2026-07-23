import { Form, Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import ClienteController from '@/actions/App/Http/Controllers/ClienteController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { index as clientesIndex, show as clienteShow } from '@/routes/clientes';
import type { Cliente, EstadoGeneral, FormaOption } from '@/types';

const ESTADO_LABEL: Record<EstadoGeneral, string> = {
    sin_iniciar: 'Sin iniciar',
    en_progreso: 'En progreso',
    completo: 'Completo',
};

const ESTADO_VARIANT: Record<
    EstadoGeneral,
    'outline' | 'secondary' | 'default'
> = {
    sin_iniciar: 'outline',
    en_progreso: 'secondary',
    completo: 'default',
};

const columns: ColumnDef<Cliente>[] = [
    {
        id: 'cliente',
        accessorFn: (c) => `${c.name} ${c.email} ${c.phone ?? ''}`,
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Cliente" />
        ),
        cell: ({ row }) => {
            const c = row.original;

            return (
                <div>
                    <Link
                        href={clienteShow(c.id)}
                        className="font-medium underline-offset-4 hover:underline"
                    >
                        {c.name}
                    </Link>
                    <div className="text-xs text-muted-foreground">
                        {c.email}
                        {c.phone ? ` · ${c.phone}` : ''}
                    </div>
                </div>
            );
        },
        enableHiding: false,
    },
    {
        accessorKey: 'estado_general',
        id: 'estado',
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Estado" />
        ),
        cell: ({ row }) => {
            const estado = row.original.estado_general;

            return (
                <Badge variant={ESTADO_VARIANT[estado]}>
                    {ESTADO_LABEL[estado]}
                </Badge>
            );
        },
        filterFn: (row, id, value) =>
            (value as string[]).includes(row.getValue<string>(id)),
    },
    {
        id: 'formas',
        accessorFn: (c) => c.formas.map((f) => f.forma),
        header: () => <span className="text-sm">Formas</span>,
        cell: ({ row }) => (
            <div className="flex flex-wrap gap-1">
                {row.original.formas.map((f) => (
                    <Badge key={f.forma} variant="outline">
                        {f.forma_label}
                    </Badge>
                ))}
            </div>
        ),
        filterFn: 'arrIncludesSome',
        enableSorting: false,
    },
];

function NuevoClienteDialog() {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button>Nuevo cliente</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Nuevo cliente</DialogTitle>
                <Form {...ClienteController.store.form()} resetOnSuccess>
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nombre</Label>
                                <Input id="name" name="name" required />
                                <InputError message={errors.name} />
                            </div>
                            <div className="mt-4 grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    required
                                />
                                <InputError message={errors.email} />
                            </div>
                            <div className="mt-4 grid gap-2">
                                <Label htmlFor="phone">
                                    Teléfono (opcional)
                                </Label>
                                <Input
                                    id="phone"
                                    name="phone"
                                    placeholder="+15551234567"
                                />
                                <InputError message={errors.phone} />
                            </div>
                            <DialogFooter className="mt-4">
                                <Button type="submit" disabled={processing}>
                                    Crear
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function ClientesIndex({
    clientes,
    formas,
}: {
    clientes: Cliente[];
    formas: FormaOption[];
}) {
    return (
        <>
            <Head title="Clientes" />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-1">
                    <h1 className="text-xl font-semibold">Clientes</h1>
                    <p className="text-sm text-muted-foreground">
                        Estado de recolección de datos por cliente, campo a
                        campo.
                    </p>
                </div>

                <DataTable
                    columns={columns}
                    data={clientes}
                    searchPlaceholder="Buscar por nombre, email o teléfono…"
                    emptyMessage="Todavía no hay clientes."
                    facetedFilters={[
                        {
                            columnId: 'estado',
                            title: 'Estado',
                            options: [
                                { label: 'Sin iniciar', value: 'sin_iniciar' },
                                { label: 'En progreso', value: 'en_progreso' },
                                { label: 'Completo', value: 'completo' },
                            ],
                        },
                        {
                            columnId: 'formas',
                            title: 'Forma',
                            options: formas.map((f) => ({
                                label: f.label,
                                value: f.value,
                            })),
                        },
                    ]}
                    toolbarActions={<NuevoClienteDialog />}
                />
            </div>
        </>
    );
}

ClientesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Clientes', href: clientesIndex() },
    ],
};
