import { AdminStatusBadge } from '@/components/admin/admin-status-badge';
import { AdminPageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    Archive,
    ArrowLeft,
    File,
    FileArchive,
    FileCode2,
    FileCog,
    FileText,
    Files,
    HardDrive,
    IndianRupee,
    Pencil,
    ShoppingBag,
    WalletCards,
    type LucideIcon,
} from 'lucide-react';

type ExtractedFile = {
    id: number;
    name: string;
    relativePath: string;
    extension: string | null;
    mimeType: string;
    size: number;
    previewUrl: string | null;
};

type FolderDetails = {
    id: string;
    name: string;
    priceRupees: string;
    zipName: string;
    fileCount: number;
    totalSize: number;
    createdAt: string | null;
    updatedAt: string | null;
    paidPurchaseCount: number;
    pendingPurchaseCount: number;
    revenuePaise: number;
    purchases: {
        id: string;
        buyerName: string | null;
        buyerPhone: string | null;
        amountPaise: number;
        currency: string;
        status: string;
        razorpayOrderId: string | null;
        razorpayPaymentId: string | null;
        purchasedAt: string | null;
    }[];
    files: ExtractedFile[];
};

type Props = { folder: FolderDetails };

const money = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' });

function formatBytes(bytes: number) {
    if (bytes < 1024) return `${bytes} B`;
    const units = ['KB', 'MB', 'GB'];
    let value = bytes / 1024;
    let unit = units[0];

    for (let index = 1; value >= 1024 && index < units.length; index++) {
        value /= 1024;
        unit = units[index];
    }

    return `${value.toFixed(value >= 10 ? 1 : 2)} ${unit}`;
}

function iconForFile(extension: string | null): LucideIcon {
    const normalized = extension?.toLowerCase() ?? '';

    if (['zip', 'rar', '7z', 'tar', 'gz'].includes(normalized)) return FileArchive;
    if (['txt', 'md', 'pdf', 'doc', 'docx', 'rtf', 'csv'].includes(normalized)) return FileText;
    if (['html', 'css', 'js', 'json', 'xml'].includes(normalized)) return FileCode2;
    if (['emb', 'dst', 'pes', 'dhb', 'jef', 'exp', 'vp3'].includes(normalized)) return FileCog;

    return File;
}

export default function AdminFolderShow({ folder }: Props) {
    const permissions = usePage<SharedData>().props.auth.permissions ?? [];
    const canUpdate = permissions.includes('folders.update');
    const canReadPurchases = permissions.includes('purchases.read');
    const canUpdatePurchases = permissions.includes('purchases.update');
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Overview', href: '/dashboard' },
        { title: 'Folders', href: '/admin/folders' },
        { title: folder.name, href: '#' },
    ];
    const summaries = [
        { label: 'Price', value: money.format(Number(folder.priceRupees)), icon: IndianRupee },
        { label: 'Extracted files', value: String(folder.fileCount), icon: Files },
        { label: 'Total size', value: formatBytes(folder.totalSize), icon: HardDrive },
        { label: 'Paid purchases', value: String(folder.paidPurchaseCount), icon: ShoppingBag },
        { label: 'Revenue', value: money.format(folder.revenuePaise / 100), icon: WalletCards },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={folder.name} />
            <div className="space-y-6 p-4 md:p-6">
                <AdminPageHeader
                    title={folder.name}
                    description={`Archive: ${folder.zipName}`}
                    actions={
                        <div className="flex gap-2">
                            <Button variant="outline" asChild className="rounded-xl">
                                <Link href={route('admin.folders.index')}>
                                    <ArrowLeft className="size-4" /> Back
                                </Link>
                            </Button>
                            {canUpdate ? (
                                <Button asChild className="rounded-xl bg-indigo-600 hover:bg-indigo-700">
                                    <Link href={route('admin.folders.edit', folder.id)}>
                                        <Pencil className="size-4" /> Edit folder
                                    </Link>
                                </Button>
                            ) : null}
                        </div>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    {summaries.map(({ label, value, icon: Icon }) => (
                        <div
                            key={label}
                            className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                        >
                            <div className="flex items-center gap-3">
                                <span className="flex size-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-300">
                                    <Icon className="size-5" />
                                </span>
                                <div>
                                    <p className="text-xs font-semibold tracking-wide text-slate-400 uppercase">{label}</p>
                                    <p className="mt-1 text-lg font-bold text-slate-900 dark:text-white">{value}</p>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                        <div className="flex items-center gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300">
                                <ShoppingBag className="size-5" />
                            </span>
                            <div>
                                <h2 className="text-sm font-bold text-slate-900 dark:text-white">Folder purchases</h2>
                                <p className="mt-1 text-xs text-slate-500">
                                    {folder.paidPurchaseCount} paid · {folder.pendingPurchaseCount} pending
                                </p>
                            </div>
                        </div>
                        {canReadPurchases ? (
                            <Button variant="outline" size="sm" asChild className="rounded-lg">
                                <Link href={`/admin/purchases?type=folder&search=${encodeURIComponent(folder.id)}`}>View all</Link>
                            </Button>
                        ) : null}
                    </div>
                    {folder.purchases.length ? (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-100 dark:divide-slate-800">
                                <thead className="bg-slate-50/80 dark:bg-slate-950/50">
                                    <tr>
                                        {['Buyer', 'Amount', 'Payment reference', 'Status', 'Purchased', 'Action'].map((heading) => (
                                            <th
                                                key={heading}
                                                className="px-5 py-3 text-left text-[11px] font-bold tracking-wide text-slate-500 uppercase"
                                            >
                                                {heading}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {folder.purchases.map((purchase) => (
                                        <tr key={purchase.id}>
                                            <td className="px-5 py-4">
                                                <p className="text-sm font-semibold text-slate-900 dark:text-white">{purchase.buyerName || 'User'}</p>
                                                <p className="mt-1 text-xs text-slate-400">{purchase.buyerPhone || 'No phone'}</p>
                                            </td>
                                            <td className="px-5 py-4 text-sm font-semibold text-slate-800 dark:text-slate-100">
                                                {money.format(purchase.amountPaise / 100)}
                                            </td>
                                            <td className="px-5 py-4">
                                                <p className="max-w-52 truncate font-mono text-xs text-slate-600 dark:text-slate-300">
                                                    {purchase.razorpayOrderId || 'No order ID'}
                                                </p>
                                                <p className="mt-1 max-w-52 truncate font-mono text-xs text-slate-400">
                                                    {purchase.razorpayPaymentId || 'No payment ID'}
                                                </p>
                                            </td>
                                            <td className="px-5 py-4">
                                                <AdminStatusBadge tone={purchase.status === 'paid' ? 'success' : 'warning'}>
                                                    {purchase.status}
                                                </AdminStatusBadge>
                                            </td>
                                            <td className="px-5 py-4 text-sm text-slate-500">
                                                {purchase.purchasedAt ? new Date(purchase.purchasedAt).toLocaleString() : 'Not completed'}
                                            </td>
                                            <td className="px-5 py-4">
                                                {canUpdatePurchases ? (
                                                    <Button variant="outline" size="sm" asChild className="rounded-lg">
                                                        <Link href={route('admin.purchases.edit', purchase.id)}>Review</Link>
                                                    </Button>
                                                ) : null}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="p-10 text-center text-sm text-slate-500">No folder purchases yet.</div>
                    )}
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                        <span className="flex size-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-300">
                            <Archive className="size-5" />
                        </span>
                        <div>
                            <h2 className="text-sm font-bold text-slate-900 dark:text-white">Extracted files</h2>
                            <p className="mt-1 text-xs text-slate-500">Files extracted from {folder.zipName}</p>
                        </div>
                    </div>

                    {folder.files.length ? (
                        <div className="grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-4">
                            {folder.files.map((file) => (
                                <FileCard key={file.id} file={file} />
                            ))}
                        </div>
                    ) : (
                        <div className="p-10 text-center text-sm text-slate-500">No extracted files are available.</div>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}

function FileCard({ file }: { file: ExtractedFile }) {
    const FileIcon = iconForFile(file.extension);

    return (
        <article className="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-slate-50/60 transition-colors hover:border-indigo-200 hover:bg-indigo-50/40 dark:border-slate-700 dark:bg-slate-950/40 dark:hover:border-indigo-800">
            {file.previewUrl ? (
                <div className="flex h-44 items-center justify-center overflow-hidden border-b border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                    <img src={file.previewUrl} alt={file.name} loading="lazy" className="h-full w-full object-contain" />
                </div>
            ) : (
                <div className="flex h-32 items-center justify-center border-b border-slate-200 bg-gradient-to-br from-white to-slate-100 dark:border-slate-700 dark:from-slate-900 dark:to-slate-950">
                    <span className="flex size-16 items-center justify-center rounded-2xl bg-white text-indigo-600 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:text-indigo-300 dark:ring-slate-700">
                        <FileIcon className="size-8" />
                    </span>
                </div>
            )}
            <div className="p-4">
                <p className="truncate text-sm font-bold text-slate-900 dark:text-white" title={file.name}>
                    {file.name}
                </p>
                <p className="mt-1 truncate text-xs text-slate-400" title={file.relativePath}>
                    {file.relativePath}
                </p>
                <div className="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-3 text-xs dark:border-slate-700">
                    <span
                        className="min-w-0 truncate rounded-md bg-slate-200/70 px-2 py-1 font-mono text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                        title={file.mimeType}
                    >
                        {file.extension ? `.${file.extension}` : 'FILE'}
                    </span>
                    <span className="shrink-0 font-semibold text-slate-700 dark:text-slate-200">{formatBytes(file.size)}</span>
                </div>
            </div>
        </article>
    );
}
