import { Link } from '@inertiajs/react';
import { Info } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EstadoFila } from '@/components/hoja/estado-fila';
import { ValorFila } from '@/components/hoja/valor-fila';
import { Button } from '@/components/ui/button';
import { TableCell, TableRow } from '@/components/ui/table';
import { formatearConfianza } from '@/components/visor/confianza-panel';
import type { FilaHoja } from '@/types/hoja-taxwise';

/** Etiqueta de cada dato en la tarjeta del celular; en la tabla (md) se ocultan. */
function Etiqueta({ children }: { children: string }) {
    return (
        <span className="mb-0.5 block text-xs font-medium text-muted-foreground uppercase md:hidden print:hidden">
            {children}
        </span>
    );
}

const CELDA =
    'block whitespace-normal px-3 py-1.5 md:table-cell md:align-top md:p-2 print:table-cell print:p-1';

/**
 * Una fila de la hoja. Es una sola estructura: en el celular cada celda es un
 * bloque con su etiqueta (tarjeta); desde `md` y al imprimir es una fila de tabla.
 */
export function FilaHoja({
    fila,
    visible,
    alAbrirConfianza,
}: {
    fila: FilaHoja;
    visible: boolean;
    alAbrirConfianza: () => void;
}) {
    const { t, i18n } = useTranslation();

    return (
        <TableRow
            className={
                visible
                    ? 'mb-3 block break-inside-avoid rounded-lg border py-2 md:mb-0 md:table-row md:rounded-none md:border-0 md:border-b md:py-0 print:table-row print:rounded-none print:border-0 print:border-b print:py-0'
                    : 'hidden print:table-row print:break-inside-avoid'
            }
        >
            <TableCell className={CELDA}>
                <Etiqueta>{t('hoja.columnas.destino')}</Etiqueta>
                {fila.destino ?? (
                    <span className="text-muted-foreground">—</span>
                )}
            </TableCell>
            <TableCell className={CELDA}>
                <Etiqueta>{t('hoja.columnas.descripcion')}</Etiqueta>
                {fila.descripcion}
            </TableCell>
            <TableCell className={CELDA}>
                <Etiqueta>{t('hoja.columnas.valor')}</Etiqueta>
                <ValorFila fila={fila} />
            </TableCell>
            <TableCell className={CELDA}>
                <Etiqueta>{t('hoja.columnas.origen')}</Etiqueta>
                {fila.enlace ? (
                    <Link
                        href={fila.enlace}
                        className="inline-flex min-h-11 items-center text-primary underline underline-offset-2 hover:no-underline md:min-h-0 print:text-inherit print:no-underline"
                    >
                        {fila.origen}
                    </Link>
                ) : (
                    fila.origen
                )}
            </TableCell>
            <TableCell className={CELDA}>
                <Etiqueta>{t('hoja.columnas.estado')}</Etiqueta>
                <EstadoFila estado={fila.estado} texto={fila.estado_texto} />
                {fila.confianza ? (
                    <div className="mt-1 space-y-1 text-xs text-muted-foreground">
                        <p>
                            {t('hoja.confianza', {
                                valor: formatearConfianza(
                                    fila.confianza.valor,
                                    i18n.language,
                                ),
                            })}
                            {fila.confianza.titulo
                                ? ` — ${fila.confianza.titulo}`
                                : ''}
                        </p>
                        {fila.confianza.explicacion ? (
                            <p>{fila.confianza.explicacion}</p>
                        ) : null}
                        <Button
                            type="button"
                            variant="link"
                            size="sm"
                            className="h-auto min-h-11 p-0 text-xs md:min-h-0 print:hidden"
                            onClick={alAbrirConfianza}
                        >
                            <Info aria-hidden="true" />
                            {t('hoja.comoSeCalcula')}
                        </Button>
                    </div>
                ) : null}
            </TableCell>
        </TableRow>
    );
}
