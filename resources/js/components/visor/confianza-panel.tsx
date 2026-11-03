import { Info } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { FilaConfianza } from '@/types/lectura';

/** Formatea la confianza (0–1) con dos decimales, como "0,90". */
export function formatearConfianza(valor: number, idioma: string): string {
    return valor.toLocaleString(idioma, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

/**
 * Panel "¿Cómo se calcula la confianza?": la tabla de motivos con su número
 * y su explicación, de la más segura a la menos, y el umbral vigente. Los
 * textos de la tabla vienen del servidor, no están repetidos aquí.
 */
export function ConfianzaPanel({
    tabla,
    umbral,
}: {
    tabla: FilaConfianza[];
    umbral: number;
}) {
    const { t, i18n } = useTranslation();
    const ordenada = [...tabla].sort((a, b) => b.confianza - a.confianza);

    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button variant="outline" size="sm">
                    <Info aria-hidden="true" />
                    {t('visor.confianza.abrir')}
                </Button>
            </SheetTrigger>
            <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
                <SheetHeader>
                    <SheetTitle>{t('visor.confianza.titulo')}</SheetTitle>
                    <SheetDescription>
                        {t('visor.confianza.umbral', {
                            umbral: formatearConfianza(umbral, i18n.language),
                        })}
                    </SheetDescription>
                </SheetHeader>
                <div className="px-4 pb-4">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>
                                    {t('visor.confianza.motivo')}
                                </TableHead>
                                <TableHead className="text-right">
                                    {t('visor.confianza.numero')}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {ordenada.map((fila) => (
                                <TableRow key={fila.motivo}>
                                    <TableCell className="align-top whitespace-normal">
                                        <p className="font-medium">
                                            {fila.titulo}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {fila.explicacion}
                                        </p>
                                    </TableCell>
                                    <TableCell
                                        className={
                                            fila.confianza < umbral
                                                ? 'text-right align-top text-amber-700 tabular-nums dark:text-amber-400'
                                                : 'text-right align-top tabular-nums'
                                        }
                                    >
                                        {formatearConfianza(
                                            fila.confianza,
                                            i18n.language,
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </SheetContent>
        </Sheet>
    );
}
