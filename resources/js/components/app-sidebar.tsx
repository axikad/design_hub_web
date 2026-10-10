import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { FolderTree, LayoutGrid, Layers, Palette, Settings, ShieldCheck, ShoppingBag, UsersRound } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Overview',
        url: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Categories',
        url: '/admin/categories',
        icon: FolderTree,
    },
    {
        title: 'Types',
        url: '/admin/types',
        icon: Layers,
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
