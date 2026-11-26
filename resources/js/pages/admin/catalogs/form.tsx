import { AdminFormPanel } from '@/components/admin/admin-form-panel';
import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { MarketplacePricingPreview } from '@/components/marketplace-pricing-preview';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Archive, ArrowLeft, File as FileIcon, LoaderCircle, Save, UserRound } from 'lucide-react';
import { type FormEventHandler } from 'react';

type ExtractedFile = {
    name: string;
    relativePath: string;
    extension: string | null;
    mimeType: string;
    size: number;
};

type CatalogForm = {
    id: string;
    ownerId: string | null;
    name: string;
    priceRupees: string;
    zipName: string;
    fileCount: number;
    totalSize: number;
    files: ExtractedFile[];
};

type OwnerOption = { id: string; label: string };
type Props = { catalog: CatalogForm | null; owners: OwnerOption[]; canChooseSeller: boolean };

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

export default function AdminCatalogForm({ catalog, owners, canChooseSeller }: Props) {
    const editing = Boolean(catalog?.id);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Overview', href: '/dashboard' },
        { title: 'Catalogs', href: '/admin/catalogs' },
        { title: editing ? 'Edit catalog' : 'Add catalog', href: '#' },
    ];
    const { data, setData, post, processing, errors, progress } = useForm({
        _method: editing ? 'put' : 'post',
        ownerId: catalog?.ownerId ?? owners[0]?.id ?? '',
        name: catalog?.name ?? '',
        priceRupees: catalog?.priceRupees ?? '',
        zipFile: null as File | null,
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(editing && catalog?.id ? route('admin.catalogs.update', catalog.id) : route('admin.catalogs.store'), {
            forceFormData: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={editing ? 'Edit catalog' : 'Add catalog'} />
            <div className="mx-auto max-w-4xl space-y-6 p-4 md:p-6">
                <AdminPageHeader
                    title={editing ? 'Edit catalog' : 'Add catalog'}
                    description="Set the package name and price, then upload a ZIP or RAR archive to extract its files."
                    actions={
                        <Button variant="outline" asChild className="rounded-xl">
                            <Link href={route('admin.catalogs.index')}>
                                <ArrowLeft className="size-4" /> Back
                            </Link>
                        </Button>
                    }
                />

                <form onSubmit={submit} className="space-y-5">
                    {canChooseSeller ? (
                        <AdminFormPanel title="Seller" description="Choose the marketplace user who owns this catalog." icon={UserRound}>
                            <div className="grid gap-2">
                                <Label>Seller</Label>
                                <Select value={data.ownerId} onValueChange={(value) => setData('ownerId', value)}>
                                    <SelectTrigger className="h-11 rounded-xl border-slate-200 dark:border-slate-700">
                                        <SelectValue placeholder="Select user" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {owners.map((owner) => (
                                            <SelectItem key={owner.id} value={owner.id}>
                                                {owner.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.ownerId} />
                                {!owners.length ? <p className="text-sm text-amber-700">Create a marketplace user before adding catalogs.</p> : null}
                            </div>
                        </AdminFormPanel>
                    ) : null}

                    <AdminFormPanel title="Catalog details" description="The price is entered in rupees and stored safely in paise." icon={Archive}>
                        <div className="grid items-start gap-5 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Catalog name</Label>
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
                                <MarketplacePricingPreview priceRupees={data.priceRupees} sellerId={data.ownerId} />
                            </div>
                        </div>
                    </AdminFormPanel>

                    <AdminFormPanel
                        title="Archive file"
                        description={
                            editing
                                ? 'Leave empty to keep the current archive, or choose a ZIP/RAR file to replace all extracted files.'
                                : 'The ZIP or RAR archive will be validated and extracted into individual private files.'
                        }
                        icon={Archive}
                    >
                        {catalog ? (
                            <div className="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950/40">
                                <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">{catalog.zipName}</p>
                                <p className="mt-1 text-xs text-slate-500">
                                    {catalog.fileCount} files · {formatBytes(catalog.totalSize)}
                                </p>
                            </div>
                        ) : null}
                        <Input
                            id="zipFile"
                            type="file"
                            accept=".zip,.rar,application/zip,application/vnd.rar,application/x-rar-compressed"
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

                    {catalog?.files.length ? (
                        <AdminFormPanel title="Extracted files" description="Files currently stored for this catalog." icon={FileIcon}>
                            <div className="max-h-96 divide-y divide-slate-100 overflow-auto rounded-xl border border-slate-200 dark:divide-slate-800 dark:border-slate-700">
                                {catalog.files.map((file) => (
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
                            <Link href={route('admin.catalogs.index')}>Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={processing} className="rounded-xl bg-indigo-600 px-5 font-semibold hover:bg-indigo-700">
                            {processing ? <LoaderCircle className="size-4 animate-spin" /> : <Save className="size-4" />}
                            {processing ? 'Processing archive...' : editing ? 'Save changes' : 'Create catalog'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
