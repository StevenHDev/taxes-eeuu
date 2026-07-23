import {
    type ColumnDef,
    type ColumnFiltersState,
    flexRender,
    getCoreRowModel,
    getFacetedRowModel,
    getFacetedUniqueValues,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    type SortingState,
    useReactTable,
    type VisibilityState,
} from '@tanstack/react-table';
import { Search, SlidersHorizontal, X } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import { DataTableFacetedFilter } from '@/components/ui/data-table-faceted-filter';
import { DataTablePagination } from '@/components/ui/data-table-pagination';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

export type FacetedFilterConfig = {
    columnId: string;
    title: string;
    options: { label: string; value: string }[];
};

/**
 * DataTable client-side reutilizable (TanStack Table): búsqueda global,
 * ordenamiento por columna, filtros por facetas, visibilidad de columnas y
 * paginación. Los datos completos llegan por props (Inertia) y todo el
 * procesamiento ocurre en el navegador.
 */
export function DataTable<TData, TValue>({
    columns,
    data,
    searchPlaceholder = 'Buscar…',
    facetedFilters = [],
    toolbarActions,
    emptyMessage = 'Sin resultados.',
    initialPageSize = 10,
}: {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
    searchPlaceholder?: string;
    facetedFilters?: FacetedFilterConfig[];
    toolbarActions?: ReactNode;
    emptyMessage?: string;
    initialPageSize?: number;
}) {
    const [sorting, setSorting] = useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] = useState<VisibilityState>(
        {},
    );
    const [globalFilter, setGlobalFilter] = useState('');

    const table = useReactTable({
        data,
        columns,
        state: { sorting, columnFilters, columnVisibility, globalFilter },
        initialState: { pagination: { pageSize: initialPageSize } },
        onSortingChange: setSorting,
        onColumnFiltersChange: setColumnFilters,
        onColumnVisibilityChange: setColumnVisibility,
        onGlobalFilterChange: setGlobalFilter,
        getCoreRowModel: getCoreRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFacetedRowModel: getFacetedRowModel(),
        getFacetedUniqueValues: getFacetedUniqueValues(),
    });

    const isFiltered = columnFilters.length > 0 || globalFilter.length > 0;
    const hideableColumns = table
        .getAllColumns()
        .filter((c) => c.getCanHide());

    return (
        <div className="space-y-4">
            {/* Toolbar */}
            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
                    <div className="relative w-full sm:max-w-xs">
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={globalFilter}
                            onChange={(e) => setGlobalFilter(e.target.value)}
                            placeholder={searchPlaceholder}
                            className="rounded-full pl-9"
                        />
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {facetedFilters.map((f) => (
                            <DataTableFacetedFilter
                                key={f.columnId}
                                column={table.getColumn(f.columnId)}
                                title={f.title}
                                options={f.options}
                            />
                        ))}
                        {isFiltered && (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-9 px-2"
                                onClick={() => {
                                    table.resetColumnFilters();
                                    setGlobalFilter('');
                                }}
                            >
                                Limpiar
                                <X className="ml-1 size-4" />
                            </Button>
                        )}
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    {hideableColumns.length > 0 && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-9"
                                >
                                    <SlidersHorizontal className="mr-2 size-4" />
                                    Columnas
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-44">
                                <DropdownMenuLabel>
                                    Mostrar columnas
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                {hideableColumns.map((c) => (
                                    <DropdownMenuCheckboxItem
                                        key={c.id}
                                        checked={c.getIsVisible()}
                                        onCheckedChange={(v) =>
                                            c.toggleVisibility(!!v)
                                        }
                                        onSelect={(e) => e.preventDefault()}
                                        className="capitalize"
                                    >
                                        {c.id}
                                    </DropdownMenuCheckboxItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                    {toolbarActions}
                </div>
            </div>

            {/* Tabla */}
            <div className="overflow-hidden rounded-xl border border-border/60 bg-card shadow-sm">
                <div className="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            {table.getHeaderGroups().map((hg) => (
                                <TableRow
                                    key={hg.id}
                                    className="hover:bg-transparent"
                                >
                                    {hg.headers.map((header) => (
                                        <TableHead key={header.id}>
                                            {header.isPlaceholder
                                                ? null
                                                : flexRender(
                                                      header.column.columnDef
                                                          .header,
                                                      header.getContext(),
                                                  )}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            ))}
                        </TableHeader>
                        <TableBody>
                            {table.getRowModel().rows.length ? (
                                table.getRowModel().rows.map((row) => (
                                    <TableRow key={row.id}>
                                        {row.getVisibleCells().map((cell) => (
                                            <TableCell key={cell.id}>
                                                {flexRender(
                                                    cell.column.columnDef.cell,
                                                    cell.getContext(),
                                                )}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell
                                        colSpan={columns.length}
                                        className="h-24 text-center text-muted-foreground"
                                    >
                                        {emptyMessage}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>

            <DataTablePagination table={table} />
        </div>
    );
}
