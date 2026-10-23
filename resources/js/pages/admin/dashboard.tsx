import { AdminStatusBadge } from '@/components/admin/admin-status-badge';
import { AdminTablePanel } from '@/components/admin/admin-table-panel';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, CreditCard, IndianRupee, Palette, ShoppingBag, UsersRound } from 'lucide-react';

type Props = {
    stats: { users: number; designs: number; purchases: number; pendingPayments: number; revenuePaise: number };
    recentDesigns: { id: string; title: string; owner: string | null; fileType: string | null; createdAt: string | null }[];
    recentPurchases: {
        id: string;
        itemType: 'design' | 'folder';
        itemName: string | null;
        buyer: string | null;
        amountPaise: number;
        currency: string;
        status: string;
        purchasedAt: string | null;
    }[];
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Overview', href: '/dashboard' }];
const formatMoney = (paise: number, currency = 'INR') => new Intl.NumberFormat('en-IN', { style: 'currency', currency }).format(paise / 100);

export default function AdminDashboard({ stats, recentDesigns, recentPurchases }: Props) {
    const permissions = usePage<SharedData>().props.auth.permissions ?? [];
    const cards = [
        {
            label: 'Marketplace users',
            value: stats.users.toLocaleString('en-IN'),
            icon: UsersRound,
            tone: 'bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300',
        },
        {
            label: 'Live designs',
            value: stats.designs.toLocaleString('en-IN'),
            icon: Palette,
            tone: 'bg-violet-50 text-violet-600 dark:bg-violet-950/40 dark:text-violet-300',
        },
        {
            label: 'Paid purchases',
            value: stats.purchases.toLocaleString('en-IN'),
            icon: ShoppingBag,
            tone: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-300',
        },
        {
            label: 'Pending payments',
            value: stats.pendingPayments.toLocaleString('en-IN'),
            icon: CreditCard,
            tone: 'bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-300',
        },
        {
            label: 'Gross revenue',
            value: formatMoney(stats.revenuePaise),
            icon: IndianRupee,
            tone: 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-300',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="DesignHub Admin" />
            <div className="space-y-7 p-4 md:p-6">
                <div className="rounded-2xl bg-gradient-to-br from-indigo-600 via-violet-600 to-purple-700 p-6 text-white shadow-lg shadow-indigo-500/15 md:p-8">
                    <p className="text-sm font-semibold text-indigo-100">DesignHub administration</p>
                    <h1 className="mt-2 text-2xl font-bold tracking-tight md:text-3xl">Marketplace overview</h1>
                    <p className="mt-2 max-w-2xl text-sm leading-6 text-indigo-100">
                        Monitor designs, customers, purchases, and server-side payment activity from one place.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    {cards.map(({ label, value, icon: Icon, tone }) => (
                        <section
                            key={label}
                            className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                        >
                            <div className={`flex size-11 items-center justify-center rounded-xl ${tone}`}>
                                <Icon className="size-5" />
                            </div>
                            <p className="mt-5 text-2xl font-bold tracking-tight text-slate-950 dark:text-white">{value}</p>
                            <p className="mt-1 text-xs font-semibold tracking-wide text-slate-500 uppercase">{label}</p>
                        </section>
                    ))}
                </div>

                <div className="grid gap-5 xl:grid-cols-2">
                    <ActivityPanel title="Recent designs" href={permissions.includes('designs.read') ? route('admin.designs.index') : undefined}>
                        {recentDesigns.length ? (
                            recentDesigns.map((design) => (
                                <div
                                    key={design.id}
                                    className="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4 last:border-0 dark:border-slate-800"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold text-slate-900 dark:text-white">{design.title}</p>
                                        <p className="mt-1 text-xs text-slate-400">
                                            {design.owner || 'Unknown seller'} · {design.fileType || 'No file type'}
                                        </p>
                                    </div>
                                    <span className="shrink-0 text-xs text-slate-400">
                                        {design.createdAt ? new Date(design.createdAt).toLocaleDateString() : ''}
                                    </span>
                                </div>
                            ))
                        ) : (
                            <EmptyActivity text="No designs created yet." />
                        )}
                    </ActivityPanel>

                    <ActivityPanel
                        title="Recent purchases"
                        href={permissions.includes('purchases.read') ? route('admin.purchases.index') : undefined}
                    >
                        {recentPurchases.length ? (
                            recentPurchases.map((purchase) => (
                                <div
                                    key={purchase.id}
                                    className="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4 last:border-0 dark:border-slate-800"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold text-slate-900 dark:text-white">
                                            {purchase.itemName || `Deleted ${purchase.itemType}`}
                                        </p>
                                        <p className="mt-1 text-xs text-slate-400">
                                            {purchase.itemType} · {purchase.buyer || 'Unknown buyer'} ·{' '}
                                            {formatMoney(purchase.amountPaise, purchase.currency)}
                                        </p>
                                    </div>
                                    <AdminStatusBadge
                                        tone={purchase.status === 'paid' ? 'success' : purchase.status === 'pending' ? 'warning' : 'neutral'}
                                    >
                                        {purchase.status}
                                    </AdminStatusBadge>
                                </div>
                            ))
                        ) : (
                            <EmptyActivity text="No purchases recorded yet." />
                        )}
                    </ActivityPanel>
                </div>
            </div>
        </AppLayout>
    );
}

function ActivityPanel({ title, href, children }: { title: string; href?: string; children: React.ReactNode }) {
    return (
        <AdminTablePanel>
            <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <h2 className="text-sm font-bold text-slate-900 dark:text-white">{title}</h2>
                {href ? (
                    <Button variant="ghost" size="sm" asChild className="rounded-lg text-indigo-600">
                        <Link href={href}>
                            View all <ArrowRight className="size-3.5" />
                        </Link>
                    </Button>
                ) : null}
            </div>
            {children}
        </AdminTablePanel>
    );
}

function EmptyActivity({ text }: { text: string }) {
    return <p className="px-5 py-10 text-center text-sm text-slate-400">{text}</p>;
}
