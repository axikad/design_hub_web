import { AdminPageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Archive, ArrowLeft, File, Files, HardDrive, IndianRupee, Pencil } from 'lucide-react';

type ExtractedFile = {
    name: string;
    relativePath: string;
    extension: string | null;
    mimeType: string;
    size: number;
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

export default function AdminFolderShow({ folder }: Props) {
    const permissions = usePage<SharedData>().props.auth.permissions ?? [];
    const canUpdate = permissions.includes('folders.update');
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Overview', href: '/dashboard' },
        { title: 'Folders', href: '/admin/folders' },
        { title: folder.name, href: '#' },
    ];
    const summaries = [
        { label: 'Price', value: money.format(Number(folder.priceRupees)), icon: IndianRupee },
        { label: 'Extracted files', value: String(folder.fileCount), icon: Files },
        { label: 'Total size', value: formatBytes(folder.totalSize), icon: HardDrive },
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

                <div className="grid gap-4 sm:grid-cols-3">
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
                        <div className="grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-3">
                            {folder.files.map((file) => (
                                <article
                                    key={file.relativePath}
                                    className="min-w-0 rounded-2xl border border-slate-200 bg-slate-50/60 p-4 transition-colors hover:border-indigo-200 hover:bg-indigo-50/40 dark:border-slate-700 dark:bg-slate-950/40 dark:hover:border-indigo-800"
                                >
                                    <div className="flex items-start gap-3">
                                        <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-white text-indigo-600 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:text-indigo-300 dark:ring-slate-700">
                                            <File className="size-5" />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-bold text-slate-900 dark:text-white" title={file.name}>
                                                {file.name}
                                            </p>
                                            <p className="mt-1 truncate text-xs text-slate-400" title={file.relativePath}>
                                                {file.relativePath}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-3 text-xs dark:border-slate-700">
                                        <span className="min-w-0 truncate text-slate-500" title={file.mimeType}>
                                            {file.extension ? `.${file.extension}` : file.mimeType}
                                        </span>
                                        <span className="shrink-0 font-semibold text-slate-700 dark:text-slate-200">{formatBytes(file.size)}</span>
                                    </div>
                                </article>
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
