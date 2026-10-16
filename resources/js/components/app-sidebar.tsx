import { Link, usePage } from '@inertiajs/react';
import {
    Bot,
    ClipboardCheck,
    Code2,
    FileText,
    History,
    LayoutGrid,
    ListChecks,
    UserCog,
    Users,
    Workflow,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as agenteMensajesIndex } from '@/routes/agente/mensajes';
import { index as apiDocsIndex } from '@/routes/api-docs';
import { index as bitacoraIndex } from '@/routes/bitacora';
import { index as catalogoIndex } from '@/routes/catalogo';
import { index as clientesIndex } from '@/routes/clientes';
import { index as metaAgenteIndex } from '@/routes/meta-agente';
import { formulario as portalFormulario } from '@/routes/portal';
import { show as procesoShow } from '@/routes/proceso';
import { index as usuariosIndex } from '@/routes/usuarios';
import type { NavItem } from '@/types';

type PageProps = {
    auth: { user: { role: 'client' | 'preparer' | 'administrator' } };
};

export function AppSidebar() {
    const { t } = useTranslation();
    const { auth } = usePage<PageProps>().props;
    const esAdministrador = auth.user.role === 'administrator';
    const esCliente = auth.user.role === 'client';
    const tieneAccesoAlPanel = auth.user.role !== 'client';

    const mainNavItems: NavItem[] = [
        {
            title: t('nav.dashboard'),
            href: dashboard(),
            icon: LayoutGrid,
        },
        ...(esCliente
            ? [
                  // El chat del portal (portal.chat) sigue existiendo y
                  // PortalFormularioController redirige ahí mientras el
                  // cliente no tenga forma declarada — a propósito ya no
                  // tiene entrada en el sidebar (pedido explícito): solo
                  // Panel y Formulario.
                  {
                      title: t('nav.portalFormulario'),
                      href: portalFormulario(),
                      icon: FileText,
                  },
              ]
            : []),
        ...(tieneAccesoAlPanel
            ? [
                  {
                      title: t('nav.clients'),
                      href: clientesIndex(),
                      icon: Users,
                  },
                  {
                      title: t('nav.proceso'),
                      href: procesoShow(),
                      icon: Workflow,
                  },
              ]
            : []),
        ...(esAdministrador
            ? [
                  {
                      title: t('nav.catalog'),
                      href: catalogoIndex(),
                      icon: ListChecks,
                  },
                  {
                      title: t('nav.users'),
                      href: usuariosIndex(),
                      icon: UserCog,
                  },
                  {
                      title: t('nav.auditLog'),
                      href: bitacoraIndex(),
                      icon: History,
                  },
                  {
                      title: t('nav.agente'),
                      href: agenteMensajesIndex(),
                      icon: Bot,
                  },
                  {
                      title: t('nav.metaAgente'),
                      href: metaAgenteIndex(),
                      icon: ClipboardCheck,
                  },
              ]
            : []),
        ...(esCliente
            ? []
            : [
                  {
                      title: t('nav.apiDocs'),
                      href: apiDocsIndex(),
                      icon: Code2,
                  },
              ]),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <Link
                    href={dashboard()}
                    prefetch
                    // bg-white a propósito: el logo (logo.png/logo-mark.png) es azul
                    // marino sobre fondo transparente — ahora que el sidebar es azul
                    // (ver resources/css/app.css --sidebar), sin esta tarjeta blanca
                    // quedaría casi invisible.
                    className="flex w-full items-center justify-center rounded-md bg-white px-1 py-1 group-data-[collapsible=icon]:px-0"
                >
                    <AppLogo />
                </Link>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
