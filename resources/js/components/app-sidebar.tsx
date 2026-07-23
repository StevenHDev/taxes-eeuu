import { Link, usePage } from '@inertiajs/react';
import {
    Code2,
    LayoutGrid,
    ListChecks,
    UserCog,
    Users,
} from 'lucide-react';
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
import { index as apiDocsIndex } from '@/routes/api-docs';
import { index as catalogoIndex } from '@/routes/catalogo';
import { index as clientesIndex } from '@/routes/clientes';
import { index as usuariosIndex } from '@/routes/usuarios';
import type { NavItem } from '@/types';

type PageProps = {
    auth: { user: { role: 'client' | 'preparer' | 'administrator' } };
};

export function AppSidebar() {
    const { auth } = usePage<PageProps>().props;
    const esAdministrador = auth.user.role === 'administrator';
    const tieneAccesoAlPanel = auth.user.role !== 'client';

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        ...(tieneAccesoAlPanel
            ? [
                  {
                      title: 'Clientes',
                      href: clientesIndex(),
                      icon: Users,
                  },
              ]
            : []),
        ...(esAdministrador
            ? [
                  {
                      title: 'Catálogo',
                      href: catalogoIndex(),
                      icon: ListChecks,
                  },
                  {
                      title: 'Usuarios',
                      href: usuariosIndex(),
                      icon: UserCog,
                  },
              ]
            : []),
        {
            title: 'Documentación API',
            href: apiDocsIndex(),
            icon: Code2,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <Link
                    href={dashboard()}
                    prefetch
                    className="flex w-full items-center justify-center rounded-md px-1 py-1 group-data-[collapsible=icon]:px-0"
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
