import { Head, Link } from '@inertiajs/react';
import { show as clienteShow, index as clientesIndex } from '@/routes/clientes';
import { dashboard } from '@/routes';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { Cliente, EstadoGeneral, FormaOption, Paginated } from '@/types';

const ESTADO_LABEL: Record<EstadoGeneral, string> = {
    sin_iniciar: 'Sin iniciar',
    en_progreso: 'En progreso',
    completo: 'Completo',
};

const ESTADO_VARIANT: Record<
    EstadoGeneral,
    'outline' | 'secondary' | 'default'
> = {
    sin_iniciar: 'outline',
    en_progreso: 'secondary',
    completo: 'default',
};

export default function ClientesIndex({
    clientes,
}: {
    clientes: Paginated<Cliente>;
    formas: FormaOption[];
}) {
    return (
        <>
            <Head title="Clientes" />

            <div className="space-y-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Clientes</h1>
                    <p className="text-sm text-muted-foreground">
                        Estado de recolección de datos por cliente, campo a
                        campo.
                    </p>
                </div>

                <div className="overflow-hidden rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Cliente</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead>Formas</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {clientes.data.map((cliente) => (
                                <TableRow key={cliente.id}>
                                    <TableCell>
                                        <Link
                                            href={clienteShow(cliente.id)}
                                            className="font-medium underline-offset-4 hover:underline"
                                        >
                                            {cliente.name}
                                        </Link>
                                        <div className="text-xs text-muted-foreground">
                                            {cliente.email}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                ESTADO_VARIANT[
                                                    cliente.estado_general
                                                ]
                                            }
                                        >
                                            {
                                                ESTADO_LABEL[
                                                    cliente.estado_general
                                                ]
                                            }
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-wrap gap-1">
                                            {cliente.formas.map((f) => (
                                                <Badge
                                                    key={f.forma}
                                                    variant="outline"
                                                >
                                                    {f.forma_label}
                                                </Badge>
                                            ))}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}

                            {clientes.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={3}
                                        className="text-center text-muted-foreground"
                                    >
                                        Todavía no hay clientes.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

ClientesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Clientes', href: clientesIndex() },
    ],
};
