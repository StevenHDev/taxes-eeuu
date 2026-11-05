import { Form, Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { MessageCircle, TriangleAlert, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import ClienteController from '@/actions/App/Http/Controllers/ClienteController';
import { EstadoExpedienteBadge } from '@/components/ficha/estado-expediente';
import InputError from '@/components/input-error';
import { fechaCorta } from '@/components/panel/formato';
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
import { WhatsappConversationDialog } from '@/components/whatsapp-conversation-dialog';
import { dashboard } from '@/routes';
import { index as clientesIndex, show as clienteShow } from '@/routes/clientes';
import type { Turno } from '@/types/expediente';
import type { ClienteLista, OpcionLista } from '@/types/panel';

const TURNOS: Turno[] = ['preparador', 'cliente', 'irs', 'nadie'];
const CANALES = ['whatsapp', 'portal', 'preparador', 'migracion'] as const;

// Filtro "incluye alguno" para valores simples (arrIncludesSome haría una
// búsqueda por texto parcial en cadenas).
const incluyeValor = (
    row: { getValue: <T>(id: string) => T },
    id: string,
    value: unknown,
) => (value as string[]).includes(row.getValue<string>(id));

const incluyeAlguno = (
    row: { getValue: <T>(id: string) => T },
    id: string,
    value: unknown,
) => {
    const propios = new Set(row.getValue<string[]>(id));

    return (value as string[]).some((v) => propios.has(v));
};

function useColumns({
    estados,
    perfiles,
}: {
    estados: OpcionLista[];
    perfiles: OpcionLista[];
}): ColumnDef<ClienteLista>[] {
    const { t, i18n } = useTranslation();
    const ordenEstado = new Map(estados.map((e, i) => [e.valor, i]));
    const etiquetaPerfil = new Map(perfiles.map((p) => [p.valor, p.label]));

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
                    <div className="min-w-44">
                        <Link
                            href={clienteShow(c.id)}
                            className="inline-flex min-h-11 items-center font-medium underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            {c.name}
                        </Link>
                        {c.es_prueba ? (
                            <Badge variant="outline" className="ml-2">
                                {t('cuentaUsuario.badgePrueba')}
                            </Badge>
                        ) : null}
                        <div className="-mt-2 text-xs text-muted-foreground">
                            {c.email}
                            {c.phone ? ` · ${c.phone}` : ''}
                        </div>
                    </div>
                );
            },
            enableHiding: false,
        },
        {
            id: 'estado',
            accessorFn: (c) => c.expediente.estado,
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title={t('panel.lista.columns.estado')}
                />
            ),
            cell: ({ row }) => {
                const e = row.original.expediente;

                return (
                    <div className="flex min-w-36 flex-col items-start gap-1">
                        <EstadoExpedienteBadge
                            estado={e.estado}
                            label={e.label}
                            turno={e.turno}
                        />
                        {e.desde ? (
                            <span className="text-xs text-muted-foreground">
                                {t('panel.lista.desde', {
                                    fecha: fechaCorta(e.desde, i18n.language),
                                })}
                            </span>
                        ) : null}
                    </div>
                );
            },
            sortingFn: (a, b) =>
                (ordenEstado.get(a.original.expediente.estado) ?? 0) -
                (ordenEstado.get(b.original.expediente.estado) ?? 0),
            filterFn: incluyeValor,
        },
        {
            id: 'leToca',
            accessorFn: (c) => c.expediente.turno,
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title={t('panel.lista.columns.leToca')}
                />
            ),
            cell: ({ row }) => {
                const turno = row.original.expediente.turno;

                return (
                    <Badge
                        variant={turno === 'preparador' ? 'default' : 'outline'}
                        className="whitespace-nowrap"
                    >
                        {t(`panel.lista.turno.${turno}`)}
                    </Badge>
                );
            },
            filterFn: incluyeValor,
        },
        {
            id: 'vence',
            accessorFn: (c) => c.expediente.vence_en ?? '9999-12-31',
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title={t('panel.lista.columns.vence')}
                />
            ),
            cell: ({ row }) => {
                const vence = row.original.expediente.vence_en;

                return vence ? (
                    <span className="text-sm whitespace-nowrap tabular-nums">
                        {fechaCorta(vence, i18n.language)}
                    </span>
                ) : (
                    <span aria-label={t('panel.lista.sinVencimiento')}>—</span>
                );
            },
        },
        {
            id: 'perfil',
            accessorFn: (c) => c.perfil,
            header: () => (
                <span className="text-sm">
                    {t('panel.lista.columns.perfil')}
                </span>
            ),
            cell: ({ row }) =>
                row.original.perfil.length === 0 ? (
                    <span aria-label={t('panel.lista.sinPerfil')}>—</span>
                ) : (
                    <div className="flex min-w-32 flex-wrap gap-1">
                        {row.original.perfil.map((p) => (
                            <Badge key={p} variant="outline">
                                {etiquetaPerfil.get(p) ?? p}
                            </Badge>
                        ))}
                    </div>
                ),
            filterFn: incluyeAlguno,
            enableSorting: false,
        },
        {
            id: 'excepciones',
            accessorFn: (c) => (c.excepciones.total > 0 ? 'con' : 'sin'),
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title={t('panel.lista.columns.excepciones')}
                />
            ),
            cell: ({ row }) => {
                const { total, bloqueantes } = row.original.excepciones;

                if (total === 0) {
                    return (
                        <span aria-label={t('panel.lista.sinExcepciones')}>
                            —
                        </span>
                    );
                }

                return (
                    <div className="flex flex-col items-start gap-0.5 text-sm">
                        <span className="tabular-nums">
                            {t('panel.lista.excepcionesTotal', {
                                count: total,
                            })}
                        </span>
                        {bloqueantes > 0 ? (
                            <span className="flex items-center gap-1 text-xs font-medium text-destructive">
                                <TriangleAlert
                                    aria-hidden="true"
                                    className="size-3.5"
                                />
                                {t('panel.lista.bloqueantes', {
                                    count: bloqueantes,
                                })}
                            </span>
                        ) : null}
                    </div>
                );
            },
            sortingFn: (a, b) =>
                a.original.excepciones.bloqueantes -
                    b.original.excepciones.bloqueantes ||
                a.original.excepciones.total - b.original.excepciones.total,
            filterFn: incluyeValor,
        },
        {
            id: 'documentos',
            accessorFn: (c) => c.documentos?.faltan ?? -1,
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title={t('clientesIndex.columns.documents')}
                />
            ),
            cell: ({ row }) => {
                const resumen = row.original.documentos;

                if (resumen === null) {
                    return (
                        <span aria-label={t('clientesIndex.documentos.sin')}>
                            —
                        </span>
                    );
                }

                if (resumen.completo) {
                    return (
                        <Badge>{t('clientesIndex.documentos.completo')}</Badge>
                    );
                }

                return (
                    <span className="text-sm">
                        {t('clientesIndex.documentos.faltan', {
                            count: resumen.faltan,
                        })}
                        {resumen.por_revisar > 0
                            ? ` · ${t('clientesIndex.documentos.porRevisar', { count: resumen.por_revisar })}`
                            : ''}
                    </span>
                );
            },
        },
        {
            id: 'canal',
            accessorFn: (c) => c.canal_inicio ?? '',
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title={t('panel.lista.columns.canal')}
                />
            ),
            cell: ({ row }) => {
                const c = row.original;

                return (
                    <div className="flex items-center gap-1">
                        <span className="text-sm whitespace-nowrap">
                            {c.canal_inicio
                                ? t(`panel.lista.canal.${c.canal_inicio}`)
                                : '—'}
                        </span>
                        {c.phone ? (
                            <WhatsappConversationDialog
                                clienteId={c.id}
                                clienteName={c.name}
                                trigger={
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-11"
                                        aria-label={t(
                                            'clientesIndex.whatsapp.trigger',
                                        )}
                                        title={t(
                                            'clientesIndex.whatsapp.trigger',
                                        )}
                                    >
                                        <MessageCircle className="size-4 text-green-600 dark:text-green-500" />
                                    </Button>
                                }
                            />
                        ) : null}
                    </div>
                );
            },
            filterFn: incluyeValor,
            enableHiding: false,
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

function ordenInicial(clientes: ClienteLista[]): ClienteLista[] {
    // Le toca al preparador primero; después el vencimiento más cercano.
    return [...clientes].sort((a, b) => {
        const turno =
            Number(b.expediente.turno === 'preparador') -
            Number(a.expediente.turno === 'preparador');

        if (turno !== 0) {
            return turno;
        }

        return (
            (a.expediente.vence_en ?? '9999-12-31').localeCompare(
                b.expediente.vence_en ?? '9999-12-31',
            ) || a.name.localeCompare(b.name)
        );
    });
}

function ListaClientes({
    clientes,
    estados,
    perfiles,
    filtroEstado,
}: {
    clientes: ClienteLista[];
    estados: OpcionLista[];
    perfiles: OpcionLista[];
    filtroEstado: string | null;
}) {
    const { t } = useTranslation();
    const columns = useColumns({ estados, perfiles });
    // Estado inicial perezoso: viene de ?estado= (tarjetas del panel).
    const [estadoActivo, setEstadoActivo] = useState<string | null>(
        () => filtroEstado,
    );
    const ordenados = useMemo(() => ordenInicial(clientes), [clientes]);
    const visibles = useMemo(
        () =>
            estadoActivo === null
                ? ordenados
                : ordenados.filter((c) => c.expediente.estado === estadoActivo),
        [ordenados, estadoActivo],
    );
    const etiquetaActiva =
        estadoActivo === null
            ? null
            : (estados.find((e) => e.valor === estadoActivo)?.label ??
              estadoActivo);

    const quitarEstado = () => {
        setEstadoActivo(null);

        // Deja la URL sin ?estado= sin recargar los datos.
        router.get(
            clientesIndex().url,
            {},
            {
                preserveScroll: true,
                replace: true,
            },
        );
    };

    return (
        <>
            <Head title={t('clientesIndex.head.title')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-1">
                    <h1 className="text-xl font-semibold">
                        {t('clientesIndex.heading')}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {t('panel.lista.subtitulo')}
                    </p>
                </div>

                {etiquetaActiva !== null ? (
                    <div
                        role="status"
                        className="flex items-center justify-between gap-3 rounded-lg border bg-muted/40 px-3 py-2 text-sm"
                    >
                        <span>
                            {t('panel.lista.filtroEstado', {
                                estado: etiquetaActiva,
                            })}
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="min-h-11"
                            onClick={quitarEstado}
                        >
                            <X aria-hidden="true" className="size-4" />
                            {t('panel.lista.quitarFiltro')}
                        </Button>
                    </div>
                ) : null}

                <DataTable
                    columns={columns}
                    data={visibles}
                    searchPlaceholder={t('clientesIndex.searchPlaceholder')}
                    emptyMessage={
                        estadoActivo === null
                            ? t('clientesIndex.emptyMessage')
                            : t('panel.lista.sinResultadosEstado')
                    }
                    facetedFilters={[
                        {
                            columnId: 'estado',
                            title: t('panel.lista.columns.estado'),
                            options: estados.map((e) => ({
                                label: e.label,
                                value: e.valor,
                            })),
                        },
                        {
                            columnId: 'leToca',
                            title: t('panel.lista.columns.leToca'),
                            options: TURNOS.map((turno) => ({
                                label: t(`panel.lista.turno.${turno}`),
                                value: turno,
                            })),
                        },
                        {
                            columnId: 'perfil',
                            title: t('panel.lista.columns.perfil'),
                            options: perfiles.map((p) => ({
                                label: p.label,
                                value: p.valor,
                            })),
                        },
                        {
                            columnId: 'excepciones',
                            title: t('panel.lista.columns.excepciones'),
                            options: [
                                {
                                    label: t('panel.lista.conExcepciones'),
                                    value: 'con',
                                },
                            ],
                        },
                        {
                            columnId: 'canal',
                            title: t('panel.lista.columns.canal'),
                            options: CANALES.map((c) => ({
                                label: t(`panel.lista.canal.${c}`),
                                value: c,
                            })),
                        },
                    ]}
                    toolbarActions={<NuevoClienteDialog />}
                />
            </div>
        </>
    );
}

export default function ClientesIndex(props: {
    clientes: ClienteLista[];
    estados: OpcionLista[];
    perfiles: OpcionLista[];
    filtroEstado: string | null;
}) {
    // Otra visita con otro ?estado= reinicia el estado inicial de la lista.
    return <ListaClientes key={props.filtroEstado ?? 'todos'} {...props} />;
}

ClientesIndex.layout = {
    breadcrumbs: [
        { title: 'nav.dashboard', href: dashboard() },
        { title: 'nav.clients', href: clientesIndex() },
    ],
};
