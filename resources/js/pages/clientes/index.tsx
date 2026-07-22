import { Form, Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import ClienteController from '@/actions/App/Http/Controllers/ClienteController';
import { show as clienteShow, index as clientesIndex } from '@/routes/clientes';
import { dashboard } from '@/routes';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    search,
}: {
    clientes: Paginated<Cliente>;
    formas: FormaOption[];
    search: string | null;
}) {
    const [query, setQuery] = useState(search ?? '');

    return (
        <>
            <Head title="Clientes" />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Clientes</h1>
                        <p className="text-sm text-muted-foreground">
                            Estado de recolección de datos por cliente, campo
                            a campo.
                        </p>
                    </div>

                    <Dialog>
                        <DialogTrigger asChild>
                            <Button>Nuevo cliente</Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>Nuevo cliente</DialogTitle>
                            <Form
                                {...ClienteController.store.form()}
                                resetOnSuccess
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="name">
                                                Nombre
                                            </Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                required
                                            />
                                            <InputError
                                                message={errors.name}
                                            />
                                        </div>
                                        <div className="mt-4 grid gap-2">
                                            <Label htmlFor="email">
                                                Email
                                            </Label>
                                            <Input
                                                id="email"
                                                name="email"
                                                type="email"
                                                required
                                            />
                                            <InputError
                                                message={errors.email}
                                            />
                                        </div>
                                        <div className="mt-4 grid gap-2">
                                            <Label htmlFor="phone">
                                                Teléfono (opcional)
                                            </Label>
                                            <Input
                                                id="phone"
                                                name="phone"
                                                placeholder="+15551234567"
                                            />
                                            <InputError
                                                message={errors.phone}
                                            />
                                        </div>
                                        <DialogFooter className="mt-4">
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Crear
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>

                <form
                    className="max-w-sm"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(
                            clientesIndex.url({ query: { search: query } }),
                        );
                    }}
                >
                    <div className="relative">
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Buscar por nombre, email o teléfono…"
                            className="rounded-full pl-9"
                        />
                    </div>
                </form>

                <Card className="overflow-hidden py-0">
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
                                            {cliente.phone
                                                ? ` · ${cliente.phone}`
                                                : ''}
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
                </Card>
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
