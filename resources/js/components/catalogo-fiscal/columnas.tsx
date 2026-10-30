import type { ColumnDef } from '@tanstack/react-table';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTableColumnHeader } from '@/components/ui/data-table-column-header';
import type { FuenteIrs } from '@/types';
import { EstadoValidacion, estadoValidacion } from './estado-validacion';

/**
 * Columnas que comparten las 5 pestañas: el código al inicio (monoespaciado,
 * para escanear rápido), el estado de validación y el botón de detalle.
 */
export function useColumnasComunes<
    T extends FuenteIrs & { codigo: string; activo?: boolean },
>(
    onVer: (pieza: T) => void,
): {
    codigo: ColumnDef<T>;
    validacion: ColumnDef<T>;
    acciones: ColumnDef<T>;
} {
    const { t } = useTranslation();

    // Memorizado: las páginas usan estas columnas dentro de su propio useMemo.
    return useMemo(
        () => ({
            codigo: {
                accessorKey: 'codigo',
                id: 'codigo',
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('catalogoFiscal.columnas.codigo')}
                    />
                ),
                cell: ({ row }) => (
                    <div className="flex flex-col items-start gap-1">
                        <span className="font-mono text-xs font-semibold">
                            {row.original.codigo}
                        </span>
                        {row.original.activo === false ? (
                            <Badge
                                variant="outline"
                                className="text-muted-foreground"
                            >
                                {t('catalogoFiscal.edicion.desactivado')}
                            </Badge>
                        ) : null}
                    </div>
                ),
                enableHiding: false,
            },
            validacion: {
                id: 'validacion',
                accessorFn: (pieza) => estadoValidacion(pieza),
                header: t('catalogoFiscal.columnas.validacion'),
                cell: ({ row }) => <EstadoValidacion pieza={row.original} />,
                filterFn: (row, id, value) =>
                    (value as string[]).includes(row.getValue<string>(id)),
            },
            acciones: {
                id: 'acciones',
                header: () => (
                    <span className="sr-only">
                        {t('catalogoFiscal.columnas.acciones')}
                    </span>
                ),
                cell: ({ row }) => (
                    <div className="flex justify-end">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => onVer(row.original)}
                            aria-label={t('catalogoFiscal.verDetalleDe', {
                                codigo: row.original.codigo,
                            })}
                        >
                            {t('catalogoFiscal.verDetalle')}
                        </Button>
                    </div>
                ),
                enableHiding: false,
                enableSorting: false,
            },
        }),
        [onVer, t],
    );
}

/** Filtro por estado de validación ("Solo sin validar"), igual en todas las pestañas. */
export function useFiltroValidacion() {
    const { t } = useTranslation();

    return {
        columnId: 'validacion',
        title: t('catalogoFiscal.columnas.validacion'),
        options: [
            {
                label: t('catalogoFiscal.validacion.sinValidar'),
                value: 'sin_validar',
            },
            {
                label: t('catalogoFiscal.validacion.validado'),
                value: 'validado',
            },
        ],
    };
}

/** Filtro de igualdad para columnas con facetas. */
export const filtroIncluye = <T,>(
    row: { getValue: <V>(id: string) => V },
    id: string,
    value: unknown,
) => (value as string[]).includes(String(row.getValue<T>(id)));
