import { Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';

export function BotonImprimir({ etiqueta }: { etiqueta: string }) {
    return (
        <Button
            type="button"
            variant="outline"
            className="min-h-11 print:hidden"
            onClick={() => window.print()}
        >
            <Printer aria-hidden="true" />
            {etiqueta}
        </Button>
    );
}
