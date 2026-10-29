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
import type { CatalogoFiscalPageProps, DocumentoCatalogo } from '@/types';

const GRUPOS = [
    'identidad',
    'trabajo',
    'negocio',
    'jubilacion',
    'inversiones',
    'otros_ingresos',
    'gastos',
    'situaciones',
    'pagos',
] as const;

type Props = CatalogoFiscalPageProps & { documentos?: DocumentoCatalogo[] };

export default function CatalogoDocumentos({
    anio,
    puedeEditar,
    documentos,
}: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head
                title={`${t('catalogoFiscal.pestanas.documentos')} ${anio}`}
            />
            <p className="mb-4 text-sm text-muted-foreground">
                {t('catalogoFiscal.ayudaPestana.documentos')}
            </p>
            <Deferred
                data="documentos"
                fallback={<TablaSkeleton />}
                rescue={<CatalogoError />}
            >
                {documentos && documentos.length > 0 ? (
                    <TablaDocumentos documentos={documentos} />
                ) : (
                    <CatalogoVacio puedeEditar={puedeEditar} />
                )}
            </Deferred>
        </>
    );
}

function TablaDocumentos({ documentos }: { documentos: DocumentoCatalogo[] }) {
    const { t } = useTranslation();
    const [seleccionado, setSeleccionado] = useState<DocumentoCatalogo | null>(
        null,
    );
    const comunes = useColumnasComunes<DocumentoCatalogo>(setSeleccionado);
    const filtroValidacion = useFiltroValidacion();

    const columnas = useMemo<ColumnDef<DocumentoCatalogo>[]>(
        () => [
            comunes.codigo,
            {
                accessorKey: 'nombre',
                id: 'documento',
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('catalogoFiscal.columnas.documento')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="line-clamp-2 max-w-md whitespace-normal">
                        {row.original.nombre}
                    </span>
                ),
            },
            {
                accessorKey: 'grupo',
                id: 'grupo',
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('catalogoFiscal.columnas.grupo')}
                    />
                ),
                cell: ({ row }) =>
                    t(`catalogoFiscal.gruposDocumento.${row.original.grupo}`),
                filterFn: filtroIncluye,
            },
            {
                id: 'activado_por',
                accessorFn: (d) => d.activado_por.join(' '),
                header: t('catalogoFiscal.columnas.loActiva'),
                cell: ({ row }) => (
                    <span className="font-mono text-xs">
                        {row.original.activado_por.join(', ') || '—'}
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
                data={documentos}
                initialPageSize={20}
                searchPlaceholder={t('catalogoFiscal.buscar')}
                emptyMessage={t('catalogoFiscal.sinResultados')}
                facetedFilters={[
                    {
                        columnId: 'grupo',
                        title: t('catalogoFiscal.columnas.grupo'),
                        options: GRUPOS.map((g) => ({
                            label: t(`catalogoFiscal.gruposDocumento.${g}`),
                            value: g,
                        })),
                    },
                    filtroValidacion,
                ]}
            />

            <DetallePieza
                abierto={seleccionado !== null}
                onCerrar={() => setSeleccionado(null)}
                titulo={seleccionado?.nombre ?? ''}
                descripcion={seleccionado?.nombre_oficial ?? undefined}
            >
                {seleccionado ? (
                    <DetalleDocumento documento={seleccionado} />
                ) : null}
            </DetallePieza>
        </>
    );
}

function DetalleDocumento({ documento }: { documento: DocumentoCatalogo }) {
    const { t, i18n } = useTranslation();

    return (
        <>
            <dl className="grid gap-4">
                <DatoDetalle etiqueta={t('catalogoFiscal.columnas.codigo')}>
                    <span className="font-mono">{documento.codigo}</span>
                </DatoDetalle>
                <DatoDetalle etiqueta={t('catalogoFiscal.documentos.emisor')}>
                    {documento.emisor}
                </DatoDetalle>
                <DatoDetalle etiqueta={t('catalogoFiscal.documentos.llegaA')}>
                    {documento.fecha_llegada
                        ? new Date(
                              `${documento.fecha_llegada}T12:00:00`,
                          ).toLocaleDateString(i18n.language, {
                              dateStyle: 'long',
                          })
                        : null}
                </DatoDetalle>
                <DatoDetalle
                    etiqueta={t('catalogoFiscal.documentos.siNoLlega')}
                >
                    {documento.si_no_llega}
                </DatoDetalle>
                <DatoDetalle etiqueta={t('catalogoFiscal.documentos.unoPor')}>
                    {t(`catalogoFiscal.unoPor.${documento.uno_por}`)}
                </DatoDetalle>
                <DatoDetalle etiqueta={t('catalogoFiscal.documentos.formatos')}>
                    {documento.formatos
                        .map((f) => f.toUpperCase())
                        .join(', ') || null}
                </DatoDetalle>
                <DatoDetalle etiqueta={t('catalogoFiscal.columnas.loActiva')}>
                    {documento.activado_por.length > 0 ? (
                        <span className="font-mono">
                            {documento.activado_por.join(', ')}
                        </span>
                    ) : null}
                </DatoDetalle>
            </dl>

            {documento.casillas.length > 0 ? (
                <section
                    className="space-y-2"
                    aria-labelledby="casillas-titulo"
                >
                    <h3 id="casillas-titulo" className="text-sm font-medium">
                        {t('catalogoFiscal.documentos.casillas')}
                    </h3>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>
                                    {t('catalogoFiscal.documentos.casilla')}
                                </TableHead>
                                <TableHead>
                                    {t('catalogoFiscal.documentos.descripcion')}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {documento.casillas.map((c) => (
                                <TableRow key={c.casilla}>
                                    <TableCell className="font-mono text-xs">
                                        {c.casilla}
                                    </TableCell>
                                    <TableCell className="whitespace-normal">
                                        {c.descripcion}
                                        {c.clave ? (
                                            <Badge
                                                variant="secondary"
                                                className="ml-2"
                                            >
                                                {t(
                                                    'catalogoFiscal.documentos.clave',
                                                )}
                                            </Badge>
                                        ) : null}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </section>
            ) : null}

            <FuenteIrs pieza={documento} />
        </>
    );
}
