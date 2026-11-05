import { Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { ClavePestana, PestanaFicha } from '@/types/expediente';

/**
 * En `sm` y más: enlaces reales (se abren en otra pestaña del navegador y se
 * precargan). En el celular: un `Select` "Sección" que navega.
 */
export function FichaPestanas({
    pestanas,
    activa,
}: {
    pestanas: PestanaFicha[];
    activa: ClavePestana;
}) {
    const { t } = useTranslation();
    const actual = pestanas.some((p) => p.clave === activa)
        ? activa
        : (pestanas[0]?.clave ?? activa);

    return (
        <>
            <div className="flex flex-col gap-1.5 sm:hidden print:hidden">
                <Label htmlFor="ficha-seccion" className="text-sm">
                    {t('ficha.seccion')}
                </Label>
                <Select
                    value={actual}
                    onValueChange={(clave) => {
                        const destino = pestanas.find((p) => p.clave === clave);

                        if (destino) {
                            router.visit(destino.url);
                        }
                    }}
                >
                    <SelectTrigger id="ficha-seccion" className="h-11 w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {pestanas.map((p) => (
                            <SelectItem
                                key={p.clave}
                                value={p.clave}
                                className="min-h-11"
                            >
                                {t(`ficha.pestanas.${p.clave}`)}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <nav
                aria-label={t('ficha.pestanasAria')}
                className="-mx-4 hidden overflow-x-auto border-b border-border px-4 sm:block print:hidden"
            >
                <ul className="flex min-w-max gap-1">
                    {pestanas.map((p) => {
                        const esActiva = p.clave === actual;

                        return (
                            <li key={p.clave}>
                                <Link
                                    href={p.url}
                                    prefetch
                                    aria-current={esActiva ? 'page' : undefined}
                                    className={cn(
                                        'inline-flex h-11 items-center border-b-2 px-3 text-sm font-medium transition-colors',
                                        'rounded-t-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                        esActiva
                                            ? 'border-primary text-foreground'
                                            : 'border-transparent text-muted-foreground hover:border-border hover:text-foreground active:text-foreground',
                                    )}
                                >
                                    {t(`ficha.pestanas.${p.clave}`)}
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            </nav>
        </>
    );
}
