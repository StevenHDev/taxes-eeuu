import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { DocumentoFila } from '@/components/entrevista/documento-fila';
import { Button } from '@/components/ui/button';
import { inicio } from '@/routes/portal/entrevista';
import type { DocumentoEntrevista } from '@/types/entrevista';

/**
 * Documentos que pide la entrevista (F2.6): los de siempre y los que activan
 * las respuestas, con su estado y la carga. Solo por el portal.
 */
export default function DocumentosEntrevista({
    documentos,
}: {
    taxYear: number;
    documentos: DocumentoEntrevista[];
    entrevistaCompleta: boolean;
}) {
    const { t } = useTranslation();
    const faltan = documentos.filter((d) => d.archivos.length === 0).length;

    return (
        <>
            <Head title={t('entrevista.documentos.titulo')} />

            <div className="mx-auto w-full max-w-xl space-y-6 p-4 sm:p-6">
                <header className="space-y-1">
                    <h1 className="font-display text-display text-foreground">
                        {t('entrevista.documentos.titulo')}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {documentos.length === 0
                            ? t('entrevista.documentos.vacio')
                            : faltan > 0
                              ? t('entrevista.documentos.faltan', {
                                    count: faltan,
                                })
                              : t('entrevista.documentos.todos')}
                    </p>
                </header>

                {documentos.length > 0 ? (
                    <ul className="space-y-3">
                        {documentos.map((d) => (
                            <DocumentoFila
                                key={`${d.codigo}-${d.persona_id ?? ''}`}
                                documento={d}
                            />
                        ))}
                    </ul>
                ) : null}

                <Button asChild variant="ghost">
                    <Link href={inicio()} prefetch>
                        <ArrowLeft aria-hidden="true" />
                        {t('entrevista.documentos.volver')}
                    </Link>
                </Button>
            </div>
        </>
    );
}
