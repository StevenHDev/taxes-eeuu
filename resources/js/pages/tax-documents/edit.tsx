import { Form, Head } from '@inertiajs/react';
import TaxDocumentController from '@/actions/App/Http/Controllers/TaxDocumentController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/tax-documents';
import type {
    TaxDocument,
    TaxDocumentClient,
    TaxDocumentTypeOption,
} from '@/types';
import TaxDocumentFormFields from './form-fields';

export default function Edit({
    document,
    types,
    clients,
}: {
    document: TaxDocument;
    types: TaxDocumentTypeOption[];
    clients: TaxDocumentClient[];
}) {
    return (
        <>
            <Head title="Editar documento fiscal" />

            <div className="px-4 py-6">
                <Heading
                    title="Editar documento fiscal"
                    description={document.title}
                />

                <Form
                    {...TaxDocumentController.update.form(document.id)}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <TaxDocumentFormFields
                                types={types}
                                clients={clients}
                                errors={errors}
                                document={document}
                            />

                            <div className="flex items-center gap-4">
                                <Button disabled={processing}>
                                    Guardar cambios
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

Edit.layout = {
    breadcrumbs: [
        { title: 'Documentos fiscales', href: index() },
        { title: 'Editar', href: index() },
    ],
};
