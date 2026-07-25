import type { Table } from '@tanstack/react-table';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

/**
 * Controles de paginación para un DataTable: filas por página, indicador de
 * página y navegación (primera/anterior/siguiente/última).
 */
export function DataTablePagination<TData>({
    table,
}: {
    table: Table<TData>;
}) {
    const { t } = useTranslation();
    const { pageIndex, pageSize } = table.getState().pagination;
    const totalRows = table.getFilteredRowModel().rows.length;

    return (
        <div className="flex flex-col items-center justify-between gap-3 px-1 sm:flex-row">
            <p className="text-sm text-muted-foreground tabular-nums">
                {t('dataTable.rows', { count: totalRows })}
            </p>

            <div className="flex items-center gap-4 sm:gap-6">
                <div className="flex items-center gap-2">
                    <p className="hidden text-sm font-medium sm:block">
                        {t('dataTable.rowsPerPage')}
                    </p>
                    <Select
                        value={`${pageSize}`}
                        onValueChange={(v) => table.setPageSize(Number(v))}
                    >
                        <SelectTrigger className="h-8 w-[72px]">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent side="top">
                            {[10, 20, 30, 50, 100].map((size) => (
                                <SelectItem key={size} value={`${size}`}>
                                    {size}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <p className="text-sm font-medium tabular-nums">
                    {t('dataTable.pageOf', {
                        current: pageIndex + 1,
                        total: table.getPageCount() || 1,
                    })}
                </p>

                <div className="flex items-center gap-1">
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        onClick={() => table.setPageIndex(0)}
                        disabled={!table.getCanPreviousPage()}
                        aria-label={t('dataTable.firstPage')}
                    >
                        <ChevronsLeft className="size-4" />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        onClick={() => table.previousPage()}
                        disabled={!table.getCanPreviousPage()}
                        aria-label={t('dataTable.prevPage')}
                    >
                        <ChevronLeft className="size-4" />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        onClick={() => table.nextPage()}
                        disabled={!table.getCanNextPage()}
                        aria-label={t('dataTable.nextPage')}
                    >
                        <ChevronRight className="size-4" />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        onClick={() => table.setPageIndex(table.getPageCount() - 1)}
                        disabled={!table.getCanNextPage()}
                        aria-label={t('dataTable.lastPage')}
                    >
                        <ChevronsRight className="size-4" />
                    </Button>
                </div>
            </div>
        </div>
    );
}
