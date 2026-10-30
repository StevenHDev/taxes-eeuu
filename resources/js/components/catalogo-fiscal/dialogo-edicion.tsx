import { TriangleAlert } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

/**
 * Diálogo de edición de una pieza del catálogo (F1.5): un solo botón
 * principal ("Guardar"), deshabilitado con "Guardando…" mientras se envía
 * para no mandar dos veces, y el aviso si el año ya está vigente.
 */
export function DialogoEdicion({
    abierto,
    onCerrar,
    titulo,
    descripcion,
    anioVigente,
    anio,
    procesando,
    onGuardar,
    children,
}: {
    abierto: boolean;
    onCerrar: () => void;
    titulo: string;
    descripcion?: string;
    anioVigente: boolean;
    anio: number;
    procesando: boolean;
    onGuardar: () => void;
    children: ReactNode;
}) {
    const { t } = useTranslation();

    const enviar = (e: FormEvent) => {
        e.preventDefault();
        onGuardar();
    };

    return (
        <Dialog
            open={abierto}
            onOpenChange={(v) => (v || procesando ? null : onCerrar())}
        >
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <form onSubmit={enviar} className="grid gap-5" noValidate>
                    <DialogHeader>
                        <DialogTitle>{titulo}</DialogTitle>
                        {descripcion ? (
                            <DialogDescription>{descripcion}</DialogDescription>
                        ) : null}
                    </DialogHeader>

                    {anioVigente ? (
                        <Alert>
                            <TriangleAlert aria-hidden="true" />
                            <AlertDescription>
                                {t('catalogoFiscal.edicion.anioVigente', {
                                    anio,
                                })}
                            </AlertDescription>
                        </Alert>
                    ) : null}

                    {children}

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={procesando}
                            >
                                {t('common.cancel')}
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={procesando}>
                            {procesando ? <Spinner aria-hidden="true" /> : null}
                            {procesando
                                ? t('catalogoFiscal.edicion.guardando')
                                : t('common.save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
