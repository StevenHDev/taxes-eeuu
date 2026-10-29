import { Link, router, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import {
    documentos,
    formularios,
    parametros,
    preguntas,
    reglas,
} from '@/routes/catalogo-fiscal';
import type { CatalogoFiscalPageProps } from '@/types';

/**
 * Catálogo fiscal del año (F1.4, 7.7 del documento madre): cabecera con el
 * año y 5 pestañas. Las pestañas son enlaces reales (se pueden abrir en
 * otra pestaña y volver atrás) y se precargan al pasar el mouse.
 */
const PESTANAS = [
    { clave: 'preguntas', ruta: preguntas },
    { clave: 'documentos', ruta: documentos },
    { clave: 'formularios', ruta: formularios },
    { clave: 'parametros', ruta: parametros },
    { clave: 'reglas', ruta: reglas },
] as const;

export default function CatalogoFiscalLayout({ children }: PropsWithChildren) {
    const { t } = useTranslation();
    const page = usePage<CatalogoFiscalPageProps>();
    const { anio, anios, estadoAnio } = page.props;
    const actual =
        PESTANAS.find((p) => page.component === `catalogo-fiscal/${p.clave}`) ??
        PESTANAS[0];

    return (
        <div className="space-y-6 px-4 py-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                {/* h1 propio (Heading usa h2 y un margen pensado para otras pantallas). */}
                <header className="space-y-0.5">
                    <h1 className="text-xl font-semibold tracking-tight">
                        {t('catalogoFiscal.titulo')}
                    </h1>
                    <p className="max-w-3xl text-sm text-muted-foreground">
                        {t('catalogoFiscal.descripcion')}
                    </p>
                </header>

                <div className="flex items-center gap-2">
                    <Label htmlFor="catalogo-anio" className="text-sm">
                        {t('catalogoFiscal.anioFiscal')}
                    </Label>
                    <Select
                        value={String(anio)}
                        onValueChange={(valor) =>
                            router.visit(actual.ruta({ anio: valor }).url)
                        }
                    >
                        <SelectTrigger id="catalogo-anio" className="w-28">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {anios.map((a) => (
                                <SelectItem key={a} value={String(a)}>
                                    {a}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Badge
                        variant={
                            estadoAnio === 'vigente' ? 'default' : 'secondary'
                        }
                    >
                        {t(`catalogoFiscal.estadoAnio.${estadoAnio}`)}
                    </Badge>
                </div>
            </div>

            <nav
                aria-label={t('catalogoFiscal.pestanasAria')}
                className="-mx-4 overflow-x-auto border-b border-border px-4"
            >
                <ul className="flex min-w-max gap-1">
                    {PESTANAS.map((p) => {
                        const activa = p.clave === actual.clave;

                        return (
                            <li key={p.clave}>
                                <Link
                                    href={p.ruta({ anio })}
                                    prefetch
                                    aria-current={activa ? 'page' : undefined}
                                    className={cn(
                                        'inline-flex h-10 items-center border-b-2 px-3 text-sm font-medium transition-colors',
                                        'rounded-t-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                        activa
                                            ? 'border-primary text-foreground'
                                            : 'border-transparent text-muted-foreground hover:border-border hover:text-foreground active:text-foreground',
                                    )}
                                >
                                    {t(`catalogoFiscal.pestanas.${p.clave}`)}
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            </nav>

            <section>{children}</section>
        </div>
    );
}
