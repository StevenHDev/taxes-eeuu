import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { CircleHelp, PartyPopper } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ResolverExcepcionDialog } from '@/components/excepciones/resolver-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { DataTableColumnHeader } from '@/components/ui/data-table-column-header';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { dashboard } from '@/routes';
import { index as clientesIndex, show as clienteShow } from '@/routes/clientes';
import { index as excepcionesIndex } from '@/routes/clientes/excepciones';
import type {
    EstadoExcepcion,
    Excepcion,
    FiltrosExcepciones,
    SeveridadExcepcion,
} from '@/types/excepciones';

const TODOS = 'todos';

const SEVERIDAD_VARIANT: Record<
    SeveridadExcepcion,
    'destructive' | 'secondary' | 'outline'
> = {
    bloqueante: 'destructive',
    revision: 'secondary',
    informativa: 'outline',
};

const SEVERIDADES: SeveridadExcepcion[] = [
    'bloqueante',
    'revision',
    'informativa',
];
const ESTADOS: EstadoExcepcion[] = ['abierta', 'consultada'];

function useColumns(): ColumnDef<Excepcion>[] {
    const { t, i18n } = useTranslation();
    const formatoFecha = new Intl.DateTimeFormat(i18n.language, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });

    return [
        {
            id: 'cliente',
            accessorFn: (e) => e.cliente.nombre,
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title={t('excepciones.columnas.cliente')}
                />
            ),
            cell: ({ row }) => (
                <Link
                    href={clienteShow(row.original.cliente.id)}
                    className="font-medium underline-offset-4 hover:underline focus-visible:underline"
                >
                    {row.original.cliente.nombre}
                </Link>
            ),
            enableHiding: false,
        },
        {
            id: 'documento',
            accessorFn: (e) => e.item?.nombre ?? '',
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title={t('excepciones.columnas.documento')}
                />
            ),
            cell: ({ row }) => {
                const { item, casilla } = row.original;

                if (item === null) {
                    return (
                        <span aria-label={t('excepciones.sinDocumento')}>
                            —
                        </span>
                    );
                }

                return (
                    <div>
                        <div className="text-sm">{item.nombre}</div>
                        {casilla ? (
                            <div className="text-xs text-muted-foreground">
                                {t('excepciones.casilla', { casilla })}
                            </div>
                        ) : null}
                    </div>
                );
            },
        },
        {
            id: 'regla',
            accessorFn: (e) => `${e.regla.codigo} ${e.regla.descripcion}`,
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title={t('excepciones.columnas.regla')}
                />
            ),
            cell: ({ row }) => <ReglaCelda excepcion={row.original} />,
        },
        {
            id: 'severidad',
            accessorFn: (e) => SEVERIDADES.indexOf(e.severidad),
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title={t('excepciones.columnas.severidad')}
                />
            ),
            cell: ({ row }) => (
                <div className="flex flex-col items-start gap-1">
                    <Badge variant={SEVERIDAD_VARIANT[row.original.severidad]}>
                        {t(`excepciones.severidad.${row.original.severidad}`)}
                    </Badge>
                    {row.original.estado === 'consultada' ? (
                        <span className="text-xs text-muted-foreground">
                            {t('excepciones.estado.consultada')}
                        </span>
                    ) : null}
                </div>
            ),
        },
        {
            id: 'fecha',
            accessorFn: (e) => e.creada_en,
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title={t('excepciones.columnas.fecha')}
                />
            ),
            cell: ({ row }) => (
                <time
                    dateTime={row.original.creada_en}
                    className="text-sm whitespace-nowrap text-muted-foreground"
                >
                    {formatoFecha.format(new Date(row.original.creada_en))}
                </time>
            ),
        },
        {
            id: 'acciones',
            header: () => (
                <span className="sr-only">
                    {t('excepciones.columnas.acciones')}
                </span>
            ),
            cell: ({ row }) => <Acciones excepcion={row.original} />,
            enableSorting: false,
            enableHiding: false,
        },
    ];
}

/** La descripción de una regla puede ser un párrafo (R-03): en la tabla va la primera frase; la completa, en el título. */
function primeraFrase(texto: string): string {
    const corte = texto.search(/\.\s/);

    return corte === -1 ? texto : texto.slice(0, corte + 1);
}

/** Regla y, en R-03, el motivo de la confianza en palabras con su explicación. */
function ReglaCelda({ excepcion }: { excepcion: Excepcion }) {
    const { t } = useTranslation();
    const { regla, motivo_confianza: motivo, enlaces } = excepcion;

    return (
        <div className="w-72 space-y-1 whitespace-normal">
            <div className="line-clamp-3 text-sm" title={excepcion.caso}>
                <span className="font-mono text-xs font-medium">
                    {regla.codigo}
                </span>{' '}
                {excepcion.caso}
            </div>
            <div
                className="line-clamp-2 text-xs text-muted-foreground"
                title={regla.descripcion}
            >
                {t('excepciones.queRevisa')} {primeraFrase(regla.descripcion)}
            </div>
            {excepcion.que_hacer ? (
                <div className="text-xs text-muted-foreground">
                    <span className="font-medium">
                        {t('excepciones.queHacer')}
                    </span>{' '}
                    {excepcion.que_hacer}
                </div>
            ) : null}
            {motivo ? (
                <div className="space-y-0.5">
                    <div className="flex items-center gap-1">
                        <Badge variant="outline">{motivo.titulo}</Badge>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <button
                                    type="button"
                                    className="rounded-full text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                    aria-label={t('excepciones.motivo.porQue')}
                                >
                                    <CircleHelp className="size-4" />
                                </button>
                            </TooltipTrigger>
                            <TooltipContent className="max-w-xs">
                                {motivo.explicacion}
                            </TooltipContent>
                        </Tooltip>
                    </div>
                    {enlaces.corregir ? (
                        <Link
                            href={enlaces.corregir}
                            className="text-xs text-muted-foreground underline-offset-4 hover:text-foreground hover:underline focus-visible:underline"
                        >
                            {t('excepciones.motivo.verCalculo')}
                        </Link>
                    ) : null}
                </div>
            ) : null}
        </div>
    );
}

/** Una acción principal por fila (corregir) y las otras dos, secundarias. */
function Acciones({ excepcion }: { excepcion: Excepcion }) {
    const { t } = useTranslation();
    const { enlaces } = excepcion;

    return (
        <div className="flex w-44 flex-col gap-2 whitespace-normal">
            {enlaces.corregir ? (
                <Button asChild size="sm" className="w-full">
                    <Link href={enlaces.corregir}>
                        {t('excepciones.acciones.corregir')}
                    </Link>
                </Button>
            ) : (
                <div className="flex flex-col gap-0.5">
                    <Button size="sm" disabled className="w-full">
                        {t('excepciones.acciones.corregir')}
                    </Button>
                    <span className="text-xs text-muted-foreground">
                        {t('excepciones.acciones.sinDocumento')}
                    </span>
                </div>
            )}
            <ResolverExcepcionDialog
                action={enlaces.aceptar}
                campo="nota"
                titulo={t('excepciones.aceptar.titulo')}
                descripcion={t('excepciones.aceptar.descripcion', {
                    regla: excepcion.regla.codigo,
                    cliente: excepcion.cliente.nombre,
                    caso: excepcion.caso,
                })}
                etiqueta={t('excepciones.aceptar.etiqueta')}
                placeholder={t('excepciones.aceptar.placeholder')}
                enviar={t('excepciones.aceptar.enviar')}
                trigger={
                    <Button size="sm" variant="outline" className="w-full">
                        {t('excepciones.acciones.aceptar')}
                    </Button>
                }
            />
            {enlaces.consultar ? (
                <ResolverExcepcionDialog
                    action={enlaces.consultar}
                    campo="texto"
                    titulo={t('excepciones.consultar.titulo')}
                    descripcion={t('excepciones.consultar.descripcion', {
                        cliente: excepcion.cliente.nombre,
                    })}
                    etiqueta={t('excepciones.consultar.etiqueta')}
                    placeholder={t('excepciones.consultar.placeholder')}
                    enviar={t('excepciones.consultar.enviar')}
                    trigger={
                        <Button size="sm" variant="ghost" className="w-full">
                            {t('excepciones.acciones.consultar')}
                        </Button>
                    }
                />
            ) : (
                <Button
                    size="sm"
                    variant="ghost"
                    disabled
                    title={t('excepciones.acciones.consultarNo')}
                >
                    {t('excepciones.acciones.consultar')}
                </Button>
            )}
        </div>
    );
}

function TablaSkeleton() {
    const { t } = useTranslation();

    return (
        <div
            className="space-y-2"
            role="status"
            aria-label={t('excepciones.cargando')}
        >
            {Array.from({ length: 5 }, (_, i) => (
                <Skeleton key={i} className="h-14 w-full" />
            ))}
        </div>
    );
}

function Vacio({ conFiltros }: { conFiltros: boolean }) {
    const { t } = useTranslation();

    return (
        <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed px-4 py-12 text-center">
            <PartyPopper className="size-8 text-muted-foreground" />
            <h2 className="text-base font-medium">
                {t('excepciones.vacio.titulo')}
            </h2>
            <p className="max-w-md text-sm text-muted-foreground">
                {conFiltros
                    ? t('excepciones.vacio.conFiltros')
                    : t('excepciones.vacio.descripcion')}
            </p>
        </div>
    );
}

export default function ExcepcionesIndex({
    excepciones,
    filtros,
    clientes,
}: {
    excepciones: Excepcion[];
    filtros: FiltrosExcepciones;
    clientes: { id: number; nombre: string }[];
}) {
    const { t } = useTranslation();
    const columns = useColumns();
    const [cargando, setCargando] = useState(false);

    const conFiltros =
        filtros.severidad !== null ||
        filtros.estado !== null ||
        filtros.cliente !== null;

    // Los filtros viajan al servidor por query string.
    const filtrar = (
        cambio: Partial<Record<keyof FiltrosExcepciones, string>>,
    ) => {
        const siguiente = {
            severidad: filtros.severidad ?? undefined,
            estado: filtros.estado ?? undefined,
            cliente: filtros.cliente ?? undefined,
            ...Object.fromEntries(
                Object.entries(cambio).map(([k, v]) => [
                    k,
                    v === TODOS ? undefined : v,
                ]),
            ),
        };

        router.get(excepcionesIndex.url(), siguiente, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setCargando(true),
            onFinish: () => setCargando(false),
        });
    };

    return (
        <>
            <Head title={t('excepciones.head.title')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-1">
                    <h1 className="text-xl font-semibold">
                        {t('excepciones.heading')}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {t('excepciones.subheading')}
                    </p>
                </div>

                <div className="flex flex-wrap items-end gap-3">
                    <FiltroSelect
                        id="filtro-severidad"
                        etiqueta={t('excepciones.filtros.severidad')}
                        valor={filtros.severidad ?? TODOS}
                        todos={t('excepciones.filtros.todas')}
                        opciones={SEVERIDADES.map((s) => ({
                            valor: s,
                            etiqueta: t(`excepciones.severidad.${s}`),
                        }))}
                        onChange={(v) => filtrar({ severidad: v })}
                    />
                    <FiltroSelect
                        id="filtro-estado"
                        etiqueta={t('excepciones.filtros.estado')}
                        valor={filtros.estado ?? TODOS}
                        todos={t('excepciones.filtros.todos')}
                        opciones={ESTADOS.map((s) => ({
                            valor: s,
                            etiqueta: t(`excepciones.estado.${s}`),
                        }))}
                        onChange={(v) => filtrar({ estado: v })}
                    />
                    <FiltroSelect
                        id="filtro-cliente"
                        etiqueta={t('excepciones.filtros.cliente')}
                        valor={
                            filtros.cliente ? String(filtros.cliente) : TODOS
                        }
                        todos={t('excepciones.filtros.todos')}
                        opciones={clientes.map((c) => ({
                            valor: String(c.id),
                            etiqueta: c.nombre,
                        }))}
                        onChange={(v) => filtrar({ cliente: v })}
                    />
                    {conFiltros ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                filtrar({
                                    severidad: TODOS,
                                    estado: TODOS,
                                    cliente: TODOS,
                                })
                            }
                        >
                            {t('excepciones.filtros.limpiar')}
                        </Button>
                    ) : null}
                </div>

                {cargando ? (
                    <TablaSkeleton />
                ) : excepciones.length === 0 ? (
                    <Vacio conFiltros={conFiltros} />
                ) : (
                    <DataTable
                        columns={columns}
                        data={excepciones}
                        searchPlaceholder={t('excepciones.buscar')}
                        emptyMessage={t('excepciones.vacio.titulo')}
                    />
                )}
            </div>
        </>
    );
}

function FiltroSelect({
    id,
    etiqueta,
    valor,
    todos,
    opciones,
    onChange,
}: {
    id: string;
    etiqueta: string;
    valor: string;
    todos: string;
    opciones: { valor: string; etiqueta: string }[];
    onChange: (valor: string) => void;
}) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id} className="text-xs text-muted-foreground">
                {etiqueta}
            </Label>
            <Select value={valor} onValueChange={onChange}>
                <SelectTrigger id={id} className="w-full min-w-44 sm:w-52">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={TODOS}>{todos}</SelectItem>
                    {opciones.map((o) => (
                        <SelectItem key={o.valor} value={o.valor}>
                            {o.etiqueta}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

ExcepcionesIndex.layout = {
    breadcrumbs: [
        { title: 'nav.dashboard', href: dashboard() },
        { title: 'nav.clients', href: clientesIndex() },
        { title: 'nav.excepciones', href: excepcionesIndex() },
    ],
};
