import { Deferred, Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Lock, Users } from 'lucide-react';
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
import type { CatalogoFiscalPageProps, PreguntaCatalogo } from '@/types';

const BLOQUES = ['A', 'B', 'C', 'D', 'E', 'F', 'G'] as const;
const NIVELES = ['entrada', 'seguimiento', 'no_se_pregunta'] as const;

type Props = CatalogoFiscalPageProps & { preguntas?: PreguntaCatalogo[] };

export default function CatalogoPreguntas({
    anio,
    puedeEditar,
    preguntas,
}: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={`${t('catalogoFiscal.pestanas.preguntas')} ${anio}`} />
            <p className="mb-4 text-sm text-muted-foreground">
                {t('catalogoFiscal.ayudaPestana.preguntas')}
            </p>
            <Deferred
                data="preguntas"
                fallback={<TablaSkeleton />}
                rescue={<CatalogoError />}
            >
                {preguntas && preguntas.length > 0 ? (
                    <TablaPreguntas preguntas={preguntas} />
                ) : (
                    <CatalogoVacio puedeEditar={puedeEditar} />
                )}
            </Deferred>
        </>
    );
}

function TablaPreguntas({ preguntas }: { preguntas: PreguntaCatalogo[] }) {
    const { t } = useTranslation();
    const [seleccionada, setSeleccionada] = useState<PreguntaCatalogo | null>(
        null,
    );
    const comunes = useColumnasComunes<PreguntaCatalogo>(setSeleccionada);
    const filtroValidacion = useFiltroValidacion();

    const columnas = useMemo<ColumnDef<PreguntaCatalogo>[]>(
        () => [
            comunes.codigo,
            {
                accessorKey: 'texto',
                id: 'pregunta',
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('catalogoFiscal.columnas.pregunta')}
                    />
                ),
                cell: ({ row }) => (
                    <div className="flex max-w-xl items-start gap-2 whitespace-normal">
                        <span className="line-clamp-2 whitespace-normal">
                            {row.original.texto}
                        </span>
                        {row.original.por_persona ? (
                            <Users
                                className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                aria-label={t(
                                    'catalogoFiscal.preguntas.porPersona',
                                )}
                            />
                        ) : null}
                        {row.original.sensible ? (
                            <Lock
                                className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                aria-label={t(
                                    'catalogoFiscal.preguntas.sensible',
                                )}
                            />
                        ) : null}
                    </div>
                ),
            },
            {
                accessorKey: 'bloque',
                id: 'bloque',
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('catalogoFiscal.columnas.bloque')}
                    />
                ),
                cell: ({ row }) =>
                    t(`catalogoFiscal.bloques.${row.original.bloque}`),
                filterFn: filtroIncluye,
            },
            {
                accessorKey: 'nivel',
                id: 'nivel',
                header: t('catalogoFiscal.columnas.nivel'),
                cell: ({ row }) => (
                    <Badge variant="secondary">
                        {t(`catalogoFiscal.niveles.${row.original.nivel}`)}
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
                data={preguntas}
                initialPageSize={20}
                searchPlaceholder={t('catalogoFiscal.buscar')}
                emptyMessage={t('catalogoFiscal.sinResultados')}
                facetedFilters={[
                    {
                        columnId: 'bloque',
                        title: t('catalogoFiscal.columnas.bloque'),
                        options: BLOQUES.map((b) => ({
                            label: t(`catalogoFiscal.bloques.${b}`),
                            value: b,
                        })),
                    },
                    {
                        columnId: 'nivel',
                        title: t('catalogoFiscal.columnas.nivel'),
                        options: NIVELES.map((n) => ({
                            label: t(`catalogoFiscal.niveles.${n}`),
                            value: n,
                        })),
                    },
                    filtroValidacion,
                ]}
            />

            <DetallePieza
                abierto={seleccionada !== null}
                onCerrar={() => setSeleccionada(null)}
                titulo={
                    seleccionada
                        ? `${seleccionada.codigo} · ${t(`catalogoFiscal.bloques.${seleccionada.bloque}`)}`
                        : ''
                }
                descripcion={seleccionada?.texto}
            >
                {seleccionada ? (
                    <DetallePregunta pregunta={seleccionada} />
                ) : null}
            </DetallePieza>
        </>
    );
}

function DetallePregunta({ pregunta }: { pregunta: PreguntaCatalogo }) {
    const { t } = useTranslation();

    return (
        <>
            <dl className="grid gap-4">
                <DatoDetalle etiqueta={t('catalogoFiscal.preguntas.ayuda')}>
                    {pregunta.ayuda}
                </DatoDetalle>
                <DatoDetalle etiqueta={t('catalogoFiscal.preguntas.respuesta')}>
                    {t(
                        `catalogoFiscal.tiposRespuesta.${pregunta.tipo_respuesta}`,
                    )}
                    {pregunta.opciones.length > 0
                        ? `: ${pregunta.opciones.join(' / ')}`
                        : ''}
                </DatoDetalle>
                <DatoDetalle etiqueta={t('catalogoFiscal.columnas.nivel')}>
                    {t(`catalogoFiscal.niveles.${pregunta.nivel}`)}
                    {pregunta.pantalla
                        ? ` · ${t('catalogoFiscal.preguntas.pantalla', { numero: pregunta.pantalla })}`
                        : ''}
                </DatoDetalle>
                <DatoDetalle
                    etiqueta={t('catalogoFiscal.preguntas.cuandoSeMuestra')}
                >
                    {pregunta.condicion
                        ? describirCondicion(pregunta.condicion, t)
                        : null}
                </DatoDetalle>
                <DatoDetalle etiqueta={t('catalogoFiscal.preguntas.efecto')}>
                    {pregunta.efecto}
                </DatoDetalle>
                <DatoDetalle etiqueta={t('catalogoFiscal.preguntas.abre')}>
                    {pregunta.activa.length > 0 ? (
                        <ul className="flex flex-wrap gap-1">
                            {pregunta.activa.map((a) => (
                                <li key={`${a.tipo}:${a.codigo}`}>
                                    <Badge variant="outline">
                                        {t(
                                            `catalogoFiscal.objetivos.${a.tipo}`,
                                        )}{' '}
                                        · {a.codigo}
                                    </Badge>
                                </li>
                            ))}
                        </ul>
                    ) : null}
                </DatoDetalle>
                <DatoDetalle etiqueta={t('catalogoFiscal.preguntas.notas')}>
                    {[
                        pregunta.por_persona
                            ? t('catalogoFiscal.preguntas.porPersona')
                            : null,
                        pregunta.sensible
                            ? t('catalogoFiscal.preguntas.sensible')
                            : null,
                    ]
                        .filter(Boolean)
                        .join(' · ') || null}
                </DatoDetalle>
            </dl>
            <FuenteIrs pieza={pregunta} />
        </>
    );
}

/** {"pregunta":"B1","es":"casado"} → "B1 = casado"; todas → "y"; alguna → "o". */
function describirCondicion(
    condicion: Record<string, unknown>,
    t: (clave: string) => string,
): string {
    if ('pregunta' in condicion) {
        const valor = Array.isArray(condicion.es)
            ? condicion.es.join(', ')
            : String(condicion.es);

        return `${String(condicion.pregunta)} = ${valor}`;
    }

    const [clave, lista] = Object.entries(condicion)[0] ?? [];
    const union =
        clave === 'todas'
            ? t('catalogoFiscal.preguntas.y')
            : t('catalogoFiscal.preguntas.o');

    return Array.isArray(lista)
        ? lista
              .map((c) => describirCondicion(c as Record<string, unknown>, t))
              .join(` ${union} `)
        : '';
}
