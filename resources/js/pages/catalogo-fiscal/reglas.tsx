import { Deferred, Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    filtroIncluye,
    useColumnasComunes,
    useFiltroValidacion,
} from '@/components/catalogo-fiscal/columnas';
import {
    DatoDetalle,
    DetallePieza,
} from '@/components/catalogo-fiscal/detalle-pieza';
import {
    CatalogoError,
    CatalogoVacio,
} from '@/components/catalogo-fiscal/estado-pagina';
import { FuenteIrs } from '@/components/catalogo-fiscal/fuente-irs';
import { TablaSkeleton } from '@/components/catalogo-fiscal/tabla-skeleton';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/ui/data-table';
import type { CatalogoFiscalPageProps, ReglaCatalogo } from '@/types';

const SEVERIDADES = ['bloqueante', 'revision', 'informativa'] as const;

const VARIANTE_SEVERIDAD = {
    bloqueante: 'destructive',
    revision: 'secondary',
    informativa: 'outline',
} as const;

type Props = CatalogoFiscalPageProps & { reglas?: ReglaCatalogo[] };

export default function CatalogoReglas({ anio, puedeEditar, reglas }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={`${t('catalogoFiscal.pestanas.reglas')} ${anio}`} />
            <p className="mb-4 text-sm text-muted-foreground">
                {t('catalogoFiscal.ayudaPestana.reglas')}
            </p>
            <Deferred
                data="reglas"
                fallback={<TablaSkeleton />}
                rescue={<CatalogoError />}
            >
                {reglas && reglas.length > 0 ? (
                    <TablaReglas reglas={reglas} />
                ) : (
                    <CatalogoVacio puedeEditar={puedeEditar} />
                )}
            </Deferred>
        </>
    );
}

function TablaReglas({ reglas }: { reglas: ReglaCatalogo[] }) {
    const { t } = useTranslation();
    const [seleccionada, setSeleccionada] = useState<ReglaCatalogo | null>(
        null,
    );
    const comunes = useColumnasComunes<ReglaCatalogo>(setSeleccionada);
    const filtroValidacion = useFiltroValidacion();

    const columnas = useMemo<ColumnDef<ReglaCatalogo>[]>(
        () => [
            comunes.codigo,
            {
                accessorKey: 'descripcion',
                id: 'regla',
                header: t('catalogoFiscal.columnas.regla'),
                cell: ({ row }) => (
                    <span className="line-clamp-2 max-w-xl whitespace-normal">
                        {row.original.descripcion}
                    </span>
                ),
            },
            {
                accessorKey: 'severidad',
                id: 'severidad',
                header: t('catalogoFiscal.columnas.severidad'),
                cell: ({ row }) => (
                    <Badge variant={VARIANTE_SEVERIDAD[row.original.severidad]}>
                        {t(
                            `catalogoFiscal.severidades.${row.original.severidad}`,
                        )}
                    </Badge>
                ),
                filterFn: filtroIncluye,
            },
            comunes.validacion,
            comunes.acciones,
        ],
        [comunes, t],
    );

    return (
        <>
            <DataTable
                columns={columnas}
                data={reglas}
                initialPageSize={20}
                searchPlaceholder={t('catalogoFiscal.buscar')}
                emptyMessage={t('catalogoFiscal.sinResultados')}
                facetedFilters={[
                    {
                        columnId: 'severidad',
                        title: t('catalogoFiscal.columnas.severidad'),
                        options: SEVERIDADES.map((s) => ({
                            label: t(`catalogoFiscal.severidades.${s}`),
                            value: s,
                        })),
                    },
                    filtroValidacion,
                ]}
            />

            <DetallePieza
                abierto={seleccionada !== null}
                onCerrar={() => setSeleccionada(null)}
                titulo={seleccionada?.codigo ?? ''}
                descripcion={seleccionada?.descripcion}
            >
                {seleccionada ? (
                    <>
                        <dl className="grid gap-4">
                            <DatoDetalle
                                etiqueta={t(
                                    'catalogoFiscal.columnas.severidad',
                                )}
                            >
                                {t(
                                    `catalogoFiscal.severidades.${seleccionada.severidad}`,
                                )}{' '}
                                —{' '}
                                {t(
                                    `catalogoFiscal.severidadesAyuda.${seleccionada.severidad}`,
                                )}
                            </DatoDetalle>
                            <DatoDetalle
                                etiqueta={t('catalogoFiscal.reglas.estado')}
                            >
                                {seleccionada.implementada
                                    ? t('catalogoFiscal.reglas.implementada')
                                    : t('catalogoFiscal.reglas.noImplementada')}
                            </DatoDetalle>
                        </dl>
                        <FuenteIrs pieza={seleccionada} />
                    </>
                ) : null}
            </DetallePieza>
        </>
    );
}
