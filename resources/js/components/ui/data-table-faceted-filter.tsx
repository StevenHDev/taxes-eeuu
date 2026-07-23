import type { Column } from '@tanstack/react-table';
import { ListFilter } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

/**
 * Filtro por facetas para una columna categórica (rol, estado, forma…).
 * Aplica un filtro de tipo "incluye alguno" — la columna debe usar
 * `filterFn: 'arrIncludesSome'`.
 */
export function DataTableFacetedFilter<TData, TValue>({
    column,
    title,
    options,
}: {
    column?: Column<TData, TValue>;
    title: string;
    options: { label: string; value: string }[];
}) {
    const selected = new Set((column?.getFilterValue() as string[]) ?? []);

    const toggle = (value: string) => {
        const next = new Set(selected);

        if (next.has(value)) {
            next.delete(value);
        } else {
            next.add(value);
        }

        const arr = Array.from(next);
        column?.setFilterValue(arr.length ? arr : undefined);
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm" className="h-9 border-dashed">
                    <ListFilter className="mr-2 size-4" />
                    {title}
                    {selected.size > 0 && (
                        <>
                            <span className="mx-2 h-4 w-px bg-border" />
                            <Badge
                                variant="secondary"
                                className="rounded-sm px-1.5 font-normal"
                            >
                                {selected.size}
                            </Badge>
                        </>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-52">
                <DropdownMenuLabel>{title}</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {options.map((opt) => (
                    <DropdownMenuCheckboxItem
                        key={opt.value}
                        checked={selected.has(opt.value)}
                        onCheckedChange={() => toggle(opt.value)}
                        onSelect={(e) => e.preventDefault()}
                    >
                        {opt.label}
                    </DropdownMenuCheckboxItem>
                ))}
                {selected.size > 0 && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onClick={() => column?.setFilterValue(undefined)}
                            className="justify-center text-sm"
                        >
                            Limpiar filtro
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
