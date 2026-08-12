import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/ui/data-table';
import { DataTableColumnHeader } from '@/components/ui/data-table-column-header';
import { dashboard } from '@/routes';
import { index as bitacoraIndex } from '@/routes/bitacora';
import type { AccionAuditoria, BitacoraEvento } from '@/types';

function useAccionLabel(): Record<AccionAuditoria, string> {
    const { t } = useTranslation();

    return {
        creado: t('bitacora.acciones.creado'),
        actualizado: t('bitacora.acciones.actualizado'),
        eliminado: t('bitacora.acciones.eliminado'),
        inicio_sesion: t('bitacora.acciones.inicioSesion'),
        cierre_sesion: t('bitacora.acciones.cierreSesion'),
    };
}

const ACCION_BADGE_VARIANT: Record<
    AccionAuditoria,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    creado: 'secondary',
    actualizado: 'default',
    eliminado: 'destructive',
    inicio_sesion: 'outline',
    cierre_sesion: 'outline',
};

function useColumns(): ColumnDef<BitacoraEvento>[] {
    const { t, i18n } = useTranslation();
    const accionLabel = useAccionLabel();

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
                    title={t('bitacora.columns.date')}
                />
            ),
            cell: ({ row }) => (
                <span className="text-sm whitespace-nowrap text-muted-foreground">
                    {formatDate(row.original.created_at)}
                </span>
            ),
        },
        {
            id: 'actor',
            accessorFn: (e) => `${e.actor_nombre ?? ''} ${e.actor_email ?? ''}`,
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title={t('bitacora.columns.actor')}
                />
            ),
            cell: ({ row }) => (
                <div>
                    <div className="font-medium text-foreground">
                        {row.original.actor_nombre ?? t('bitacora.system')}
                    </div>
                    {row.original.actor_email && (
                        <div className="text-xs text-muted-foreground">
                            {row.original.actor_email}
                        </div>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'accion',
            id: 'accion',
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title={t('bitacora.columns.action')}
                />
            ),
            cell: ({ row }) => (
                <Badge variant={ACCION_BADGE_VARIANT[row.original.accion]}>
                    {accionLabel[row.original.accion]}
                </Badge>
            ),
            filterFn: (row, id, value) =>
                (value as string[]).includes(row.getValue<string>(id)),
        },
        {
            id: 'registro',
            accessorFn: (e) => `${e.etiqueta ?? ''} ${e.auditable_type ?? ''}`,
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title={t('bitacora.columns.record')}
                />
            ),
            cell: ({ row }) => (
                <div>
                    <div className="text-sm text-foreground">
                        {row.original.etiqueta ?? '—'}
                    </div>
                    {row.original.auditable_type && (
                        <div className="text-xs text-muted-foreground">
                            {row.original.auditable_type}
                        </div>
                    )}
                </div>
            ),
        },
        {
            id: 'campos',
            accessorFn: (e) => e.campos_afectados?.join(', ') ?? '',
            header: () => (
                <span className="text-xs">{t('bitacora.columns.fields')}</span>
            ),
            cell: ({ row }) =>
                row.original.campos_afectados &&
                row.original.campos_afectados.length > 0 ? (
                    <div className="flex flex-wrap gap-1">
                        {row.original.campos_afectados.map((campo) => (
                            <Badge key={campo} variant="outline">
                                {campo}
                            </Badge>
                        ))}
                    </div>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
            enableSorting: false,
        },
        {
            accessorKey: 'ip_address',
            id: 'ip',
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title={t('bitacora.columns.ip')}
                />
            ),
            cell: ({ row }) => (
                <span className="text-xs text-muted-foreground">
                    {row.original.ip_address ?? '—'}
                </span>
            ),
        },
    ];
}

export default function BitacoraIndex({
    eventos,
}: {
    eventos: BitacoraEvento[];
}) {
    const { t } = useTranslation();
    const accionLabel = useAccionLabel();
    const columns = useColumns();

    return (
        <>
            <Head title={t('bitacora.title')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-1">
                    <h1 className="text-xl font-semibold">
                        {t('bitacora.title')}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {t('bitacora.subtitle')}
                    </p>
                </div>

                <DataTable
                    columns={columns}
                    data={eventos}
                    searchPlaceholder={t('bitacora.searchPlaceholder')}
                    emptyMessage={t('bitacora.empty')}
                    facetedFilters={[
                        {
                            columnId: 'accion',
                            title: t('bitacora.columns.action'),
                            options: (
                                Object.keys(accionLabel) as AccionAuditoria[]
                            ).map((accion) => ({
                                label: accionLabel[accion],
                                value: accion,
                            })),
                        },
                    ]}
                />
            </div>
        </>
    );
}

BitacoraIndex.layout = {
    breadcrumbs: [
        { title: 'nav.dashboard', href: dashboard() },
        { title: 'nav.auditLog', href: bitacoraIndex() },
    ],
};
