import { Deferred, Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { Editor } from '@/components/catalogo-fiscal/acciones-pieza';
import { AccionesPieza } from '@/components/catalogo-fiscal/acciones-pieza';
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
import { FormularioParametro } from '@/components/catalogo-fiscal/formularios/formulario-parametro';
import { FuenteIrs } from '@/components/catalogo-fiscal/fuente-irs';
import { TablaSkeleton } from '@/components/catalogo-fiscal/tabla-skeleton';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/ui/data-table';
import { DataTableColumnHeader } from '@/components/ui/data-table-column-header';
import type { CatalogoFiscalPageProps, ParametroCatalogo } from '@/types';

const GRUPOS = [
    'deduccion_estandar',
    'deducciones_detalladas',
    'ajustes_ingreso',
    'deducciones_schedule_1a',
    'creditos',
    'topes_umbrales',
    'tablas',
] as const;

type Props = CatalogoFiscalPageProps & { parametros?: ParametroCatalogo[] };

export default function CatalogoParametros({
    anio,
    estadoAnio,
    puedeEditar,
    puedeValidar,
    parametros,
}: Props) {
    const { t } = useTranslation();
    // Fuera de <Deferred>: al guardar (o si el servidor devuelve errores) la
    // tabla se vuelve a cargar, y el diálogo y el detalle no deben cerrarse con ella.
    const editor: Editor | null = puedeEditar
        ? { anio, vigente: estadoAnio === 'vigente' }
        : null;
    const [seleccionado, setSeleccionado] = useState<ParametroCatalogo | null>(
        null,
    );
    const [editando, setEditando] = useState<
        ParametroCatalogo | 'nueva' | null
    >(null);
    const cerrarTodo = () => {
        setEditando(null);
        setSeleccionado(null);
    };

    return (
        <>
            <Head
                title={`${t('catalogoFiscal.pestanas.parametros')} ${anio}`}
            />
            <p className="mb-4 text-sm text-muted-foreground">
                {t('catalogoFiscal.ayudaPestana.parametros')}
            </p>
            <Deferred
                data="parametros"
                fallback={<TablaSkeleton />}
                rescue={<CatalogoError />}
            >
                {parametros && parametros.length > 0 ? (
                    <TablaParametros
                        parametros={parametros}
                        onVer={setSeleccionado}
                    />
                ) : (
                    <CatalogoVacio puedeEditar={puedeEditar} />
                )}
            </Deferred>

            <DetallePieza
                acciones={
                    (editor || puedeValidar) && seleccionado ? (
                        <AccionesPieza
                            anio={anio}
                            tipo="parametros"
                            pieza={seleccionado}
                            onEditar={
                                editor
                                    ? () => setEditando(seleccionado)
                                    : undefined
                            }
                            onHecho={cerrarTodo}
                            sePuedeDesactivar={false}
                        />
                    ) : null
                }
                abierto={seleccionado !== null}
                onCerrar={() => setSeleccionado(null)}
                titulo={seleccionado?.nombre ?? ''}
                descripcion={
                    seleccionado ? (
                        <span className="font-mono text-xs">
                            {seleccionado.codigo}
                        </span>
                    ) : undefined
                }
            >
                {seleccionado ? (
                    <>
                        <dl className="grid gap-4">
                            <DatoDetalle
                                etiqueta={t('catalogoFiscal.columnas.valor')}
                            >
                                <ValorParametro
                                    parametro={seleccionado}
                                    completo
                                />
                            </DatoDetalle>
                            <DatoDetalle
                                etiqueta={t(
                                    'catalogoFiscal.parametros.condicion',
                                )}
                            >
                                {seleccionado.condicion}
                            </DatoDetalle>
                            <DatoDetalle
                                etiqueta={t(
                                    'catalogoFiscal.columnas.dondeSeUsa',
                                )}
                            >
                                {seleccionado.formulario_linea}
                            </DatoDetalle>
                        </dl>
                        <FuenteIrs pieza={seleccionado} />
                    </>
                ) : null}
            </DetallePieza>

            {editor && editando !== null && editando !== 'nueva' ? (
                <FormularioParametro
                    key={editando.id}
                    anio={editor.anio}
                    anioVigente={editor.vigente}
                    parametro={editando}
                    abierto
                    onCerrar={() => setEditando(null)}
                    onGuardado={cerrarTodo}
                />
            ) : null}
        </>
    );
}

function TablaParametros({
    parametros,
    onVer,
}: {
    parametros: ParametroCatalogo[];
    onVer: (pieza: ParametroCatalogo) => void;
}) {
    const { t } = useTranslation();
    const comunes = useColumnasComunes<ParametroCatalogo>(onVer);
    const filtroValidacion = useFiltroValidacion();

    const columnas = useMemo<ColumnDef<ParametroCatalogo>[]>(
        () => [
            {
                accessorKey: 'nombre',
                id: 'parametro',
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('catalogoFiscal.columnas.parametro')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="line-clamp-2 max-w-md whitespace-normal">
                        {row.original.nombre}
                    </span>
                ),
                enableHiding: false,
            },
            {
                id: 'valor',
                accessorFn: (p) =>
                    p.valor === null ? '' : JSON.stringify(p.valor),
                header: t('catalogoFiscal.columnas.valor'),
                cell: ({ row }) => <ValorParametro parametro={row.original} />,
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
                    row.original.grupo
                        ? t(
                              `catalogoFiscal.gruposParametro.${row.original.grupo}`,
                          )
                        : '—',
                filterFn: filtroIncluye,
            },
            {
                accessorKey: 'formulario_linea',
                id: 'formulario_linea',
                header: t('catalogoFiscal.columnas.dondeSeUsa'),
                cell: ({ row }) => (
                    <span className="text-sm text-muted-foreground">
                        {row.original.formulario_linea ?? '—'}
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
                data={parametros}
                initialPageSize={50}
                searchPlaceholder={t('catalogoFiscal.buscar')}
                emptyMessage={t('catalogoFiscal.sinResultados')}
                facetedFilters={[
                    {
                        columnId: 'grupo',
                        title: t('catalogoFiscal.columnas.grupo'),
                        options: GRUPOS.map((g) => ({
                            label: t(`catalogoFiscal.gruposParametro.${g}`),
                            value: g,
                        })),
                    },
                    filtroValidacion,
                ]}
            />
        </>
    );
}

/**
 * Muestra el valor según su tipo. Un parámetro sin cifra dice "Pendiente"
 * (con texto, no solo color): la plataforma nunca inventa un monto.
 */
function ValorParametro({
    parametro,
    completo = false,
}: {
    parametro: ParametroCatalogo;
    completo?: boolean;
}) {
    const { t, i18n } = useTranslation();
    const { valor, tipo } = parametro;

    if (valor === null || valor === undefined) {
        return (
            <Badge
                variant="outline"
                className="border-amber-600/40 text-amber-800 dark:text-amber-300"
            >
                {t('catalogoFiscal.parametros.pendiente')}
            </Badge>
        );
    }

    if (tipo === 'monto' && typeof valor === 'number') {
        return (
            <span className="tabular-nums">
                {new Intl.NumberFormat(i18n.language, {
                    style: 'currency',
                    currency: 'USD',
                    maximumFractionDigits: 2,
                }).format(valor)}
            </span>
        );
    }

    if (tipo === 'porcentaje' && typeof valor === 'number') {
        return (
            <span className="tabular-nums">
                {new Intl.NumberFormat(i18n.language, {
                    style: 'percent',
                    maximumFractionDigits: 2,
                }).format(valor)}
            </span>
        );
    }

    if (tipo === 'fecha' && typeof valor === 'string') {
        return (
            <span>
                {new Date(`${valor}T12:00:00`).toLocaleDateString(
                    i18n.language,
                    { dateStyle: 'long' },
                )}
            </span>
        );
    }

    if (tipo === 'tabla' && Array.isArray(valor)) {
        return completo ? (
            <ul className="space-y-1 text-sm tabular-nums">
                {valor.map((tramo, i) => (
                    <li key={i}>{describirTramo(tramo, i18n.language)}</li>
                ))}
            </ul>
        ) : (
            <span className="text-sm text-muted-foreground">
                {t('catalogoFiscal.parametros.tramos', { count: valor.length })}
            </span>
        );
    }

    return <span className="tabular-nums">{String(valor)}</span>;
}

function describirTramo(tramo: unknown, idioma: string): string {
    if (
        tramo &&
        typeof tramo === 'object' &&
        'desde' in tramo &&
        'tasa' in tramo
    ) {
        const { desde, tasa } = tramo as { desde: number; tasa: number };
        const monto = new Intl.NumberFormat(idioma, {
            style: 'currency',
            currency: 'USD',
            maximumFractionDigits: 0,
        }).format(desde);
        const porcentaje = new Intl.NumberFormat(idioma, {
            style: 'percent',
            maximumFractionDigits: 1,
        }).format(tasa);

        return `${monto}+ → ${porcentaje}`;
    }

    return JSON.stringify(tramo);
}
