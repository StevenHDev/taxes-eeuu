import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import type { AccionRevision } from '@/types/revision';

/**
 * La acción principal es el único botón primario; las demás van en
 * contorno. Elegir una abre el diálogo de confirmación (lo maneja la página).
 */
export function AccionesExpediente({
    acciones,
    onElegir,
}: {
    acciones: AccionRevision[];
    onElegir: (accion: AccionRevision) => void;
}) {
    const { t } = useTranslation();

    if (acciones.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                {t('revision.acciones.ninguna')}
            </p>
        );
    }

    return (
        <ul className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-start">
            {acciones.map((a) => (
                <li key={a.accion} className="flex flex-col gap-1.5">
                    <Button
                        type="button"
                        variant={a.principal ? 'default' : 'outline'}
                        className="h-11 w-full whitespace-normal sm:w-auto"
                        disabled={!a.habilitada}
                        onClick={() => onElegir(a)}
                    >
                        {a.etiqueta}
                    </Button>
                    {!a.habilitada && a.motivo_deshabilitada && (
                        <p className="text-sm text-muted-foreground">
                            {a.motivo_deshabilitada}
                        </p>
                    )}
                </li>
            ))}
        </ul>
    );
}
