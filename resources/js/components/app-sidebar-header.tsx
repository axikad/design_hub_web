import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { type BreadcrumbItem as BreadcrumbItemType, type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    const { auth } = usePage<SharedData>().props;
    const getInitials = useInitials();
    const currentPage = breadcrumbs.at(-1)?.title ?? 'Dashboard';

    return (
        <header className="sticky top-0 z-30 flex h-16 shrink-0 items-center border-b border-slate-200/80 bg-white/90 px-4 backdrop-blur-xl transition-[width,height] ease-linear md:px-6 dark:border-slate-800 dark:bg-slate-950/85">
            <div className="flex min-w-0 items-center gap-3">
                <SidebarTrigger className="-ml-1 size-9 rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300" />
                <div className="hidden h-5 w-px bg-slate-200 sm:block dark:bg-slate-800" />
                <div className="min-w-0">
                    <p className="truncate text-sm font-bold text-slate-900 dark:text-white">{currentPage}</p>
                    {breadcrumbs.length > 1 ? (
                        <div className="mt-0.5 hidden items-center gap-1 text-xs text-slate-400 sm:flex">
                            <ChevronRight className="size-3" />
                            <Breadcrumbs breadcrumbs={breadcrumbs} />
                        </div>
                    ) : null}
                </div>
            </div>

            <div className="ml-auto flex items-center gap-1.5">
                <AppearanceToggleDropdown />
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" className="h-10 gap-2 rounded-xl px-1.5 sm:px-2">
                            <Avatar className="size-8 rounded-lg">
                                <AvatarImage src={auth.user.avatar} alt={auth.user.name} />
                                <AvatarFallback className="rounded-lg bg-indigo-100 text-xs font-bold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                                    {getInitials(auth.user.name)}
                                </AvatarFallback>
                            </Avatar>
                            <span className="hidden max-w-32 truncate text-sm font-semibold text-slate-700 sm:block dark:text-slate-200">
                                {auth.user.name}
                            </span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent className="w-64 rounded-xl" align="end">
                        <UserMenuContent user={auth.user} />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>
    );
}
