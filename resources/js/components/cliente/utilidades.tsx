import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/** Botón de solo ícono con su nombre en `aria-label` y en un tooltip. */
export function AccionIcono({
    etiqueta,
    onClick,
    disabled,
    children,
}: {
    etiqueta: string;
    onClick: () => void;
    disabled?: boolean;
    children: ReactNode;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={etiqueta}
                    disabled={disabled}
                    onClick={onClick}
                >
                    {children}
                </Button>
            </TooltipTrigger>
            <TooltipContent>{etiqueta}</TooltipContent>
        </Tooltip>
    );
}
