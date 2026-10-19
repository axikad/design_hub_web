import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { Cog, Cpu, FileType2, FolderTree, Layers, LayoutGrid, Palette, Settings, ShieldCheck, ShoppingBag, UsersRound } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Overview',
        url: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Machines',
        url: '/admin/machines',
        icon: Cpu,
    },
    {
        title: 'Machine Types',
        url: '/admin/machine-types',
        icon: Cog,
    },
    {
        title: 'Categories',
        url: '/admin/categories',
        icon: FolderTree,
    },
    {
        title: 'Design Types',
        url: '/admin/types',
        icon: Layers,
    },
    {
        title: 'Design File Types',
        url: '/admin/design-file-types',
        icon: FileType2,
    },
    {
        title: 'Designs',
        url: '/admin/designs',
        icon: Palette,
    },
    {
        title: 'Users',
        url: '/admin/users',
        icon: UsersRound,
    },
    {
        title: 'Roles & permissions',
        url: '/admin/roles',
        icon: ShieldCheck,
    },
    {
        title: 'Purchases',
        url: '/admin/purchases',
        icon: ShoppingBag,
    },
    {
        title: 'Site settings',
        url: '/admin/settings/site',
        icon: Settings,
    },
];

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
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
