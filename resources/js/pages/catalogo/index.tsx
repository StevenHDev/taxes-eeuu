import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
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
import { dashboard } from '@/routes';
import { index as catalogoIndex } from '@/routes/catalogo';
import CatalogoController from '@/actions/App/Http/Controllers/CatalogoController';
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
    const [clave, setClave] = useState(campo?.clave ?? '');
    const [tipoCampo, setTipoCampo] = useState(
        campo?.tipo_campo ?? 'dato',
    );
    const [tipoDato, setTipoDato] = useState(campo?.tipo_dato ?? 'string');
    const [formatos, setFormatos] = useState(
        campo?.formatos_aceptados?.join(', ') ?? '',
    );
    const [subcampos, setSubcampos] = useState(
        campo?.subcampos?.join(', ') ?? '',
    );
    const [obligatorio, setObligatorio] = useState(
        campo?.obligatorio ?? true,
    );
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
            router.patch(CatalogoController.update(campo.id).url, payload, options);
        } else {
            router.post(CatalogoController.store().url, payload, options);
        }
    };

    return (
        <div className="space-y-4">
            <div className="grid gap-2">
                <Label htmlFor="clave">Clave del campo</Label>
                <Input
                    id="clave"
                    value={clave}
                    onChange={(e) => setClave(e.target.value)}
                    placeholder="ej. ingresos_negocio"
                />
                <InputError message={errors.clave} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="tipo_campo">Tipo de campo</Label>
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
                    <option value="documento">documento (solo archivo)</option>
                    <option value="dato">dato (solo texto/número)</option>
                    <option value="mixto">mixto (archivo o texto)</option>
                </select>
                <InputError message={errors.tipo_campo} />
            </div>

            {tipoCampo !== 'documento' && (
                <div className="grid gap-2">
                    <Label htmlFor="tipo_dato">Tipo de dato</Label>
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
                        Formatos aceptados (separados por coma)
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
                    Subcampos (opcional, para object/array_object)
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
                    Obligatorio para completar la forma
                </Label>
            </div>

            <div className="flex items-center gap-2">
                <Checkbox
                    id="sensible"
                    checked={sensible}
                    onCheckedChange={(v) => setSensible(v === true)}
                />
                <Label htmlFor="sensible">
                    Sensible (se cifra y se enmascara)
                </Label>
            </div>

            <DialogFooter>
                <Button onClick={submit} disabled={processing}>
                    Guardar
                </Button>
            </DialogFooter>
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
    const [dialogAbierto, setDialogAbierto] = useState<
        'nuevo' | number | null
    >(null);

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <h2 className="font-semibold">{forma.label}</h2>
                <Dialog
                    open={dialogAbierto === 'nuevo'}
                    onOpenChange={(open) =>
                        setDialogAbierto(open ? 'nuevo' : null)
                    }
                >
                    <DialogTrigger asChild>
                        <Button size="sm" variant="secondary">
                            Agregar campo
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>
                            Agregar campo a {forma.label}
                        </DialogTitle>
                        <CampoForm
                            forma={forma.value}
                            onDone={() => setDialogAbierto(null)}
                        />
                    </DialogContent>
                </Dialog>
            </div>

            <Card className="overflow-hidden py-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Clave</TableHead>
                            <TableHead>Tipo</TableHead>
                            <TableHead>Obligatorio</TableHead>
                            <TableHead>Sensible</TableHead>
                            <TableHead className="text-right">
                                Acciones
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {campos.map((campo) => (
                            <TableRow key={campo.id}>
                                <TableCell>{campo.clave}</TableCell>
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
                                        <Badge variant="default">Sí</Badge>
                                    ) : (
                                        <Badge variant="outline">No</Badge>
                                    )}
                                </TableCell>
                                <TableCell>
                                    {campo.sensible ? (
                                        <Badge variant="destructive">
                                            Sensible
                                        </Badge>
                                    ) : (
                                        '—'
                                    )}
                                </TableCell>
                                <TableCell className="text-right">
                                    <Dialog
                                        open={dialogAbierto === campo.id}
                                        onOpenChange={(open) =>
                                            setDialogAbierto(
                                                open ? campo.id : null,
                                            )
                                        }
                                    >
                                        <DialogTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                            >
                                                Editar
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogTitle>
                                                Editar «{campo.clave}»
                                            </DialogTitle>
                                            <CampoForm
                                                forma={forma.value}
                                                campo={campo}
                                                onDone={() =>
                                                    setDialogAbierto(null)
                                                }
                                            />
                                        </DialogContent>
                                    </Dialog>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="text-red-600"
                                        onClick={() => {
                                            if (
                                                confirm(
                                                    `¿Eliminar «${campo.clave}»? Los datos ya cargados de clientes no se borran.`,
                                                )
                                            ) {
                                                router.delete(
                                                    CatalogoController.destroy(
                                                        campo.id,
                                                    ).url,
                                                );
                                            }
                                        }}
                                    >
                                        Eliminar
                                    </Button>
                                </TableCell>
                            </TableRow>
                        ))}

                        {campos.length === 0 && (
                            <TableRow>
                                <TableCell
                                    colSpan={5}
                                    className="text-center text-muted-foreground"
                                >
                                    Sin campos definidos.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </Card>
        </div>
    );
}

export default function CatalogoIndex({
    formas,
    campos,
}: {
    formas: FormaOption[];
    campos: CampoCatalogo[];
}) {
    return (
        <>
            <Head title="Catálogo" />

            <div className="space-y-8 p-4">
                <div>
                    <h1 className="text-xl font-semibold">
                        Catálogo de campos
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Qué campos pide cada formulario. Eliminar una
                        definición no borra los datos ya cargados de
                        clientes — solo deja de pedirse y de contar para la
                        completitud.
                    </p>
                </div>

                {formas.map((forma) => (
                    <FormaSection
                        key={forma.value}
                        forma={forma}
                        campos={campos.filter((c) => c.forma === forma.value)}
                    />
                ))}
            </div>
        </>
    );
}

CatalogoIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Catálogo', href: catalogoIndex() },
    ],
};
