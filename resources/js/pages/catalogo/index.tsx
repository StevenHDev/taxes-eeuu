import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import CatalogoController from '@/actions/App/Http/Controllers/CatalogoController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
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

function FormaSection({
    forma,
    campos,
}: {
    forma: FormaOption;
    campos: CampoCatalogo[];
}) {
    const { t } = useTranslation();
    const [nuevo, setNuevo] = useState(false);

    return (
        <section className="space-y-3">
            <div className="flex items-center justify-between gap-2">
                <div className="flex items-baseline gap-2">
                    <h2 className="font-semibold">{forma.label}</h2>
                    <span className="text-xs text-muted-foreground">
                        {t('catalogo.fieldCount', { count: campos.length })}
                    </span>
                </div>
                <Dialog open={nuevo} onOpenChange={setNuevo}>
                    <DialogTrigger asChild>
                        <Button size="sm" variant="secondary">
                            {t('catalogo.actions.addField')}
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>
                            {t('catalogo.actions.addFieldTo', {
                                form: forma.label,
                            })}
                        </DialogTitle>
                        <CampoForm
                            forma={String(forma.value)}
                            onDone={() => setNuevo(false)}
                        />
                    </DialogContent>
                </Dialog>
            </div>

            <Card className="overflow-hidden py-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{t('catalogo.columns.key')}</TableHead>
                            <TableHead>{t('catalogo.columns.type')}</TableHead>
                            <TableHead>
                                {t('catalogo.columns.required')}
                            </TableHead>
                            <TableHead>
                                {t('catalogo.columns.sensitive')}
                            </TableHead>
                            <TableHead className="text-right">
                                {t('common.actions')}
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {campos.map((campo) => (
                            <TableRow key={campo.id}>
                                <TableCell>
                                    <span className="font-medium text-foreground">
                                        {campo.clave}
                                    </span>
                                    {campo.subcampos?.length ? (
                                        <div className="text-xs text-muted-foreground">
                                            {campo.subcampos.join(', ')}
                                        </div>
                                    ) : null}
                                </TableCell>
                                <TableCell className="text-xs text-muted-foreground">
                                    {campo.tipo_campo}
                                    {campo.tipo_dato
                                        ? ` · ${campo.tipo_dato}`
                                        : ''}
                                    {campo.formatos_aceptados?.length
                                        ? ` · ${campo.formatos_aceptados.join('/')}`
                                        : ''}
                                </TableCell>
                                <TableCell>
                                    {campo.obligatorio ? (
                                        <Badge variant="default">
                                            {t('catalogo.badges.yes')}
                                        </Badge>
                                    ) : (
                                        <Badge variant="outline">
                                            {t('catalogo.badges.no')}
                                        </Badge>
                                    )}
                                </TableCell>
                                <TableCell>
                                    {campo.sensible ? (
                                        <Badge variant="destructive">
                                            {t('catalogo.badges.sensitive')}
                                        </Badge>
                                    ) : (
                                        <span className="text-muted-foreground">
                                            —
                                        </span>
                                    )}
                                </TableCell>
                                <TableCell>
                                    <CampoRowActions campo={campo} />
                                </TableCell>
                            </TableRow>
                        ))}

                        {campos.length === 0 && (
                            <TableRow>
                                <TableCell
                                    colSpan={5}
                                    className="text-center text-muted-foreground"
                                >
                                    {t('catalogo.empty')}
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </Card>
        </section>
    );
}

export default function CatalogoIndex({
    formas,
    campos,
}: {
    formas: FormaOption[];
    campos: CampoCatalogo[];
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('catalogo.title')} />

            <div className="space-y-8 p-4">
                <div className="flex flex-col gap-1">
                    <h1 className="text-xl font-semibold">
                        {t('catalogo.title')}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {t('catalogo.subtitle')}
                    </p>
                </div>

                {(formas ?? []).map((forma) => (
                    <FormaSection
                        key={String(forma.value)}
                        forma={forma}
                        campos={(campos ?? []).filter(
                            (c) => c.forma === forma.value,
                        )}
                    />
                ))}
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
