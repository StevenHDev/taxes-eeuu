import type { Column } from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ChevronsUpDown, EyeOff } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

/**
 * Encabezado de columna con menú de ordenamiento (asc/desc) y opción de ocultar.
 * Se usa dentro de las definiciones de columnas (`header`) de un DataTable.
 */
export function DataTableColumnHeader<TData, TValue>({
    column,
    title,
    className,
}: {
    column: Column<TData, TValue>;
    title: string;
    className?: string;
}) {
    if (!column.getCanSort()) {
        return <span className={cn('text-sm', className)}>{title}</span>;
    }

    const sorted = column.getIsSorted();

    return (
        <div className={cn('flex items-center', className)}>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="-ml-2.5 h-8 data-[state=open]:bg-accent"
                    >
                        <span>{title}</span>
                        {sorted === 'desc' ? (
                            <ArrowDown className="ml-1.5 size-3.5" />
                        ) : sorted === 'asc' ? (
                            <ArrowUp className="ml-1.5 size-3.5" />
                        ) : (
                            <ChevronsUpDown className="ml-1.5 size-3.5 opacity-50" />
                        )}
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start">
                    <DropdownMenuItem onClick={() => column.toggleSorting(false)}>
                        <ArrowUp className="mr-2 size-3.5 text-muted-foreground/70" />
                        Ascendente
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => column.toggleSorting(true)}>
                        <ArrowDown className="mr-2 size-3.5 text-muted-foreground/70" />
                        Descendente
                    </DropdownMenuItem>
                    {column.getCanHide() && (
                        <>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                onClick={() => column.toggleVisibility(false)}
                            >
                                <EyeOff className="mr-2 size-3.5 text-muted-foreground/70" />
                                Ocultar
                            </DropdownMenuItem>
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}
