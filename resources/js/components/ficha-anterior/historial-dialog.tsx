import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { FieldValue } from '@/components/ficha-anterior/field-value';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { humanizarClave } from '@/lib/humanizar-clave';
import { historial as campoHistorial } from '@/routes/clientes/campos';
import type { CampoCliente, HistorialCambio } from '@/types';

const SOURCE_VARIANT: Record<
    HistorialCambio['source'],
    'default' | 'secondary' | 'outline'
> = {
    agente_ia: 'default',
    preparador: 'secondary',
    administrador: 'outline',
    cliente: 'default',
};

export function HistorialDialog({
    clienteId,
    taxYear,
    campo,
}: {
    clienteId: number;
    taxYear: number;
    campo: CampoCliente;
}) {
    const { t, i18n } = useTranslation();
    const [items, setItems] = useState<HistorialCambio[] | null>(null);

    const formatDate = (iso: string | null): string => {
        if (!iso) {
            return '';
        }

        const fecha = new Date(iso);

        if (Number.isNaN(fecha.getTime())) {
            return iso;
        }

        return new Intl.DateTimeFormat(i18n.language, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(fecha);
    };

    const load = async () => {
        const response = await fetch(
            campoHistorial({ cliente: clienteId, campo: campo.campo }).url +
                `?forma=${campo.forma}&tax_year=${taxYear}`,
            { headers: { Accept: 'application/json' } },
        );
        const json = await response.json();
        setItems(json.historial ?? json.data ?? []);
    };

    return (
        <Dialog onOpenChange={(open) => open && load()}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm">
                    {t('clienteShow.history.trigger')}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogTitle>
                    {t('clienteShow.history.title', {
                        campo: humanizarClave(campo.campo),
                    })}
                </DialogTitle>
                <div className="max-h-[70vh] space-y-3 overflow-y-auto">
                    {items === null && (
                        <p className="text-sm text-muted-foreground">
                            {t('common.loading')}
                        </p>
                    )}
                    {items?.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            {t('clienteShow.history.empty')}
                        </p>
                    )}
                    {items?.map((h, i) => (
                        <div key={i} className="rounded-lg border p-3">
                            <div className="mb-2 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                <Badge
                                    variant={SOURCE_VARIANT[h.source]}
                                    className="font-normal"
                                >
                                    {t(
                                        `clienteShow.history.source.${h.source}`,
                                    )}
                                </Badge>
                                <span>{formatDate(h.created_at)}</span>
                                {h.modificado_por && (
                                    <span>· {h.modificado_por}</span>
                                )}
                            </div>
                            <div className="space-y-2">
                                <div>
                                    <div className="text-xs font-medium text-muted-foreground">
                                        {t('clienteShow.history.before')}
                                    </div>
                                    <div className="mt-0.5 text-sm">
                                        <FieldValue value={h.valor_anterior} />
                                    </div>
                                </div>
                                <div>
                                    <div className="text-xs font-medium text-muted-foreground">
                                        {t('clienteShow.history.after')}
                                    </div>
                                    <div className="mt-0.5 text-sm">
                                        <FieldValue value={h.valor_nuevo} />
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </DialogContent>
        </Dialog>
    );
}
