import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { index as apiDocsIndex } from '@/routes/api-docs';

export default function ApiDocs({ html }: { html: string }) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('apiDocs.title')} />

            <div className="space-y-6 px-4 py-6">
                <Heading
                    title={t('apiDocs.title')}
                    description={t('apiDocs.intro')}
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
            title: 'nav.apiDocs',
            href: apiDocsIndex(),
        },
    ],
};
