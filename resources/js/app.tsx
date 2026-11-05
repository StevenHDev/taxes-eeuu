import { createInertiaApp } from '@inertiajs/react';
import { configureEcho } from '@laravel/echo-react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import i18n, { isLanguage } from '@/i18n';
import AgenteLayout from '@/layouts/agente/layout';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import CatalogoFiscalLayout from '@/layouts/catalogo-fiscal/layout';
import FichaLayout from '@/layouts/ficha/layout';
import SettingsLayout from '@/layouts/settings/layout';

configureEcho({
    broadcaster: 'reverb',
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            case name.startsWith('agente/'):
                return [AppLayout, AgenteLayout];
            case name.startsWith('catalogo-fiscal/'):
                return [AppLayout, CatalogoFiscalLayout];
            case name.startsWith('ficha/'):
                return [AppLayout, FichaLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app, { ssr, page }) {
        const locale = page.props.locale;

        if (ssr && isLanguage(locale) && i18n.language !== locale) {
            void i18n.changeLanguage(locale);
        }

        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
