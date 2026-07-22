import { Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { RadialGauge } from '@/components/dashboard/radial-gauge';
import { useInitials } from '@/hooks/use-initials';
import { dashboard } from '@/routes';
import { show as clienteShow, index as clientesIndex } from '@/routes/clientes';
import type { DashboardResumen } from '@/types';

const SOURCE_LABEL: Record<DashboardResumen['actividad_reciente'][number]['source'], string> = {
    agente_ia: 'Agente',
    preparador: 'Preparador',
    administrador: 'Admin',
};

function diaCorto(fecha: string): string {
    return new Date(`${fecha}T00:00:00`).toLocaleDateString('es-AR', {
        weekday: 'short',
    });
}

function fechaHora(fecha: string | null): string {
    if (!fecha) return '—';

    return new Date(fecha).toLocaleString('es-AR', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function StatRow({
    dotClassName,
    label,
    value,
}: {
    dotClassName: string;
    label: string;
    value: number;
}) {
    return (
        <div className="flex items-center justify-between py-2.5">
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                <span className={`size-2 rounded-full ${dotClassName}`} />
                {label}
            </div>
            <span className="text-lg font-semibold tabular-nums">
                {value}
            </span>
        </div>
    );
}

function ActivityChart({
    datos,
}: {
    datos: DashboardResumen['actividad_por_dia'];
}) {
    const max = Math.max(1, ...datos.map((d) => d.cantidad));

    return (
        <div className="flex h-32 items-end gap-2">
            {datos.map((d) => (
                <div
                    key={d.fecha}
                    className="flex flex-1 flex-col items-center gap-2"
                >
                    <div className="flex h-24 w-full items-end">
                        <div
                            className="w-full rounded-md bg-primary transition-[height] duration-500"
                            style={{
                                height: `${Math.max(4, (d.cantidad / max) * 100)}%`,
                            }}
                            title={`${d.cantidad} campo(s)`}
                        />
                    </div>
                    <span className="text-xs text-muted-foreground capitalize">
                        {diaCorto(d.fecha)}
                    </span>
                </div>
            ))}
        </div>
    );
}

export default function Dashboard({
    resumen,
}: {
    resumen: DashboardResumen | null;
}) {
    const getInitials = useInitials();

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                {resumen ? (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Card className="lg:col-span-2">
                            <CardHeader>
                                <CardTitle className="text-sm font-normal text-muted-foreground">
                                    Clientes
                                </CardTitle>
                                <p className="text-4xl font-semibold tabular-nums text-foreground">
                                    {resumen.total}
                                </p>
                            </CardHeader>
                            <CardContent>
                                <p className="mb-3 text-xs text-muted-foreground">
                                    Campos recibidos por día · últimos 7 días
                                </p>
                                <ActivityChart datos={resumen.actividad_por_dia} />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm font-normal text-muted-foreground">
                                    Formularios
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <StatRow
                                    dotClassName="bg-secondary-foreground/40"
                                    label="Sin iniciar"
                                    value={resumen.sin_iniciar}
                                />
                                <StatRow
                                    dotClassName="bg-primary/60"
                                    label="En progreso"
                                    value={resumen.en_progreso}
                                />
                                <StatRow
                                    dotClassName="bg-primary"
                                    label="Completos"
                                    value={resumen.completo}
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm font-normal text-muted-foreground">
                                    Progreso de campos
                                </CardTitle>
                                <p className="text-2xl font-semibold tabular-nums">
                                    {resumen.campos_recibidos_porcentaje}%
                                </p>
                            </CardHeader>
                            <CardContent>
                                <div className="h-2.5 w-full overflow-hidden rounded-full bg-secondary">
                                    <div
                                        className="h-full rounded-full bg-primary transition-[width] duration-500"
                                        style={{
                                            width: `${resumen.campos_recibidos_porcentaje}%`,
                                        }}
                                    />
                                </div>
                                <p className="mt-2 text-xs text-muted-foreground">
                                    Campos individuales recibidos sobre el
                                    total esperado.
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm font-normal text-muted-foreground">
                                    Formularios completos
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-col items-center">
                                <RadialGauge
                                    percentage={
                                        resumen.formas_completas_porcentaje
                                    }
                                />
                                <p className="mt-2 text-center text-xs text-muted-foreground">
                                    De las formas ya iniciadas, cuántas
                                    llegaron al 100%.
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm font-normal text-muted-foreground">
                                    Clientes por formulario
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {resumen.distribucion_por_forma.length ===
                                0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Todavía no hay formas iniciadas.
                                    </p>
                                ) : (
                                    resumen.distribucion_por_forma.map(
                                        (d) => {
                                            const max = Math.max(
                                                1,
                                                ...resumen.distribucion_por_forma.map(
                                                    (x) => x.cantidad,
                                                ),
                                            );

                                            return (
                                                <div key={d.forma}>
                                                    <div className="mb-1 flex justify-between text-xs">
                                                        <span className="text-muted-foreground">
                                                            {d.forma_label}
                                                        </span>
                                                        <span className="font-medium tabular-nums">
                                                            {d.cantidad}
                                                        </span>
                                                    </div>
                                                    <div className="h-1.5 w-full overflow-hidden rounded-full bg-secondary">
                                                        <div
                                                            className="h-full rounded-full bg-primary"
                                                            style={{
                                                                width: `${(d.cantidad / max) * 100}%`,
                                                            }}
                                                        />
                                                    </div>
                                                </div>
                                            );
                                        },
                                    )
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm font-normal text-muted-foreground">
                                    Pendientes de revisar
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {resumen.pendientes_revisar.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No hay formas completas esperando
                                        revisión.
                                    </p>
                                ) : (
                                    resumen.pendientes_revisar.map((p) => (
                                        <Link
                                            key={`${p.cliente_id}-${p.forma}`}
                                            href={clienteShow(p.cliente_id)}
                                            className="flex items-center justify-between text-sm hover:underline"
                                        >
                                            <span>
                                                {p.cliente_nombre}
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    · {p.forma_label}
                                                </span>
                                            </span>
                                            <ArrowRight className="size-3.5 shrink-0 text-muted-foreground" />
                                        </Link>
                                    ))
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm font-normal text-muted-foreground">
                                    Actividad reciente
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {resumen.actividad_reciente.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Todavía no hay actividad.
                                    </p>
                                ) : (
                                    resumen.actividad_reciente.map(
                                        (a, i) => (
                                            <div
                                                key={i}
                                                className="flex items-start justify-between gap-2 text-sm"
                                            >
                                                <div>
                                                    <p>
                                                        {a.campo}{' '}
                                                        <span className="text-muted-foreground">
                                                            · {a.cliente_nombre}
                                                        </span>
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {fechaHora(
                                                            a.created_at,
                                                        )}
                                                    </p>
                                                </div>
                                                <Badge variant="outline">
                                                    {SOURCE_LABEL[a.source]}
                                                </Badge>
                                            </div>
                                        ),
                                    )
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm font-normal text-muted-foreground">
                                    Últimos clientes
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {resumen.ultimos_clientes.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Todavía no hay clientes.
                                    </p>
                                ) : (
                                    <div className="flex flex-wrap gap-3">
                                        {resumen.ultimos_clientes.map(
                                            (c) => (
                                                <Link
                                                    key={c.id}
                                                    href={clienteShow(c.id)}
                                                    className="flex flex-col items-center gap-1"
                                                    title={c.name}
                                                >
                                                    <Avatar>
                                                        <AvatarFallback className="bg-secondary text-secondary-foreground">
                                                            {getInitials(
                                                                c.name,
                                                            )}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <span className="max-w-14 truncate text-center text-xs text-muted-foreground">
                                                        {c.name.split(' ')[0]}
                                                    </span>
                                                </Link>
                                            ),
                                        )}
                                    </div>
                                )}
                                <Link
                                    href={clientesIndex()}
                                    className="mt-4 flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                                >
                                    Ver todos los clientes
                                    <ArrowRight className="size-3" />
                                </Link>
                            </CardContent>
                        </Card>
                    </div>
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle>Bienvenido</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm text-muted-foreground">
                            Tu preparador va a ir cargando tu información acá.
                            No tenés acceso al panel interno.
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
