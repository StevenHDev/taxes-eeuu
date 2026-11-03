import { Form } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

/**
 * Diálogo de una acción sobre una excepción que pide un texto requerido:
 * aceptar con nota (campo `nota`) o consultar al cliente (campo `texto`).
 * El error de validación del servidor se muestra bajo el campo.
 */
export function ResolverExcepcionDialog({
    action,
    campo,
    trigger,
    titulo,
    descripcion,
    etiqueta,
    placeholder,
    enviar,
}: {
    action: string;
    campo: 'nota' | 'texto';
    trigger: ReactNode;
    titulo: string;
    descripcion: string;
    etiqueta: string;
    placeholder: string;
    enviar: string;
}) {
    const { t } = useTranslation();
    const [abierto, setAbierto] = useState(false);
    const id = `excepcion-${campo}-${action}`;

    return (
        <Dialog open={abierto} onOpenChange={setAbierto}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogTitle>{titulo}</DialogTitle>
                <DialogDescription>{descripcion}</DialogDescription>
                <Form
                    action={action}
                    method="post"
                    onSuccess={() => setAbierto(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={id}>{etiqueta}</Label>
                                <Textarea
                                    id={id}
                                    name={campo}
                                    required
                                    rows={4}
                                    placeholder={placeholder}
                                    aria-invalid={
                                        errors[campo] ? true : undefined
                                    }
                                />
                                <InputError message={errors[campo]} />
                            </div>
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setAbierto(false)}
                                >
                                    {t('excepciones.dialogo.cancelar')}
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {enviar}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
