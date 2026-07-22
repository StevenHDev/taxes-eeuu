import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { index as apiDocsIndex } from '@/routes/api-docs';

export default function ApiDocs({ html }: { html: string }) {
    return (
        <>
            <Head title="Documentación de la API" />

            <div className="space-y-6 px-4 py-6">
                <Heading
                    title="Documentación de la API"
                    description="Cómo autenticarte y usar cada endpoint para subir y consultar documentos fiscales."
                />

                <div
                    className="prose prose-sm max-w-none dark:prose-invert prose-pre:overflow-x-auto prose-table:block prose-table:overflow-x-auto"
                    dangerouslySetInnerHTML={{ __html: html }}
                />
            </div>
        </>
    );
}

ApiDocs.layout = {
    breadcrumbs: [
        {
            title: 'Documentación de la API',
            href: apiDocsIndex(),
        },
    ],
};
