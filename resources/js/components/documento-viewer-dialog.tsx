import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { CampoDocumento } from '@/types/tax-event';

/**
 * Visor de documentos a pantalla completa (100% ancho/alto) con botón de
 * descarga. El archivo se sirve inline vía URL firmada temporal
 * (preview_url); los PDFs y formatos que el navegador entiende se renderizan
 * en el iframe, las imágenes con <img>.
 *
 * Compartido entre clientes/show.tsx (vista del preparador) y
 * portal/formulario.tsx (el cliente viendo su propio documento ya subido) —
 * antes solo existía en clientes/show.tsx, así que el cliente no tenía forma
 * de revisar qué subió sin descargarlo a ciegas.
 */
export function DocumentoViewerDialog({
    documento,
    trigger,
}: {
    documento: CampoDocumento;
    trigger?: React.ReactNode;
}) {
    const { t } = useTranslation();
    const esImagen = documento.file_mime_type?.startsWith('image/');

    return (
        <Dialog>
            <DialogTrigger asChild>
                {trigger ?? (
                    <Button
                        variant="link"
                        className="h-auto max-w-full justify-start truncate p-0 underline"
                        title={t('clienteShow.viewer.viewTitle', {
                            name: documento.file_original_name,
                        })}
                    >
                        {documento.file_original_name}
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="flex h-screen w-screen max-w-none flex-col gap-0 rounded-none border-0 p-0 sm:max-w-none">
                <DialogTitle className="sr-only">
                    {documento.file_original_name}
                </DialogTitle>
                <div className="flex items-center justify-between gap-4 border-b bg-background px-4 py-3">
                    <span className="truncate text-sm font-medium">
                        {documento.file_original_name}
                    </span>
                    <div className="flex items-center gap-2 pr-10">
                        {documento.download_url && (
                            <a
                                href={documento.download_url}
                                download={documento.file_original_name}
                            >
                                <Button size="sm">
                                    {t('common.download')}
                                </Button>
                            </a>
                        )}
                    </div>
                </div>
                <div className="min-h-0 flex-1 bg-muted">
                    {documento.preview_url ? (
                        esImagen ? (
                            <div className="flex h-full w-full items-center justify-center overflow-auto p-4">
                                <img
                                    src={documento.preview_url}
                                    alt={documento.file_original_name}
                                    className="max-h-full max-w-full object-contain"
                                />
                            </div>
                        ) : (
                            <iframe
                                src={documento.preview_url}
                                title={documento.file_original_name}
                                className="h-full w-full border-0"
                            />
                        )
                    ) : (
                        <div className="flex h-full items-center justify-center p-6 text-center text-sm text-muted-foreground">
                            {t('clienteShow.viewer.noPreview')}
                            {documento.download_url && (
                                <>
                                    {' '}
                                    <a
                                        href={documento.download_url}
                                        className="underline"
                                    >
                                        {t('clienteShow.viewer.downloadFile')}
                                    </a>
                                    .
                                </>
                            )}
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
