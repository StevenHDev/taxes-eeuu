import { Form, Head } from '@inertiajs/react';
import TaxDocumentController from '@/actions/App/Http/Controllers/TaxDocumentController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/tax-documents';
import type { TaxDocumentClient, TaxDocumentTypeOption } from '@/types';
import TaxDocumentFormFields from './form-fields';

export default function Create({
    types,
    clients,
}: {
    types: TaxDocumentTypeOption[];
    clients: TaxDocumentClient[];
}) {
    return (
        <>
            <Head title="Nuevo documento fiscal" />

            <div className="px-4 py-6">
                <Heading
                    title="Nuevo documento fiscal"
                    description="Agrega un documento o dato necesario para preparar la declaración."
                />

                <Form
                    {...TaxDocumentController.store.form()}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <TaxDocumentFormFields
                                types={types}
                                clients={clients}
                                errors={errors}
                            />

                            <div className="flex items-center gap-4">
                                <Button disabled={processing}>Guardar</Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

Create.layout = {
    breadcrumbs: [
        { title: 'Documentos fiscales', href: index() },
        { title: 'Nuevo', href: index() },
    ],
};
