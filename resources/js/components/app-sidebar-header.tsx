import { router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { LanguageSwitcher } from '@/components/language-switcher';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { index as clientesIndex } from '@/routes/clientes';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

type PageProps = {
    auth: { user: { role: 'client' | 'preparer' | 'administrator' } };
};

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { t } = useTranslation();
    const { auth } = usePage<PageProps>().props;
    const tieneAccesoAlPanel = auth.user.role !== 'client';
    const [search, setSearch] = useState('');

    return (
        <header className="flex h-16 shrink-0 items-center gap-4 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            <div className="ml-auto flex items-center gap-2">
                {tieneAccesoAlPanel && (
                    <form
                        className="hidden w-full max-w-xs sm:block"
                        onSubmit={(e) => {
                            e.preventDefault();
                            router.get(
                                clientesIndex.url({ query: { search } }),
                            );
                        }}
                    >
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                type="search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder={t('nav.searchClient')}
                                className="h-10 w-full rounded-full border border-input bg-secondary/60 pr-3 pl-9 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            />
                        </div>
                    </form>
                )}

                <LanguageSwitcher />
            </div>
        </header>
    );
}
