import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Code2,
    FolderGit2,
    LayoutGrid,
    ListChecks,
    UserCog,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
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

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

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
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
