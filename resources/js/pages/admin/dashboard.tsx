import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

type DashboardProps = {
    stats: {
        users: number;
        designs: number;
        purchases: number;
        revenuePaise: number;
    };
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Overview', href: '/dashboard' }];

function formatInr(paise: number): string {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(paise / 100);
}

export default function AdminDashboard({ stats }: DashboardProps) {
    const cards = [
        { label: 'Registered users', value: stats.users.toString() },
        { label: 'Live designs', value: stats.designs.toString() },
        { label: 'Completed purchases', value: stats.purchases.toString() },
        { label: 'Gross revenue', value: formatInr(stats.revenuePaise) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="DesignHub Admin" />
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-slate-950 dark:text-white">DesignHub control panel</h1>
                    {/* <p className="mt-1 text-sm text-slate-600 dark:text-slate-400">
                        Monitor marketplace activity for the mobile embroidery app. API base path: <code className="rounded bg-slate-100 px-1.5 py-0.5 dark:bg-slate-800">/api/v1</code>
                    </p> */}
                </div>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {cards.map((card) => (
                        <div
                            key={card.label}
                            className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                        >
                            <p className="text-xs font-semibold uppercase tracking-[0.14em] text-indigo-600 dark:text-indigo-300">{card.label}</p>
                            <p className="mt-3 text-3xl font-bold text-slate-950 dark:text-white">{card.value}</p>
                        </div>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
