import { Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/ui/data-table';
import { DataTableColumnHeader } from '@/components/ui/data-table-column-header';
import { dashboard } from '@/routes';
import { index as mensajesIndex } from '@/routes/agente/mensajes';
import { show as clienteShowRoute } from '@/routes/clientes';
import type { MensajeAgenteLog, RolMensajeAgente } from '@/types';

const ROL_BADGE_VARIANT: Record<
    RolMensajeAgente,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    cliente: 'outline',
    agente: 'default',
    preparador: 'secondary',
    sistema: 'destructive',
};

function useRolLabel(): Record<RolMensajeAgente, string> {
    const { t } = useTranslation();

    return {
        cliente: t('agente.mensajes.rol.cliente'),
        agente: t('agente.mensajes.rol.agente'),
        preparador: t('agente.mensajes.rol.preparador'),
        sistema: t('agente.mensajes.rol.sistema'),
    };
}

function useColumns(): ColumnDef<MensajeAgenteLog>[] {
    const { t, i18n } = useTranslation();
    const rolLabel = useRolLabel();

    const formatDate = (iso: string) =>
        new Intl.DateTimeFormat(i18n.language, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(iso));

    return [
        {
            accessorKey: 'created_at',
            id: 'fecha',
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title={t('agente.mensajes.columns.date')}
                />
            ),
            cell: ({ row }) => (
                <span className="text-sm whitespace-nowrap text-muted-foreground">
                    {formatDate(row.original.created_at)}
                </span>
            ),
        },
        {
            id: 'origen',
            accessorFn: (m) => `${m.telefono} ${m.cliente_nombre ?? ''}`,
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title={t('agente.mensajes.columns.origin')}
                />
            ),
            cell: ({ row }) => (
                <div>
                    <div className="text-sm text-foreground">
                        {row.original.telefono}
                    </div>
                    {row.original.cliente_id && row.original.cliente_nombre ? (
                        <Link
                            href={
                                clienteShowRoute({
                                    cliente: row.original.cliente_id,
                                }).url
                            }
                            className="text-xs text-muted-foreground underline underline-offset-2 hover:text-foreground"
                        >
                            {row.original.cliente_nombre}
                        </Link>
                    ) : (
                        <span className="text-xs text-muted-foreground">
                            {t('agente.mensajes.sinCuenta')}
                        </span>
                    )}
                </div>
            ),
            // Filtra por el teléfono crudo (no por el string combinado que usa
            // el accessor para orden/búsqueda) — así el filtro de facetas deja
            // aislar todos los mensajes de una misma línea, sin importar si el
            // nombre del cliente cambió entre mensajes.
            filterFn: (row, _id, value) =>
                (value as string[]).includes(row.original.telefono),
        },
        {
            accessorKey: 'rol',
            id: 'rol',
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title={t('agente.mensajes.columns.role')}
                />
            ),
            cell: ({ row }) => (
                <Badge variant={ROL_BADGE_VARIANT[row.original.rol]}>
                    {rolLabel[row.original.rol]}
                </Badge>
            ),
            filterFn: (row, id, value) =>
                (value as string[]).includes(row.getValue<string>(id)),
        },
        {
            accessorKey: 'contenido',
            id: 'contenido',
            header: () => (
                <span className="text-xs">
                    {t('agente.mensajes.columns.content')}
                </span>
            ),
            cell: ({ row }) => (
                <p className="max-w-md truncate text-sm text-foreground">
                    {row.original.contenido}
                </p>
            ),
            enableSorting: false,
        },
        {
            accessorKey: 'proveedor',
            id: 'proveedor',
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title={t('agente.mensajes.columns.provider')}
                />
            ),
            cell: ({ row }) => (
                <span className="text-xs text-muted-foreground">
                    {row.original.proveedor ?? '—'}
                </span>
            ),
            filterFn: (row, id, value) =>
                (value as string[]).includes(row.getValue<string>(id) ?? ''),
        },
        {
            accessorKey: 'prompt_version',
            id: 'version',
            header: () => (
                <span className="text-xs">
                    {t('agente.mensajes.columns.promptVersion')}
                </span>
            ),
            cell: ({ row }) => (
                <span className="text-xs text-muted-foreground">
                    {row.original.prompt_version ?? '—'}
                </span>
            ),
        },
    ];
}

/**
 * Una entrada por línea telefónica distinta, para el filtro de facetas
 * "origen" — deja aislar de un click todos los mensajes de una misma
 * conversación en esta bandeja general, sin depender de que cada mensaje
 * traiga cliente_id (agrupa por teléfono, que sí está siempre presente).
 */
function useTelefonoOptions(
    mensajes: MensajeAgenteLog[],
): { label: string; value: string }[] {
    return useMemo(() => {
        const vistos = new Map<string, string>();

        for (const m of mensajes) {
            if (!vistos.has(m.telefono)) {
                vistos.set(
                    m.telefono,
                    m.cliente_nombre
                        ? `${m.telefono} — ${m.cliente_nombre}`
                        : m.telefono,
                );
            }
        }

        return Array.from(vistos, ([value, label]) => ({ label, value }));
    }, [mensajes]);
}

export default function AgenteMensajes({
    mensajes,
}: {
    mensajes: MensajeAgenteLog[];
}) {
    const { t } = useTranslation();
    const rolLabel = useRolLabel();
    const columns = useColumns();
    const telefonoOptions = useTelefonoOptions(mensajes);

    return (
        <>
            <Head title={t('agente.mensajes.title')} />

            <div className="flex flex-col gap-1">
                <h1 className="text-xl font-semibold">
                    {t('agente.mensajes.title')}
                </h1>
                <p className="text-sm text-muted-foreground">
                    {t('agente.mensajes.subtitle')}
                </p>
            </div>

            <DataTable
                columns={columns}
                data={mensajes}
                searchPlaceholder={t('agente.mensajes.searchPlaceholder')}
                emptyMessage={t('agente.mensajes.empty')}
                facetedFilters={[
                    {
                        columnId: 'rol',
                        title: t('agente.mensajes.columns.role'),
                        options: (
                            Object.keys(rolLabel) as RolMensajeAgente[]
                        ).map((rol) => ({
                            label: rolLabel[rol],
                            value: rol,
                        })),
                    },
                    {
                        columnId: 'origen',
                        title: t('agente.mensajes.columns.origin'),
                        options: telefonoOptions,
                    },
                ]}
            />
        </>
    );
}

AgenteMensajes.layout = {
    breadcrumbs: [
        { title: 'nav.dashboard', href: dashboard() },
        { title: 'agente.nav.mensajes', href: mensajesIndex() },
    ],
};
