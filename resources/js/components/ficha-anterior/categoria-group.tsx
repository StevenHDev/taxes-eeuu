import { Check, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CampoRow } from '@/components/ficha-anterior/campo-row';
import { necesitaAtencion } from '@/components/ficha-anterior/constantes';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { CampoCliente } from '@/types';

export function CategoriaGroup({
    clienteId,
    taxYear,
    categoria,
    campos,
    formaLabel,
}: {
    clienteId: number;
    taxYear: number;
    categoria: string;
    campos: CampoCliente[];
    formaLabel: string;
}) {
    const { t } = useTranslation();
    const pendientes = campos.filter(necesitaAtencion).length;
    const [abierto, setAbierto] = useState(pendientes > 0);
    const recibidos = campos.filter((c) => c.estado === 'recibido').length;
    const completo = pendientes === 0 && recibidos === campos.length;

    return (
        <div className="rounded-lg border">
            <button
                type="button"
                onClick={() => setAbierto((v) => !v)}
                aria-expanded={abierto}
                className="flex w-full items-center justify-between gap-3 p-3 text-left"
            >
                <span className="flex min-w-0 items-center gap-2">
                    <ChevronRight
                        className={`size-4 shrink-0 text-muted-foreground transition-transform ${
                            abierto ? 'rotate-90' : ''
                        }`}
                        aria-hidden
                    />
                    <span className="truncate text-sm font-medium text-foreground">
                        {t(`clienteShow.categorias.${categoria}`)}
                    </span>
                </span>
                <span className="flex shrink-0 items-center gap-2">
                    {pendientes > 0 && (
                        <Badge variant="secondary" className="font-normal">
                            {t('clienteShow.categoriaAtencion', {
                                count: pendientes,
                            })}
                        </Badge>
                    )}
                    <span className="flex items-center gap-1 font-mono text-micro text-muted-foreground tabular-nums">
                        {recibidos}/{campos.length}
                        {completo && (
                            <Check
                                className="size-3 text-emerald-500"
                                aria-hidden
                            />
                        )}
                    </span>
                </span>
            </button>

            {abierto && (
                <Table className="border-t">
                    <TableHeader className="bg-transparent [&_th]:text-muted-foreground [&_tr]:border-border">
                        <TableRow className="hover:bg-transparent">
                            <TableHead className="font-mono text-micro uppercase">
                                {t('clienteShow.table.field')}
                            </TableHead>
                            <TableHead className="font-mono text-micro uppercase">
                                {t('clienteShow.table.value')}
                            </TableHead>
                            <TableHead className="font-mono text-micro uppercase">
                                {t('clienteShow.table.status')}
                            </TableHead>
                            <TableHead className="text-right font-mono text-micro uppercase">
                                {t('common.actions')}
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {campos.map((campo) => (
                            <CampoRow
                                key={`${campo.forma}-${campo.campo}`}
                                clienteId={clienteId}
                                taxYear={taxYear}
                                campo={campo}
                                formaLabel={formaLabel}
                            />
                        ))}
                    </TableBody>
                </Table>
            )}
        </div>
    );
}

// Una forma por sección: el preparador trabaja «el 1040», no una tabla plana
