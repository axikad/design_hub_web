import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';

export function NavMain({ items = [], label = 'Workspace' }: { items: NavItem[]; label?: string }) {
    const page = usePage();

    const isActive = (url: string) => page.url === url || page.url.startsWith(`${url}/`) || page.url.startsWith(`${url}?`);

    if (items.length === 0) {
        return null;
    }

    return (
        <SidebarGroup className="px-3 py-2 group-data-[collapsible=icon]:px-2">
            <SidebarGroupLabel className="px-2 text-[10px] font-bold tracking-[0.16em] text-slate-400 uppercase group-data-[collapsible=icon]:hidden">
                {label}
            </SidebarGroupLabel>
            <SidebarMenu className="gap-1">
                {items.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            isActive={isActive(item.url)}
                            tooltip={item.title}
                            className="h-10 rounded-xl px-3 text-slate-600 transition-colors hover:bg-indigo-50 hover:text-indigo-700 data-[active=true]:bg-indigo-600 data-[active=true]:font-semibold data-[active=true]:text-white data-[active=true]:shadow-md data-[active=true]:shadow-indigo-500/20 dark:text-slate-300 dark:hover:bg-indigo-950/60 dark:hover:text-indigo-300 dark:data-[active=true]:bg-indigo-600 dark:data-[active=true]:text-white"
                        >
                            <Link href={item.url} prefetch>
                                {item.icon && <item.icon className="size-4.5" />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
