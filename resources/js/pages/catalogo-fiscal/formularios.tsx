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
import { DataTableColumnHeader } from '@/components/ui/data-table-column-header';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type {
    CatalogoFiscalPageProps,
    FormularioCatalogo,
    LineaCatalogo,
} from '@/types';

const FICHAS = ['completa', 'conector', 'pendiente'] as const;

type Props = CatalogoFiscalPageProps & { formularios?: FormularioCatalogo[] };

export default function CatalogoFormularios({
    anio,
    puedeEditar,
    formularios,
}: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head
                title={`${t('catalogoFiscal.pestanas.formularios')} ${anio}`}
            />
            <p className="mb-4 text-sm text-muted-foreground">
                {t('catalogoFiscal.ayudaPestana.formularios')}
            </p>
            <Deferred
                data="formularios"
                fallback={<TablaSkeleton />}
                rescue={<CatalogoError />}
            >
                {formularios && formularios.length > 0 ? (
                    <TablaFormularios formularios={formularios} />
                ) : (
                    <CatalogoVacio puedeEditar={puedeEditar} />
                )}
            </Deferred>
        </>
    );
}

function TablaFormularios({
    formularios,
}: {
    formularios: FormularioCatalogo[];
}) {
    const { t } = useTranslation();
    const [seleccionado, setSeleccionado] = useState<FormularioCatalogo | null>(
        null,
    );
    const comunes = useColumnasComunes<FormularioCatalogo>(setSeleccionado);
    const filtroValidacion = useFiltroValidacion();

    const columnas = useMemo<ColumnDef<FormularioCatalogo>[]>(
        () => [
            comunes.codigo,
            {
                accessorKey: 'nombre',
                id: 'formulario',
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('catalogoFiscal.columnas.formulario')}
                    />
                ),
                cell: ({ row }) => (
                    <div className="flex max-w-md flex-col whitespace-normal">
                        <span className="font-medium">
                            {row.original.nombre}
                            {row.original.siempre ? (
                                <Badge className="ml-2">
                                    {t('catalogoFiscal.formularios.siempre')}
                                </Badge>
                            ) : null}
                        </span>
                        {row.original.para_que ? (
                            <span className="line-clamp-1 text-xs whitespace-normal text-muted-foreground">
                                {row.original.para_que}
                            </span>
                        ) : null}
                    </div>
                ),
            },
            {
                accessorKey: 'ficha',
                id: 'ficha',
                header: t('catalogoFiscal.columnas.ficha'),
                cell: ({ row }) => (
                    <Badge
                        variant={
                            row.original.ficha === 'pendiente'
                                ? 'outline'
                                : 'secondary'
                        }
                    >
                        {t(`catalogoFiscal.fichas.${row.original.ficha}`)}
                    </Badge>
                ),
                filterFn: filtroIncluye,
            },
            {
                id: 'lineas',
                accessorFn: (f) => f.lineas.length,
                header: t('catalogoFiscal.columnas.lineas'),
                cell: ({ row }) => (
                    <span className="tabular-nums">
                        {row.original.lineas.length}
                    </span>
                ),
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
                data={formularios}
                initialPageSize={30}
                searchPlaceholder={t('catalogoFiscal.buscar')}
                emptyMessage={t('catalogoFiscal.sinResultados')}
                facetedFilters={[
                    {
                        columnId: 'ficha',
                        title: t('catalogoFiscal.columnas.ficha'),
                        options: FICHAS.map((f) => ({
                            label: t(`catalogoFiscal.fichas.${f}`),
                            value: f,
                        })),
                    },
                    filtroValidacion,
                ]}
            />

            <DetallePieza
                abierto={seleccionado !== null}
                onCerrar={() => setSeleccionado(null)}
                titulo={seleccionado?.nombre ?? ''}
                descripcion={seleccionado?.para_que ?? undefined}
            >
                {seleccionado ? (
                    <DetalleFormulario formulario={seleccionado} />
                ) : null}
            </DetallePieza>
        </>
    );
}

function DetalleFormulario({ formulario }: { formulario: FormularioCatalogo }) {
    const { t } = useTranslation();

    return (
        <>
            <dl className="grid gap-4">
                <DatoDetalle etiqueta={t('catalogoFiscal.columnas.ficha')}>
                    {t(`catalogoFiscal.fichas.${formulario.ficha}`)}
                </DatoDetalle>
                <DatoDetalle etiqueta={t('catalogoFiscal.columnas.loActiva')}>
                    {formulario.siempre ? (
                        t('catalogoFiscal.formularios.siempre')
                    ) : formulario.activado_por.length > 0 ? (
                        <span className="font-mono">
                            {formulario.activado_por.join(', ')}
                        </span>
                    ) : (
                        t('catalogoFiscal.formularios.activaOtroFormulario')
                    )}
                </DatoDetalle>
            </dl>

            <section className="space-y-2" aria-labelledby="lineas-titulo">
                <h3 id="lineas-titulo" className="text-sm font-medium">
                    {t('catalogoFiscal.formularios.lineas')}
                </h3>
                {formulario.lineas.length > 0 ? (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>
                                    {t('catalogoFiscal.formularios.linea')}
                                </TableHead>
                                <TableHead>
                                    {t(
                                        'catalogoFiscal.formularios.deDondeSale',
                                    )}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {formulario.lineas.map((l) => (
                                <TableRow key={l.linea}>
                                    <TableCell className="align-top font-mono text-xs">
                                        {l.linea}
                                    </TableCell>
                                    <TableCell className="whitespace-normal">
                                        <p>{l.descripcion}</p>
                                        <p className="text-xs text-muted-foreground">
                                            <OrigenLinea linea={l} />
                                        </p>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        {t('catalogoFiscal.formularios.sinLineas')}
                    </p>
                )}
            </section>

            <FuenteIrs pieza={formulario} />
        </>
    );
}

/** "W-2 casilla 1", "Schedule C línea 31", "respuesta C2" o "cálculo". */
function OrigenLinea({ linea }: { linea: LineaCatalogo }) {
    const { t } = useTranslation();

    if (linea.origen_tipo === 'calculo') {
        return <>{t('catalogoFiscal.origenes.calculo')}</>;
    }

    return (
        <>
            {t(`catalogoFiscal.origenes.${linea.origen_tipo}`)}:{' '}
            <span className="font-mono">{linea.origen.join(' + ')}</span>
        </>
    );
}
