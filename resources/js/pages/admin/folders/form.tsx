import { AdminFormPanel } from '@/components/admin/admin-form-panel';
import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Archive, ArrowLeft, File as FileIcon, LoaderCircle, Save } from 'lucide-react';
import { type FormEventHandler } from 'react';

type ExtractedFile = {
    name: string;
    relativePath: string;
    extension: string | null;
    mimeType: string;
    size: number;
};

type FolderForm = {
    id: string;
    name: string;
    priceRupees: string;
    zipName: string;
    fileCount: number;
    totalSize: number;
    files: ExtractedFile[];
};

type Props = { folder: FolderForm | null };

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

export default function AdminFolderForm({ folder }: Props) {
    const editing = Boolean(folder?.id);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Overview', href: '/dashboard' },
        { title: 'Folders', href: '/admin/folders' },
        { title: editing ? 'Edit folder' : 'Add folder', href: '#' },
    ];
    const { data, setData, post, processing, errors, progress } = useForm({
        _method: editing ? 'put' : 'post',
        name: folder?.name ?? '',
        priceRupees: folder?.priceRupees ?? '',
        zipFile: null as File | null,
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(editing && folder?.id ? route('admin.folders.update', folder.id) : route('admin.folders.store'), {
            forceFormData: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={editing ? 'Edit folder' : 'Add folder'} />
            <div className="mx-auto max-w-4xl space-y-6 p-4 md:p-6">
                <AdminPageHeader
                    title={editing ? 'Edit folder' : 'Add folder'}
                    description="Set the package name and price, then upload the ZIP to extract its files."
                    actions={
                        <Button variant="outline" asChild className="rounded-xl">
                            <Link href={route('admin.folders.index')}>
                                <ArrowLeft className="size-4" /> Back
                            </Link>
                        </Button>
                    }
                />

                <form onSubmit={submit} className="space-y-5">
                    <AdminFormPanel title="Folder details" description="The price is entered in rupees and stored safely in paise." icon={Archive}>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Folder name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(event) => setData('name', event.target.value)}
                                    placeholder="e.g. Bridal Embroidery Pack"
                                    required
                                    autoFocus
                                    className="h-11 rounded-xl border-slate-200 focus-visible:border-indigo-500 dark:border-slate-700"
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="priceRupees">Price (₹)</Label>
                                <Input
                                    id="priceRupees"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={data.priceRupees}
                                    onChange={(event) => setData('priceRupees', event.target.value)}
                                    placeholder="299.00"
                                    required
                                    className="h-11 rounded-xl border-slate-200 focus-visible:border-indigo-500 dark:border-slate-700"
                                />
                                <InputError message={errors.priceRupees} />
                            </div>
                        </div>
                    </AdminFormPanel>

                    <AdminFormPanel
                        title="ZIP archive"
                        description={
                            editing
                                ? 'Leave empty to keep the current archive, or choose a ZIP to replace all extracted files.'
                                : 'The ZIP will be validated and extracted into individual private files.'
                        }
                        icon={Archive}
                    >
                        {folder ? (
                            <div className="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950/40">
                                <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">{folder.zipName}</p>
                                <p className="mt-1 text-xs text-slate-500">
                                    {folder.fileCount} files · {formatBytes(folder.totalSize)}
                                </p>
                            </div>
                        ) : null}
                        <Input
                            id="zipFile"
                            type="file"
                            accept=".zip,application/zip"
                            required={!editing}
                            onChange={(event) => setData('zipFile', event.target.files?.[0] ?? null)}
                            className="h-11 rounded-xl border-slate-200 focus-visible:border-indigo-500 dark:border-slate-700"
                        />
                        {data.zipFile ? <p className="mt-2 text-xs text-slate-500">Selected: {data.zipFile.name}</p> : null}
                        {progress ? (
                            <div className="mt-3 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div className="h-full bg-indigo-600 transition-all" style={{ width: `${progress.percentage}%` }} />
                            </div>
                        ) : null}
                        <InputError message={errors.zipFile} className="mt-2" />
                        <p className="mt-3 text-xs text-slate-500">Maximum 500 extracted files and 512 MB extracted content.</p>
                    </AdminFormPanel>

                    {folder?.files.length ? (
                        <AdminFormPanel title="Extracted files" description="Files currently stored for this folder." icon={FileIcon}>
                            <div className="max-h-96 divide-y divide-slate-100 overflow-auto rounded-xl border border-slate-200 dark:divide-slate-800 dark:border-slate-700">
                                {folder.files.map((file) => (
                                    <div key={file.relativePath} className="flex items-center justify-between gap-4 px-4 py-3">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium text-slate-800 dark:text-slate-100" title={file.relativePath}>
                                                {file.relativePath}
                                            </p>
                                            <p className="mt-0.5 truncate text-xs text-slate-400">{file.mimeType}</p>
                                        </div>
                                        <span className="shrink-0 text-xs font-medium text-slate-500">{formatBytes(file.size)}</span>
                                    </div>
                                ))}
                            </div>
                        </AdminFormPanel>
                    ) : null}

                    <div className="flex justify-end gap-3">
                        <Button variant="outline" asChild className="rounded-xl">
                            <Link href={route('admin.folders.index')}>Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={processing} className="rounded-xl bg-indigo-600 px-5 font-semibold hover:bg-indigo-700">
                            {processing ? <LoaderCircle className="size-4 animate-spin" /> : <Save className="size-4" />}
                            {processing ? 'Processing ZIP...' : editing ? 'Save changes' : 'Create folder'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
