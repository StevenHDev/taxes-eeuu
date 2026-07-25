import { Head, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
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

function useRoleLabel(): Record<Usuario['role'], string> {
    const { t } = useTranslation();

    return {
        client: t('usuarios.roles.client'),
        preparer: t('usuarios.roles.preparer'),
        administrator: t('usuarios.roles.administrator'),
    };
}

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
    const { t } = useTranslation();
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
                <Label htmlFor="name">{t('usuarios.form.name')}</Label>
                <Input
                    id="name"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="email">{t('usuarios.form.email')}</Label>
                <Input
                    id="email"
                    type="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                />
                <InputError message={errors.email} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="phone">{t('usuarios.form.phone')}</Label>
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
                    {t('usuarios.form.password')}
                    {usuario ? t('usuarios.form.passwordHint') : ''}
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
                <Label htmlFor="role">{t('usuarios.form.role')}</Label>
                <select
                    id="role"
                    className="rounded border bg-background p-2 text-sm"
                    value={role}
                    onChange={(e) => setRole(e.target.value as Usuario['role'])}
                >
                    <option value="client">{t('usuarios.roles.client')}</option>
                    <option value="preparer">
                        {t('usuarios.roles.preparer')}
                    </option>
                    <option value="administrator">
                        {t('usuarios.roles.administrator')}
                    </option>
                </select>
                <InputError message={errors.role} />
            </div>

            {role === 'client' && (
                <div className="grid gap-2">
                    <Label htmlFor="preparer_id">
                        {t('usuarios.form.assignedPreparer')}
                    </Label>
                    <select
                        id="preparer_id"
                        className="rounded border bg-background p-2 text-sm"
                        value={preparerId}
                        onChange={(e) => setPreparerId(e.target.value)}
                    >
                        <option value="">
                            {t('usuarios.form.unassigned')}
                        </option>
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
                    {t('common.save')}
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
    const { t } = useTranslation();
    const [editar, setEditar] = useState(false);

    return (
        <div className="flex justify-end gap-1">
            <Dialog open={editar} onOpenChange={setEditar}>
                <DialogTrigger asChild>
                    <Button variant="ghost" size="sm">
                        {t('common.edit')}
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogTitle>
                        {t('usuarios.actions.editTitle', {
                            name: usuario.name,
                        })}
                    </DialogTitle>
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
                        {t('common.delete')}
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogTitle>
                        {t('usuarios.actions.deleteTitle', {
                            name: usuario.name,
                        })}
                    </DialogTitle>
                    <DialogDescription>
                        {t('usuarios.actions.deleteDescription')}
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
                            {t('usuarios.actions.deleteConfirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

function useColumns(preparadores: Preparador[]): ColumnDef<Usuario>[] {
    const { t } = useTranslation();
    const roleLabel = useRoleLabel();

    return [
        {
            id: 'nombre',
            accessorFn: (u) => `${u.name} ${u.email} ${u.phone ?? ''}`,
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title={t('usuarios.columns.name')}
                />
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
                <DataTableColumnHeader
                    column={column}
                    title={t('usuarios.columns.role')}
                />
            ),
            cell: ({ row }) => (
                <Badge variant="outline">{roleLabel[row.original.role]}</Badge>
            ),
            filterFn: (row, id, value) =>
                (value as string[]).includes(row.getValue<string>(id)),
        },
        {
            id: 'preparador',
            accessorFn: (u) => u.preparer?.name ?? '',
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title={t('usuarios.columns.preparer')}
                />
            ),
            cell: ({ row }) => (
                <span className="text-sm text-muted-foreground">
                    {row.original.preparer?.name ?? '—'}
                </span>
            ),
        },
        {
            id: 'acciones',
            header: () => (
                <span className="sr-only">{t('common.actions')}</span>
            ),
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
    const { t } = useTranslation();
    const [nuevo, setNuevo] = useState(false);
    const columns = useColumns(preparadores);

    return (
        <>
            <Head title={t('usuarios.title')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-1">
                    <h1 className="text-xl font-semibold">
                        {t('usuarios.title')}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {t('usuarios.subtitle')}
                    </p>
                </div>

                <DataTable
                    columns={columns}
                    data={usuarios}
                    searchPlaceholder={t('usuarios.searchPlaceholder')}
                    emptyMessage={t('usuarios.empty')}
                    facetedFilters={[
                        {
                            columnId: 'rol',
                            title: t('usuarios.columns.role'),
                            options: [
                                {
                                    label: t('usuarios.roles.client'),
                                    value: 'client',
                                },
                                {
                                    label: t('usuarios.roles.preparer'),
                                    value: 'preparer',
                                },
                                {
                                    label: t('usuarios.roles.administrator'),
                                    value: 'administrator',
                                },
                            ],
                        },
                    ]}
                    toolbarActions={
                        <Dialog open={nuevo} onOpenChange={setNuevo}>
                            <DialogTrigger asChild>
                                <Button>{t('usuarios.actions.new')}</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>
                                    {t('usuarios.actions.new')}
                                </DialogTitle>
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
        { title: 'nav.dashboard', href: dashboard() },
        { title: 'nav.users', href: usuariosIndex() },
    ],
};
