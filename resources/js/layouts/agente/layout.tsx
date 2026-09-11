import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { index as mensajesIndex } from '@/routes/agente/mensajes';
import { index as promptsIndex } from '@/routes/agente/prompts';
import type { NavItem } from '@/types';

/**
 * Layout compartido de la sección "Agente" (panel de administración del
 * agente conversacional de WhatsApp) — mismo patrón de tabs que
 * SettingsLayout. Mensajes es la primera pestaña; Prompts, Tools y Base de
 * Conocimiento se agregan acá a medida que se construyen (ver
 * docs/implementar_agente_n8n.md).
 */
export default function AgenteLayout({ children }: PropsWithChildren) {
    const { t } = useTranslation();
    const { isCurrentOrParentUrl } = useCurrentUrl();

    const navItems: NavItem[] = [
        {
            title: t('agente.nav.mensajes'),
            href: mensajesIndex(),
            icon: null,
        },
        {
            title: t('agente.nav.prompts'),
            href: promptsIndex(),
            icon: null,
        },
    ];

    return (
        <div className="px-4 py-6">
            <Heading
                title={t('agente.layout.title')}
                description={t('agente.layout.description')}
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav
                        className="flex flex-col space-y-1 space-x-0"
                        aria-label={t('agente.layout.navAriaLabel')}
                    >
                        {navItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted': isCurrentOrParentUrl(item.href),
                                })}
                            >
                                <Link href={item.href}>
                                    {item.icon && (
                                        <item.icon className="h-4 w-4" />
                                    )}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="flex-1">
                    <section className="space-y-6">{children}</section>
                </div>
            </div>
        </div>
    );
}
