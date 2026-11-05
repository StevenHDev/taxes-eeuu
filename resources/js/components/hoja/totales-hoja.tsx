import { CheckCircle2, CircleDashed } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { TotalHoja } from '@/types/hoja-taxwise';

const CELDA =
    'block whitespace-normal px-3 py-1.5 md:table-cell md:align-top md:p-2 print:table-cell print:p-1';

function Etiqueta({ children }: { children: string }) {
    return (
        <span className="mb-0.5 block text-xs font-medium text-muted-foreground uppercase md:hidden print:hidden">
            {children}
        </span>
    );
}

function formatear(valor: string | null, idioma: string): string {
    const n = valor === null ? NaN : Number(valor);

    return Number.isFinite(n)
        ? new Intl.NumberFormat(idioma, {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          }).format(n)
        : '—';
}

/** Los totales de control por línea: se comparan con lo que calcula TaxWise. */
export function TotalesHoja({ totales }: { totales: TotalHoja[] }) {
    const { t, i18n } = useTranslation();

    if (totales.length === 0) {
        return null;
    }

    return (
        <section
            aria-labelledby="hoja-totales"
            className="break-inside-avoid space-y-3"
        >
            <h2
                id="hoja-totales"
                className="border-b pb-1 font-display text-lg font-semibold"
            >
                {t('hoja.totalesTitulo')}
            </h2>
            <p className="text-sm text-muted-foreground">
                {t('hoja.totalesAyuda')}
            </p>
            <Table className="block md:table print:table">
                <TableHeader className="hidden md:table-header-group print:table-header-group">
                    <TableRow>
                        {(
                            [
                                'destino',
                                'descripcion',
                                'valor',
                                'nota',
                                'completa',
                            ] as const
                        ).map((c) => (
                            <TableHead key={c} scope="col">
                                {t(`hoja.totales.${c}`)}
                            </TableHead>
                        ))}
                    </TableRow>
                </TableHeader>
                <TableBody className="block md:table-row-group print:table-row-group [&>tr]:bg-card!">
                    {totales.map((total) => {
                        const Icono = total.completo
                            ? CheckCircle2
                            : CircleDashed;

                        return (
                            <TableRow
                                key={`${total.destino}-${total.descripcion}`}
                                className="mb-3 block break-inside-avoid rounded-lg border py-2 md:mb-0 md:table-row md:rounded-none md:border-0 md:border-b md:py-0 print:table-row print:rounded-none print:border-0 print:border-b print:py-0"
                            >
                                <TableCell className={CELDA}>
                                    <Etiqueta>
                                        {t('hoja.totales.destino')}
                                    </Etiqueta>
                                    {total.destino}
                                </TableCell>
                                <TableCell className={CELDA}>
                                    <Etiqueta>
                                        {t('hoja.totales.descripcion')}
                                    </Etiqueta>
                                    {total.descripcion}
                                </TableCell>
                                <TableCell className={CELDA}>
                                    <Etiqueta>
                                        {t('hoja.totales.valor')}
                                    </Etiqueta>
                                    <span className="font-medium tabular-nums">
                                        {formatear(total.valor, i18n.language)}
                                    </span>
                                </TableCell>
                                <TableCell className={CELDA}>
                                    <Etiqueta>
                                        {t('hoja.totales.nota')}
                                    </Etiqueta>
                                    {total.nota}
                                </TableCell>
                                <TableCell className={CELDA}>
                                    <Etiqueta>
                                        {t('hoja.totales.completa')}
                                    </Etiqueta>
                                    <p
                                        className={
                                            total.completo
                                                ? 'flex items-center gap-1.5 text-emerald-700 dark:text-emerald-400'
                                                : 'flex items-center gap-1.5 text-amber-700 dark:text-amber-400'
                                        }
                                    >
                                        <Icono
                                            className="size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                        {total.completo
                                            ? t('hoja.completa')
                                            : t('hoja.incompleta')}
                                    </p>
                                </TableCell>
                            </TableRow>
                        );
                    })}
                </TableBody>
            </Table>
        </section>
    );
}
