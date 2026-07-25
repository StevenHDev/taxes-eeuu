import { Form, Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useTranslation } from 'react-i18next';
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

const ESTADO_LABEL_KEY: Record<EstadoGeneral, string> = {
    sin_iniciar: 'clientesIndex.estado.sinIniciar',
    en_progreso: 'clientesIndex.estado.enProgreso',
    completo: 'clientesIndex.estado.completo',
};

const ESTADO_VARIANT: Record<
    EstadoGeneral,
    'outline' | 'secondary' | 'default'
> = {
    sin_iniciar: 'outline',
    en_progreso: 'secondary',
    completo: 'default',
};

function useColumns(): ColumnDef<Cliente>[] {
    const { t } = useTranslation();

    return [
        {
            id: 'cliente',
            accessorFn: (c) => `${c.name} ${c.email} ${c.phone ?? ''}`,
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title={t('clientesIndex.columns.client')}
                />
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
                <DataTableColumnHeader
                    column={column}
                    title={t('clientesIndex.columns.status')}
                />
            ),
            cell: ({ row }) => {
                const estado = row.original.estado_general;

                return (
                    <Badge variant={ESTADO_VARIANT[estado]}>
                        {t(ESTADO_LABEL_KEY[estado])}
                    </Badge>
                );
            },
            filterFn: (row, id, value) =>
                (value as string[]).includes(row.getValue<string>(id)),
        },
        {
            id: 'formas',
            accessorFn: (c) => c.formas.map((f) => f.forma),
            header: () => (
                <span className="text-sm">
                    {t('clientesIndex.columns.forms')}
                </span>
            ),
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
}

function NuevoClienteDialog() {
    const { t } = useTranslation();

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button>{t('clientesIndex.newClient.trigger')}</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>{t('clientesIndex.newClient.title')}</DialogTitle>
                <Form {...ClienteController.store.form()} resetOnSuccess>
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">
                                    {t('clientesIndex.newClient.nameLabel')}
                                </Label>
                                <Input id="name" name="name" required />
                                <InputError message={errors.name} />
                            </div>
                            <div className="mt-4 grid gap-2">
                                <Label htmlFor="email">
                                    {t('clientesIndex.newClient.emailLabel')}
                                </Label>
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
                                    {t('clientesIndex.newClient.phoneLabel')}
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
                                    {t('clientesIndex.newClient.submit')}
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
    const { t } = useTranslation();
    const columns = useColumns();

    return (
        <>
            <Head title={t('clientesIndex.head.title')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-1">
                    <h1 className="text-xl font-semibold">
                        {t('clientesIndex.heading')}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {t('clientesIndex.subheading')}
                    </p>
                </div>

                <DataTable
                    columns={columns}
                    data={clientes}
                    searchPlaceholder={t('clientesIndex.searchPlaceholder')}
                    emptyMessage={t('clientesIndex.emptyMessage')}
                    facetedFilters={[
                        {
                            columnId: 'estado',
                            title: t('clientesIndex.columns.status'),
                            options: [
                                {
                                    label: t('clientesIndex.estado.sinIniciar'),
                                    value: 'sin_iniciar',
                                },
                                {
                                    label: t('clientesIndex.estado.enProgreso'),
                                    value: 'en_progreso',
                                },
                                {
                                    label: t('clientesIndex.estado.completo'),
                                    value: 'completo',
                                },
                            ],
                        },
                        {
                            columnId: 'formas',
                            title: t('clientesIndex.filters.form'),
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
        { title: 'nav.dashboard', href: dashboard() },
        { title: 'nav.clients', href: clientesIndex() },
    ],
};
