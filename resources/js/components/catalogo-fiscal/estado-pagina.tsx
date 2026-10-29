import { CircleAlert, Inbox } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

/** El año no tiene nada cargado en esta pestaña. */
export function CatalogoVacio({ puedeEditar }: { puedeEditar: boolean }) {
    const { t } = useTranslation();

    return (
        <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed border-border p-10 text-center">
            <Inbox
                className="size-8 text-muted-foreground"
                aria-hidden="true"
            />
            <p className="font-medium">{t('catalogoFiscal.vacio.titulo')}</p>
            <p className="max-w-md text-sm text-muted-foreground">
                {puedeEditar
                    ? t('catalogoFiscal.vacio.administrador')
                    : t('catalogoFiscal.vacio.preparador')}
            </p>
        </div>
    );
}

/** La carga de la tabla falló (prop diferida con rescue). */
export function CatalogoError() {
    const { t } = useTranslation();

    return (
        <Alert variant="destructive">
            <CircleAlert aria-hidden="true" />
            <AlertTitle>{t('catalogoFiscal.error.titulo')}</AlertTitle>
            <AlertDescription>
                {t('catalogoFiscal.error.descripcion')}
            </AlertDescription>
        </Alert>
    );
}
