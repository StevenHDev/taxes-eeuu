import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Bot,
    CheckCircle2,
    CircleDashed,
    ClipboardCheck,
    ClipboardList,
    Inbox,
    Loader,
    ShieldCheck,
    UserCog,
    Users,
} from 'lucide-react';
import { ActivityBarChart } from '@/components/dashboard/activity-bar-chart';
import { RadialGauge } from '@/components/dashboard/radial-gauge';
import { StatCard } from '@/components/dashboard/stat-card';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useInitials } from '@/hooks/use-initials';
import { dashboard } from '@/routes';
import { index as clientesIndex, show as clienteShow } from '@/routes/clientes';
import type { DashboardResumen } from '@/types';

const SOURCE: Record<
    DashboardResumen['actividad_reciente'][number]['source'],
    { label: string; icon: typeof Bot }
> = {
    agente_ia: { label: 'Agente IA', icon: Bot },
    preparador: { label: 'Preparador', icon: UserCog },
    administrador: { label: 'Admin', icon: ShieldCheck },
};

function fechaHora(fecha: string | null): string {
    if (!fecha) return '—';

    return new Date(fecha).toLocaleString('es-AR', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function SectionTitle({
    icon: Icon,
    children,
}: {
    icon: typeof Bot;
    children: React.ReactNode;
}) {
    return (
        <CardTitle className="flex items-center gap-2 text-sm font-semibold text-foreground">
            <Icon className="size-4 text-muted-foreground" />
            {children}
        </CardTitle>
    );
}

export default function Dashboard({
    resumen,
}: {
    resumen: DashboardResumen | null;
}) {
    const getInitials = useInitials();

    if (!resumen) {
        return (
            <>
                <Head title="Dashboard" />
                <div className="flex h-full flex-1 flex-col p-4">
                    <Card className="mx-auto mt-8 max-w-lg">
                        <CardHeader>
                            <CardTitle>Bienvenido</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm text-muted-foreground">
                            Tu preparador va a ir cargando tu información acá. No
                            tenés acceso al panel interno.
                        </CardContent>
                    </Card>
                </div>
            </>
        );
    }

    const maxForma = Math.max(
        1,
        ...resumen.distribucion_por_forma.map((d) => d.cantidad),
    );

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                {/* ── Hero ─────────────────────────────────────────────── */}
                <section className="dash-hero-texture dash-reveal relative overflow-hidden rounded-xl bg-primary p-6 text-primary-foreground shadow-lg ring-1 ring-white/10 sm:p-8">
                    <div
                        aria-hidden
                        className="pointer-events-none absolute -top-20 -right-16 size-64 rounded-full bg-primary-foreground/5"
                    />
                    <div
                        aria-hidden
                        className="pointer-events-none absolute -bottom-28 right-40 size-72 rounded-full bg-primary-foreground/5"
                    />
                    <div className="relative z-10 flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p className="text-xs font-medium tracking-[0.14em] text-primary-foreground/60 uppercase">
                                Clientes en gestión
                            </p>
                            <p className="mt-1.5 text-[clamp(2.75rem,7vw,4.25rem)] font-semibold leading-[0.95] tracking-tight tabular-nums">
                                {resumen.total}
                            </p>
                            <div className="mt-4 flex flex-wrap gap-2 text-xs">
                                <span className="rounded-full bg-primary-foreground/10 px-2.5 py-1 font-medium ring-1 ring-inset ring-primary-foreground/15">
                                    {resumen.en_progreso} en progreso
                                </span>
                                <span className="rounded-full bg-primary-foreground/10 px-2.5 py-1 font-medium ring-1 ring-inset ring-primary-foreground/15">
                                    {resumen.completo} completos
                                </span>
                                <span className="rounded-full bg-primary-foreground/10 px-2.5 py-1 font-medium ring-1 ring-inset ring-primary-foreground/15">
                                    {resumen.sin_iniciar} sin iniciar
                                </span>
                            </div>
                        </div>
                        <Link
                            href={clientesIndex()}
                            className="group inline-flex w-full items-center justify-center gap-1.5 self-start rounded-lg bg-primary-foreground px-5 py-3 text-sm font-semibold text-primary shadow-sm transition-all hover:shadow-md sm:w-auto sm:self-auto"
                        >
                            Ver clientes
                            <ArrowRight className="size-4 transition-transform group-hover:translate-x-0.5" />
                        </Link>
                    </div>
                </section>

                {/* ── Actividad + rail de estados ──────────────────────── */}
                <div
                    className="dash-reveal grid gap-4 lg:grid-cols-3"
                    style={{ animationDelay: '80ms' }}
                >
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <SectionTitle icon={ClipboardList}>
                                Actividad
                            </SectionTitle>
                            <p className="text-xs text-muted-foreground">
                                Campos recibidos por día · últimos 7 días
                            </p>
                        </CardHeader>
                        <CardContent>
                            <ActivityBarChart data={resumen.actividad_por_dia} />
                        </CardContent>
                    </Card>

                    <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-1">
                        <StatCard
                            icon={CircleDashed}
                            label="Sin iniciar"
                            value={resumen.sin_iniciar}
                            accent="muted"
                        />
                        <StatCard
                            icon={Loader}
                            label="En progreso"
                            value={resumen.en_progreso}
                        />
                        <StatCard
                            icon={CheckCircle2}
                            label="Completos"
                            value={resumen.completo}
                            accent="strong"
                        />
                    </div>
                </div>

                {/* ── Fila de KPIs ─────────────────────────────────────── */}
                <div
                    className="dash-reveal grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                    style={{ animationDelay: '160ms' }}
                >
                    <StatCard
                        icon={Inbox}
                        label="Campos recibidos"
                        value={`${resumen.campos_recibidos_porcentaje}%`}
                        tag="Progreso"
                        hint="Sobre el total esperado de todas las formas."
                    />
                    <StatCard
                        icon={ClipboardCheck}
                        label="Formas completas"
                        value={`${resumen.formas_completas_porcentaje}%`}
                        tag="Total"
                        hint="De las formas iniciadas que llegaron al 100%."
                    />
                    {resumen.pendientes_revisar.length > 0 ? (
                        <Link
                            href={clientesIndex()}
                            className="rounded-xl transition-transform hover:-translate-y-0.5"
                        >
                            <StatCard
                                icon={ClipboardCheck}
                                label="Pendientes de revisar"
                                value={resumen.pendientes_revisar.length}
                                tag="Revisión"
                                hint="Formas completas esperando aprobación."
                            />
                        </Link>
                    ) : (
                        <StatCard
                            icon={ClipboardCheck}
                            label="Pendientes de revisar"
                            value={0}
                            tag="Revisión"
                            hint="No hay formas esperando revisión."
                        />
                    )}
                </div>

                {/* ── Actividad reciente + panel lateral ───────────────── */}
                <div
                    className="dash-reveal grid gap-4 lg:grid-cols-3"
                    style={{ animationDelay: '240ms' }}
                >
                    <Card className="lg:col-span-2">
                        <CardHeader className="flex-row items-center justify-between space-y-0">
                            <SectionTitle icon={ClipboardList}>
                                Actividad reciente
                            </SectionTitle>
                            <Link
                                href={clientesIndex()}
                                className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                            >
                                Ver clientes
                                <ArrowRight className="size-3" />
                            </Link>
                        </CardHeader>
                        <CardContent>
                            {resumen.actividad_reciente.length === 0 ? (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    Todavía no hay actividad.
                                </p>
                            ) : (
                                <div className="-mx-2 overflow-x-auto px-2">
                                    <Table>
                                    <TableHeader>
                                        <TableRow className="hover:bg-transparent">
                                            <TableHead>Campo</TableHead>
                                            <TableHead>Cliente</TableHead>
                                            <TableHead>Origen</TableHead>
                                            <TableHead className="hidden text-right sm:table-cell">
                                                Fecha
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {resumen.actividad_reciente.map(
                                            (a, i) => {
                                                const src = SOURCE[a.source];
                                                const SrcIcon = src.icon;

                                                return (
                                                    <TableRow key={i}>
                                                        <TableCell>
                                                            <div className="font-medium text-foreground">
                                                                {a.campo}
                                                            </div>
                                                            <div className="text-xs text-muted-foreground">
                                                                {a.forma_label}
                                                            </div>
                                                        </TableCell>
                                                        <TableCell className="text-muted-foreground">
                                                            {a.cliente_nombre}
                                                        </TableCell>
                                                        <TableCell>
                                                            <Badge
                                                                variant="secondary"
                                                                className="gap-1 font-normal"
                                                            >
                                                                <SrcIcon className="size-3" />
                                                                {src.label}
                                                            </Badge>
                                                        </TableCell>
                                                        <TableCell className="hidden text-right text-xs text-muted-foreground tabular-nums sm:table-cell">
                                                            {fechaHora(
                                                                a.created_at,
                                                            )}
                                                        </TableCell>
                                                    </TableRow>
                                                );
                                            },
                                        )}
                                    </TableBody>
                                    </Table>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <div className="flex flex-col gap-4">
                        <Card>
                            <CardHeader>
                                <SectionTitle icon={ClipboardCheck}>
                                    Formas completas
                                </SectionTitle>
                            </CardHeader>
                            <CardContent className="flex flex-col items-center gap-4">
                                <RadialGauge
                                    percentage={
                                        resumen.formas_completas_porcentaje
                                    }
                                />
                                {resumen.distribucion_por_forma.length > 0 && (
                                    <div className="w-full space-y-2.5">
                                        <p className="text-xs font-medium text-muted-foreground">
                                            Distribución por forma
                                        </p>
                                        {resumen.distribucion_por_forma
                                            .slice(0, 4)
                                            .map((d) => (
                                                <div key={d.forma}>
                                                    <div className="mb-1 flex justify-between text-xs">
                                                        <span className="truncate text-muted-foreground">
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
                                                                width: `${(d.cantidad / maxForma) * 100}%`,
                                                            }}
                                                        />
                                                    </div>
                                                </div>
                                            ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <SectionTitle icon={Users}>
                                    Últimos clientes
                                </SectionTitle>
                            </CardHeader>
                            <CardContent>
                                {resumen.ultimos_clientes.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Todavía no hay clientes.
                                    </p>
                                ) : (
                                    <div className="space-y-1">
                                        {resumen.ultimos_clientes.map((c) => (
                                            <Link
                                                key={c.id}
                                                href={clienteShow(c.id)}
                                                className="flex items-center gap-3 rounded-lg px-2 py-2.5 transition-colors hover:bg-accent"
                                            >
                                                <Avatar className="size-8">
                                                    <AvatarFallback className="bg-secondary text-xs text-secondary-foreground">
                                                        {getInitials(c.name)}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <span className="flex-1 truncate text-sm text-foreground">
                                                    {c.name}
                                                </span>
                                                <ArrowRight className="size-3.5 shrink-0 text-muted-foreground" />
                                            </Link>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
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
