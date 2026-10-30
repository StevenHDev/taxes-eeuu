import type { ReactNode } from 'react';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

/**
 * Panel lateral con el detalle completo de una pieza del catálogo. En
 * pantallas chicas la tabla esconde columnas y todo se lee aquí.
 */
export function DetallePieza({
    abierto,
    onCerrar,
    titulo,
    descripcion,
    acciones,
    children,
}: {
    abierto: boolean;
    onCerrar: () => void;
    titulo: ReactNode;
    descripcion?: ReactNode;
    /** Acciones del administrador (F1.5), arriba del detalle. */
    acciones?: ReactNode;
    children: ReactNode;
}) {
    return (
        <Sheet open={abierto} onOpenChange={(v) => (v ? null : onCerrar())}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>{titulo}</SheetTitle>
                    {descripcion ? (
                        <SheetDescription>{descripcion}</SheetDescription>
                    ) : null}
                </SheetHeader>
                <div className="space-y-6 px-4 pb-6">
                    {acciones}
                    {children}
                </div>
            </SheetContent>
        </Sheet>
    );
}

/** Fila "etiqueta: valor" del detalle. No se muestra si el valor está vacío. */
export function DatoDetalle({
    etiqueta,
    children,
}: {
    etiqueta: string;
    children: ReactNode;
}) {
    if (children === null || children === undefined || children === '') {
        return null;
    }

    return (
        <div className="grid gap-1">
            <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {etiqueta}
            </dt>
            <dd className="text-sm">{children}</dd>
        </div>
    );
}
