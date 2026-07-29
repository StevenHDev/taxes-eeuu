import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { index as apiDocsIndex, openapi } from '@/routes/api-docs';

type TocItem = { title: string; slug: string };
type TocSection = TocItem & { children: TocItem[] };

// Nav lateral con el ítem activo resaltado según qué encabezado está a la
// vista — la misma tabla de contenidos sirve para navegar y para ubicarse en
// un documento de referencia largo (384+ líneas de markdown, sin esto no hay
// forma de saber dónde se está parado salvo hacer scroll a ciegas).
function useActiveSlug(contentRef: React.RefObject<HTMLDivElement | null>) {
    const [activeSlug, setActiveSlug] = useState<string | null>(null);

    useEffect(() => {
        const headings = contentRef.current?.querySelectorAll('h2[id], h3[id]');

        if (!headings || headings.length === 0) {
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                const visible = entries
                    .filter((e) => e.isIntersecting)
                    .sort(
                        (a, b) =>
                            a.boundingClientRect.top - b.boundingClientRect.top,
                    );

                if (visible[0]) {
                    setActiveSlug(visible[0].target.id);
                }
            },
            { rootMargin: '0px 0px -70% 0px', threshold: 0 },
        );

        headings.forEach((h) => observer.observe(h));

        return () => observer.disconnect();
    }, [contentRef]);

    return activeSlug;
}

function TocLinks({
    toc,
    activeSlug,
    onNavigate,
}: {
    toc: TocSection[];
    activeSlug: string | null;
    onNavigate?: () => void;
}) {
    return (
        <ul className="space-y-3">
            {toc.map((section) => (
                <li key={section.slug}>
                    <a
                        href={`#${section.slug}`}
                        data-active={activeSlug === section.slug}
                        onClick={onNavigate}
                        className="block border-l-2 border-transparent py-0.5 pl-3 text-sm text-muted-foreground transition-colors hover:text-foreground"
                    >
                        {section.title}
                    </a>
                    {section.children.length > 0 && (
                        <ul className="mt-1 space-y-1">
                            {section.children.map((child) => (
                                <li key={child.slug}>
                                    <a
                                        href={`#${child.slug}`}
                                        data-active={activeSlug === child.slug}
                                        onClick={onNavigate}
                                        className="block border-l-2 border-transparent py-0.5 pl-6 font-mono text-xs text-muted-foreground transition-colors hover:text-foreground"
                                    >
                                        {child.title}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    )}
                </li>
            ))}
        </ul>
    );
}

export default function ApiDocs({
    html,
    toc,
}: {
    html: string;
    toc: TocSection[];
}) {
    const { t } = useTranslation();
    const contentRef = useRef<HTMLDivElement>(null);
    const activeSlug = useActiveSlug(contentRef);

    return (
        <>
            <Head title={t('apiDocs.title')} />

            <div className="space-y-6 px-4 py-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title={t('apiDocs.title')}
                        description={t('apiDocs.intro')}
                    />
                    <a
                        href={openapi().url}
                        download="openapi.yaml"
                        className="inline-flex shrink-0 items-center gap-1.5 rounded-md border px-3 py-2 font-mono text-xs text-muted-foreground transition-colors hover:border-primary hover:text-foreground"
                    >
                        <Download className="size-3.5" aria-hidden />
                        {t('apiDocs.downloadSpec')}
                    </a>
                </div>

                {/* Mobile: la tabla de contenidos vive en un <details> nativo,
                    sin JS — a partir de lg el nav lateral fijo la reemplaza. */}
                <details className="rounded-md border p-3 lg:hidden">
                    <summary className="cursor-pointer font-mono text-xs tracking-wide text-muted-foreground uppercase">
                        {t('apiDocs.contents')}
                    </summary>
                    <nav className="mt-3">
                        <TocLinks toc={toc} activeSlug={activeSlug} />
                    </nav>
                </details>

                <div className="grid grid-cols-1 gap-10 lg:grid-cols-[220px_1fr]">
                    <nav
                        aria-label={t('apiDocs.contents')}
                        className="api-docs-nav sticky top-6 hidden max-h-[calc(100vh-3rem)] self-start overflow-y-auto lg:block"
                    >
                        <TocLinks toc={toc} activeSlug={activeSlug} />
                    </nav>

                    <div
                        ref={contentRef}
                        className="api-docs-content min-w-0"
                        dangerouslySetInnerHTML={{ __html: html }}
                    />
                </div>
            </div>
        </>
    );
}

ApiDocs.layout = {
    breadcrumbs: [
        {
            title: 'nav.apiDocs',
            href: apiDocsIndex,
        },
    ],
};
