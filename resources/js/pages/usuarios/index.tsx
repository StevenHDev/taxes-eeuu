import { Head, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useState } from 'react';
import UsuarioController from '@/actions/App/Http/Controllers/UsuarioController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { DataTableColumnHeader } from '@/components/ui/data-table-column-header';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { index as usuariosIndex } from '@/routes/usuarios';
import type { Usuario } from '@/types';

const ROLE_LABEL: Record<Usuario['role'], string> = {
    client: 'Cliente',
    preparer: 'Preparador',
    administrator: 'Administrador',
};

type Preparador = { id: number; name: string };

type Errors = Partial<
    Record<
        'name' | 'email' | 'phone' | 'password' | 'role' | 'preparer_id',
        string
    >
>;

function UsuarioForm({
    usuario,
    preparadores,
    onDone,
}: {
    usuario?: Usuario;
    preparadores: Preparador[];
    onDone: () => void;
}) {
    const [name, setName] = useState(usuario?.name ?? '');
    const [email, setEmail] = useState(usuario?.email ?? '');
    const [phone, setPhone] = useState(usuario?.phone ?? '');
    const [password, setPassword] = useState('');
    const [role, setRole] = useState<Usuario['role']>(
        usuario?.role ?? 'client',
    );
    const [preparerId, setPreparerId] = useState(
        usuario?.preparer?.id ? String(usuario.preparer.id) : '',
    );
    const [errors, setErrors] = useState<Errors>({});
    const [processing, setProcessing] = useState(false);

    const submit = () => {
        setProcessing(true);

        const payload = {
            name,
            email,
            phone: phone || null,
            ...(password ? { password } : {}),
            role,
            preparer_id: preparerId ? Number(preparerId) : null,
        };

        const options = {
            onError: (e: Errors) => setErrors(e),
            onSuccess: () => onDone(),
            onFinish: () => setProcessing(false),
        };

        if (usuario) {
            router.patch(
                UsuarioController.update(usuario.id).url,
                payload,
                options,
            );
        } else {
            router.post(UsuarioController.store().url, payload, options);
        }
    };

    return (
        <div className="space-y-4">
            <div className="grid gap-2">
                <Label htmlFor="name">Nombre</Label>
                <Input
                    id="name"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="email">Email</Label>
                <Input
                    id="email"
                    type="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                />
                <InputError message={errors.email} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="phone">Teléfono</Label>
                <Input
                    id="phone"
                    value={phone}
                    onChange={(e) => setPhone(e.target.value)}
                    placeholder="+15551234567"
                />
                <InputError message={errors.phone} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="password">
                    Contraseña
                    {usuario ? ' (dejar en blanco para no cambiarla)' : ''}
                </Label>
                <Input
                    id="password"
                    type="password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                />
                <InputError message={errors.password} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="role">Rol</Label>
                <select
                    id="role"
                    className="rounded border bg-background p-2 text-sm"
                    value={role}
                    onChange={(e) => setRole(e.target.value as Usuario['role'])}
                >
                    <option value="client">Cliente</option>
                    <option value="preparer">Preparador</option>
                    <option value="administrator">Administrador</option>
                </select>
                <InputError message={errors.role} />
            </div>

            {role === 'client' && (
                <div className="grid gap-2">
                    <Label htmlFor="preparer_id">Preparador asignado</Label>
                    <select
                        id="preparer_id"
                        className="rounded border bg-background p-2 text-sm"
                        value={preparerId}
                        onChange={(e) => setPreparerId(e.target.value)}
                    >
                        <option value="">Sin asignar</option>
                        {preparadores.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.name}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.preparer_id} />
                </div>
            )}

            <DialogFooter>
                <Button onClick={submit} disabled={processing}>
                    Guardar
                </Button>
            </DialogFooter>
        </div>
    );
}

function UsuarioRowActions({
    usuario,
    preparadores,
}: {
    usuario: Usuario;
    preparadores: Preparador[];
}) {
    const [editar, setEditar] = useState(false);

    return (
        <div className="flex justify-end gap-1">
            <Dialog open={editar} onOpenChange={setEditar}>
                <DialogTrigger asChild>
                    <Button variant="ghost" size="sm">
                        Editar
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogTitle>Editar {usuario.name}</DialogTitle>
                    <UsuarioForm
                        usuario={usuario}
                        preparadores={preparadores}
                        onDone={() => setEditar(false)}
                    />
                </DialogContent>
            </Dialog>

            <Dialog>
                <DialogTrigger asChild>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="text-destructive hover:text-destructive"
                    >
                        Eliminar
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogTitle>¿Eliminar a {usuario.name}?</DialogTitle>
                    <DialogDescription>
                        Si es un cliente, se borran todos sus datos cargados.
                        Esta acción no se puede deshacer.
                    </DialogDescription>
                    <DialogFooter>
                        <Button
                            variant="destructive"
                            onClick={() =>
                                router.delete(
                                    UsuarioController.destroy(usuario.id).url,
                                )
                            }
                        >
                            Eliminar definitivamente
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

function buildColumns(preparadores: Preparador[]): ColumnDef<Usuario>[] {
    return [
        {
            id: 'nombre',
            accessorFn: (u) => `${u.name} ${u.email} ${u.phone ?? ''}`,
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Nombre" />
            ),
            cell: ({ row }) => {
                const u = row.original;

                return (
                    <div>
                        <div className="font-medium text-foreground">
                            {u.name}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            {u.email}
                            {u.phone ? ` · ${u.phone}` : ''}
                        </div>
                    </div>
                );
            },
            enableHiding: false,
        },
        {
            accessorKey: 'role',
            id: 'rol',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Rol" />
            ),
            cell: ({ row }) => (
                <Badge variant="outline">{ROLE_LABEL[row.original.role]}</Badge>
            ),
            filterFn: (row, id, value) =>
                (value as string[]).includes(row.getValue<string>(id)),
        },
        {
            id: 'preparador',
            accessorFn: (u) => u.preparer?.name ?? '',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Preparador" />
            ),
            cell: ({ row }) => (
                <span className="text-sm text-muted-foreground">
                    {row.original.preparer?.name ?? '—'}
                </span>
            ),
        },
        {
            id: 'acciones',
            header: () => <span className="sr-only">Acciones</span>,
            cell: ({ row }) => (
                <UsuarioRowActions
                    usuario={row.original}
                    preparadores={preparadores}
                />
            ),
            enableHiding: false,
            enableSorting: false,
        },
    ];
}

export default function UsuariosIndex({
    usuarios,
    preparadores,
}: {
    usuarios: Usuario[];
    preparadores: Preparador[];
}) {
    const [nuevo, setNuevo] = useState(false);
    const columns = buildColumns(preparadores);

    return (
        <>
            <Head title="Usuarios" />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-1">
                    <h1 className="text-xl font-semibold">Usuarios</h1>
                    <p className="text-sm text-muted-foreground">
                        Alta, edición y baja de clientes, preparadores y
                        administradores.
                    </p>
                </div>

                <DataTable
                    columns={columns}
                    data={usuarios}
                    searchPlaceholder="Buscar por nombre, email o teléfono…"
                    emptyMessage="Sin usuarios."
                    facetedFilters={[
                        {
                            columnId: 'rol',
                            title: 'Rol',
                            options: [
                                { label: 'Cliente', value: 'client' },
                                { label: 'Preparador', value: 'preparer' },
                                {
                                    label: 'Administrador',
                                    value: 'administrator',
                                },
                            ],
                        },
                    ]}
                    toolbarActions={
                        <Dialog open={nuevo} onOpenChange={setNuevo}>
                            <DialogTrigger asChild>
                                <Button>Nuevo usuario</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>Nuevo usuario</DialogTitle>
                                <UsuarioForm
                                    preparadores={preparadores}
                                    onDone={() => setNuevo(false)}
                                />
                            </DialogContent>
                        </Dialog>
                    }
                />
            </div>
        </>
    );
}

UsuariosIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Usuarios', href: usuariosIndex() },
    ],
};
