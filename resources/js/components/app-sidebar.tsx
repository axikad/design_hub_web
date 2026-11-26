import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    Archive,
    Cog,
    Cpu,
    FileText,
    FileType2,
    FolderTree,
    Landmark,
    Layers,
    LayoutGrid,
    Palette,
    Settings,
    ShieldCheck,
    ShoppingBag,
    TableProperties,
    UsersRound,
    WalletCards,
} from 'lucide-react';
import AppLogo from './app-logo';

const workspaceNavItems: NavItem[] = [
    {
        title: 'Overview',
        url: '/dashboard',
        icon: LayoutGrid,
        permission: 'dashboard.read',
    },
    {
        title: 'Designs',
        url: '/admin/designs',
        icon: Palette,
        permission: 'designs.read',
    },
    {
        title: 'Folders',
        url: '/admin/folders',
        icon: Archive,
        permission: 'folders.read',
    },
    {
        title: 'Catalogs',
        url: '/admin/catalogs',
        icon: FolderTree,
        permission: 'catalogs.read',
    },
    {
        title: 'My Wallet',
        url: '/admin/wallet',
        icon: WalletCards,
        permission: 'wallet.view_own',
    },
    {
        title: 'Purchases',
        url: '/admin/purchases',
        icon: ShoppingBag,
        permission: 'purchases.read',
    },
    {
        title: 'Withdrawals',
        url: '/admin/withdrawals',
        icon: Landmark,
        permission: 'withdrawals.view_any',
    },
];

const catalogNavItems: NavItem[] = [
    { title: 'Machines', url: '/admin/machines', icon: Cpu, permission: 'machines.read' },
    {
        title: 'Machine Types',
        url: '/admin/machine-types',
        icon: Cog,
        permission: 'machine_types.read',
    },
    {
        title: 'Categories',
        url: '/admin/categories',
        icon: FolderTree,
        permission: 'categories.read',
    },
    {
        title: 'Design Types',
        url: '/admin/types',
        icon: Layers,
        permission: 'design_types.read',
    },
    {
        title: 'Design File Types',
        url: '/admin/design-file-types',
        icon: FileType2,
        permission: 'design_file_types.read',
    },
];

const administrationNavItems: NavItem[] = [
    {
        title: 'Users',
        url: '/admin/users',
        icon: UsersRound,
        permission: 'users.read',
    },
    {
        title: 'Roles & permissions',
        url: '/admin/roles',
        icon: ShieldCheck,
        permission: 'roles.read',
    },
    {
        title: 'Seller payments',
        url: '/admin/settings/seller-payments',
        icon: WalletCards,
        permission: 'seller_payments.read',
    },
    {
        title: 'Settlement earnings',
        url: '/admin/settlement-earnings',
        icon: TableProperties,
        permission: 'seller_payments.read',
    },
    {
        title: 'Content pages',
        url: '/admin/content-pages',
        icon: FileText,
        permission: 'branding.read',
    },
    {
        title: 'Site settings',
        url: '/admin/settings/site',
        icon: Settings,
        permission: 'branding.read',
    },
];

export function AppSidebar() {
    const permissions = usePage<SharedData>().props.auth.permissions ?? [];
    const visibleItems = (items: NavItem[]) => items.filter((item) => !item.permission || permissions.includes(item.permission));

    return (
        <Sidebar collapsible="icon" variant="inset" className="border-r-0">
            <SidebarHeader className="border-b border-slate-200/80 p-3 dark:border-slate-800">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild className="h-12 rounded-xl hover:bg-indigo-50 dark:hover:bg-indigo-950/40">
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="py-3">
                <NavMain label="Workspace" items={visibleItems(workspaceNavItems)} />
                <NavMain label="Catalog" items={visibleItems(catalogNavItems)} />
                <NavMain label="Administration" items={visibleItems(administrationNavItems)} />
            </SidebarContent>

            <SidebarFooter className="border-t border-slate-200/80 p-3 dark:border-slate-800">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
