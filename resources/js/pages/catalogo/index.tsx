import { Head, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import CatalogoController from '@/actions/App/Http/Controllers/CatalogoController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DataTable } from '@/components/ui/data-table';
import { DataTableColumnHeader } from '@/components/ui/data-table-column-header';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { index as catalogoIndex } from '@/routes/catalogo';
import type { CampoCatalogo, FormaOption } from '@/types';

type Errors = Partial<
    Record<
        | 'clave'
        | 'tipo_campo'
        | 'tipo_dato'
        | 'formatos_aceptados'
        | 'subcampos'
        | 'obligatorio'
        | 'sensible',
        string
    >
>;

function CampoForm({
    forma,
    campo,
    onDone,
}: {
    forma: string;
    campo?: CampoCatalogo;
    onDone: () => void;
}) {
    const { t } = useTranslation();
    const [clave, setClave] = useState(campo?.clave ?? '');
    const [tipoCampo, setTipoCampo] = useState(campo?.tipo_campo ?? 'dato');
    const [tipoDato, setTipoDato] = useState(campo?.tipo_dato ?? 'string');
    const [formatos, setFormatos] = useState(
        campo?.formatos_aceptados?.join(', ') ?? '',
    );
    const [subcampos, setSubcampos] = useState(
        campo?.subcampos?.join(', ') ?? '',
    );
    const [obligatorio, setObligatorio] = useState(campo?.obligatorio ?? true);
    const [sensible, setSensible] = useState(campo?.sensible ?? false);
    const [errors, setErrors] = useState<Errors>({});
    const [processing, setProcessing] = useState(false);

    const submit = () => {
        setProcessing(true);

        const payload = {
            forma,
            clave,
            tipo_campo: tipoCampo,
            tipo_dato: tipoCampo === 'documento' ? null : tipoDato,
            formatos_aceptados:
                tipoCampo === 'dato'
                    ? null
                    : formatos
                          .split(',')
                          .map((f) => f.trim())
                          .filter(Boolean),
            subcampos: subcampos
                .split(',')
                .map((s) => s.trim())
                .filter(Boolean),
            obligatorio,
            sensible,
        };

        const options = {
            onError: (e: Errors) => setErrors(e),
            onSuccess: () => onDone(),
            onFinish: () => setProcessing(false),
        };

        if (campo) {
            router.patch(
                CatalogoController.update(campo.id).url,
                payload,
                options,
            );
        } else {
            router.post(CatalogoController.store().url, payload, options);
        }
    };

    return (
        <div className="space-y-4">
            <div className="grid gap-2">
                <Label htmlFor="clave">{t('catalogo.form.key')}</Label>
                <Input
                    id="clave"
                    value={clave}
                    onChange={(e) => setClave(e.target.value)}
                    placeholder={t('catalogo.form.keyPlaceholder')}
                />
                <InputError message={errors.clave} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="tipo_campo">
                    {t('catalogo.form.fieldType')}
                </Label>
                <select
                    id="tipo_campo"
                    className="rounded border bg-background p-2 text-sm"
                    value={tipoCampo}
                    onChange={(e) =>
                        setTipoCampo(
                            e.target.value as CampoCatalogo['tipo_campo'],
                        )
                    }
                >
                    <option value="documento">
                        {t('catalogo.fieldTypes.documentoOption')}
                    </option>
                    <option value="dato">
                        {t('catalogo.fieldTypes.datoOption')}
                    </option>
                    <option value="mixto">
                        {t('catalogo.fieldTypes.mixtoOption')}
                    </option>
                </select>
                <InputError message={errors.tipo_campo} />
            </div>

            {tipoCampo !== 'documento' && (
                <div className="grid gap-2">
                    <Label htmlFor="tipo_dato">
                        {t('catalogo.form.dataType')}
                    </Label>
                    <select
                        id="tipo_dato"
                        className="rounded border bg-background p-2 text-sm"
                        value={tipoDato ?? 'string'}
                        onChange={(e) =>
                            setTipoDato(
                                e.target.value as NonNullable<
                                    CampoCatalogo['tipo_dato']
                                >,
                            )
                        }
                    >
                        <option value="string">string</option>
                        <option value="number">number</option>
                        <option value="object">object</option>
                        <option value="array_string">array_string</option>
                        <option value="array_object">array_object</option>
                    </select>
                    <InputError message={errors.tipo_dato} />
                </div>
            )}

            {tipoCampo !== 'dato' && (
                <div className="grid gap-2">
                    <Label htmlFor="formatos">
                        {t('catalogo.form.acceptedFormats')}
                    </Label>
                    <Input
                        id="formatos"
                        value={formatos}
                        onChange={(e) => setFormatos(e.target.value)}
                        placeholder="pdf, jpg, png"
                    />
                    <InputError message={errors.formatos_aceptados} />
                </div>
            )}

            <div className="grid gap-2">
                <Label htmlFor="subcampos">
                    {t('catalogo.form.subfields')}
                </Label>
                <Input
                    id="subcampos"
                    value={subcampos}
                    onChange={(e) => setSubcampos(e.target.value)}
                    placeholder="nombre_completo, fecha_nacimiento, ssn"
                />
            </div>

            <div className="flex items-center gap-2">
                <Checkbox
                    id="obligatorio"
                    checked={obligatorio}
                    onCheckedChange={(v) => setObligatorio(v === true)}
                />
                <Label htmlFor="obligatorio">
                    {t('catalogo.form.required')}
                </Label>
            </div>

            <div className="flex items-center gap-2">
                <Checkbox
                    id="sensible"
                    checked={sensible}
                    onCheckedChange={(v) => setSensible(v === true)}
                />
                <Label htmlFor="sensible">{t('catalogo.form.sensitive')}</Label>
            </div>

            <DialogFooter>
                <Button onClick={submit} disabled={processing}>
                    {t('common.save')}
                </Button>
            </DialogFooter>
        </div>
    );
}

function CampoRowActions({ campo }: { campo: CampoCatalogo }) {
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
                        {t('catalogo.actions.editTitle', { key: campo.clave })}
                    </DialogTitle>
                    <CampoForm
                        forma={campo.forma}
                        campo={campo}
                        onDone={() => setEditar(false)}
                    />
                </DialogContent>
            </Dialog>
            <Button
                variant="ghost"
                size="sm"
                className="text-destructive hover:text-destructive"
                onClick={() => {
                    if (
                        confirm(
                            t('catalogo.actions.deleteConfirm', {
                                key: campo.clave,
                            }),
                        )
                    ) {
                        router.delete(CatalogoController.destroy(campo.id).url);
                    }
                }}
            >
                {t('common.delete')}
            </Button>
        </div>
    );
}

function NuevoCampoDialog({ formas }: { formas: FormaOption[] }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [forma, setForma] = useState<string>(String(formas[0]?.value ?? ''));

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>{t('catalogo.actions.new')}</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>{t('catalogo.actions.new')}</DialogTitle>
                <div className="grid gap-2">
                    <Label htmlFor="forma_nuevo">
                        {t('catalogo.columns.form')}
                    </Label>
                    <select
                        id="forma_nuevo"
                        className="rounded border bg-background p-2 text-sm"
                        value={forma}
                        onChange={(e) => setForma(e.target.value)}
                    >
                        {formas.map((f) => (
                            <option key={f.value} value={f.value}>
                                {f.label}
                            </option>
                        ))}
                    </select>
                </div>
                <CampoForm forma={forma} onDone={() => setOpen(false)} />
            </DialogContent>
        </Dialog>
    );
}

function useColumns(
    formaLabel: (value: string) => string,
): ColumnDef<CampoCatalogo>[] {
    const { t } = useTranslation();

    return [
        {
            accessorKey: 'clave',
            id: 'clave',
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title={t('catalogo.columns.key')}
                />
            ),
            cell: ({ row }) => (
                <span className="font-medium text-foreground">
                    {row.original.clave}
                </span>
            ),
            enableHiding: false,
        },
        {
            accessorKey: 'forma',
            id: 'forma',
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title={t('catalogo.columns.form')}
                />
            ),
            cell: ({ row }) => (
                <span className="text-sm text-muted-foreground">
                    {formaLabel(row.original.forma)}
                </span>
            ),
            filterFn: (row, id, value) =>
                (value as string[]).includes(row.getValue<string>(id)),
        },
        {
            accessorKey: 'tipo_campo',
            id: 'tipo',
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title={t('catalogo.columns.type')}
                />
            ),
            cell: ({ row }) => {
                const c = row.original;

                return (
                    <span className="text-xs text-muted-foreground">
                        {c.tipo_campo}
                        {c.tipo_dato ? ` · ${c.tipo_dato}` : ''}
                        {c.formatos_aceptados?.length
                            ? ` · ${c.formatos_aceptados.join('/')}`
                            : ''}
                    </span>
                );
            },
            filterFn: (row, id, value) =>
                (value as string[]).includes(row.getValue<string>(id)),
        },
        {
            id: 'obligatorio',
            accessorFn: (c) => (c.obligatorio ? 'si' : 'no'),
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title={t('catalogo.columns.required')}
                />
            ),
            cell: ({ row }) =>
                row.original.obligatorio ? (
                    <Badge variant="default">{t('catalogo.badges.yes')}</Badge>
                ) : (
                    <Badge variant="outline">{t('catalogo.badges.no')}</Badge>
                ),
            filterFn: (row, id, value) =>
                (value as string[]).includes(row.getValue<string>(id)),
        },
        {
            id: 'sensible',
            accessorFn: (c) => (c.sensible ? 'si' : 'no'),
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title={t('catalogo.columns.sensitive')}
                />
            ),
            cell: ({ row }) =>
                row.original.sensible ? (
                    <Badge variant="destructive">
                        {t('catalogo.badges.sensitive')}
                    </Badge>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
            filterFn: (row, id, value) =>
                (value as string[]).includes(row.getValue<string>(id)),
        },
        {
            id: 'acciones',
            header: () => (
                <span className="sr-only">{t('common.actions')}</span>
            ),
            cell: ({ row }) => <CampoRowActions campo={row.original} />,
            enableHiding: false,
            enableSorting: false,
        },
    ];
}

export default function CatalogoIndex({
    formas,
    campos,
}: {
    formas: FormaOption[];
    campos: CampoCatalogo[];
}) {
    const { t } = useTranslation();
    const formaLabel = (value: string) =>
        formas.find((f) => String(f.value) === value)?.label ?? value;

    const columns = useColumns(formaLabel);

    return (
        <>
            <Head title={t('catalogo.title')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-1">
                    <h1 className="text-xl font-semibold">
                        {t('catalogo.title')}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {t('catalogo.subtitle')}
                    </p>
                </div>

                <DataTable
                    columns={columns}
                    data={campos}
                    searchPlaceholder={t('catalogo.searchPlaceholder')}
                    emptyMessage={t('catalogo.empty')}
                    initialPageSize={20}
                    facetedFilters={[
                        {
                            columnId: 'forma',
                            title: t('catalogo.columns.form'),
                            options: formas.map((f) => ({
                                label: f.label,
                                value: String(f.value),
                            })),
                        },
                        {
                            columnId: 'tipo',
                            title: t('catalogo.columns.type'),
                            options: [
                                { label: 'documento', value: 'documento' },
                                { label: 'dato', value: 'dato' },
                                { label: 'mixto', value: 'mixto' },
                            ],
                        },
                        {
                            columnId: 'obligatorio',
                            title: t('catalogo.columns.required'),
                            options: [
                                {
                                    label: t('catalogo.badges.yes'),
                                    value: 'si',
                                },
                                { label: t('catalogo.badges.no'), value: 'no' },
                            ],
                        },
                        {
                            columnId: 'sensible',
                            title: t('catalogo.columns.sensitive'),
                            options: [
                                {
                                    label: t('catalogo.badges.sensitive'),
                                    value: 'si',
                                },
                                {
                                    label: t('catalogo.badges.notSensitive'),
                                    value: 'no',
                                },
                            ],
                        },
                    ]}
                    toolbarActions={<NuevoCampoDialog formas={formas} />}
                />
            </div>
        </>
    );
}

CatalogoIndex.layout = {
    breadcrumbs: [
        { title: 'nav.dashboard', href: dashboard() },
        { title: 'nav.catalog', href: catalogoIndex() },
    ],
};
