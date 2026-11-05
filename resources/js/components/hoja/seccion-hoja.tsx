import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { FilaHoja } from '@/components/hoja/fila-hoja';
import {
    Table,
    TableBody,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { SeccionHoja as Seccion } from '@/types/hoja-taxwise';
import { ESTADOS_RESUELTOS } from '@/types/hoja-taxwise';

const COLUMNAS = [
    'destino',
    'descripcion',
    'valor',
    'origen',
    'estado',
] as const;

/** Una sección con sus grupos; cada grupo es una tabla (md) o una lista de tarjetas (celular). */
export function SeccionHoja({
    seccion,
    soloPendiente,
    alAbrirConfianza,
}: {
    seccion: Seccion;
    soloPendiente: boolean;
    alAbrirConfianza: () => void;
}) {
    const { t } = useTranslation();
    const hayVisibles = seccion.grupos.some((g) =>
        g.filas.some((f) => !soloPendiente || !ESTADOS_RESUELTOS.has(f.estado)),
    );

    return (
        <section
            aria-labelledby={`hoja-${seccion.codigo}`}
            className={
                hayVisibles ? 'space-y-3' : 'hidden space-y-3 print:block'
            }
        >
            <h2
                id={`hoja-${seccion.codigo}`}
                className="border-b pb-1 font-display text-lg font-semibold"
            >
                {seccion.titulo}
            </h2>
            {seccion.grupos.map((grupo, i) => {
                const visibles = grupo.filas.some(
                    (f) => !soloPendiente || !ESTADOS_RESUELTOS.has(f.estado),
                );

                return (
                    <div
                        key={`${grupo.item_id ?? 'g'}-${i}`}
                        className={
                            visibles
                                ? 'space-y-2'
                                : 'hidden space-y-2 print:block'
                        }
                    >
                        {grupo.titulo !== seccion.titulo ? (
                            <h3 className="text-sm font-semibold">
                                {grupo.enlace ? (
                                    <Link
                                        href={grupo.enlace}
                                        className="inline-flex min-h-11 items-center underline-offset-2 hover:underline md:min-h-0 print:no-underline"
                                    >
                                        {grupo.titulo}
                                    </Link>
                                ) : (
                                    grupo.titulo
                                )}
                            </h3>
                        ) : null}
                        <Table className="block md:table print:table">
                            <TableHeader className="hidden md:table-header-group print:table-header-group">
                                <TableRow>
                                    {COLUMNAS.map((c) => (
                                        <TableHead key={c} scope="col">
                                            {t(`hoja.columnas.${c}`)}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            </TableHeader>
                            <TableBody className="block md:table-row-group print:table-row-group [&>tr]:bg-card!">
                                {grupo.filas.map((fila) => (
                                    <FilaHoja
                                        key={fila.id}
                                        fila={fila}
                                        visible={
                                            !soloPendiente ||
                                            !ESTADOS_RESUELTOS.has(fila.estado)
                                        }
                                        alAbrirConfianza={alAbrirConfianza}
                                    />
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                );
            })}
        </section>
    );
}
