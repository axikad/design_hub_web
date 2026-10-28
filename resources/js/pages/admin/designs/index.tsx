import { AdminEmptyState } from '@/components/admin/admin-empty-state';
import { AdminFilterBar } from '@/components/admin/admin-filter-bar';
import { AdminSearchInput } from '@/components/admin/admin-search-input';
import { AdminStatusBadge } from '@/components/admin/admin-status-badge';
import { AdminTablePanel } from '@/components/admin/admin-table-panel';
import { ConfirmDelete } from '@/components/admin/confirm-delete';
import { AdminPageHeader } from '@/components/admin/page-header';
import { PaginationLinks } from '@/components/admin/pagination-links';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertCircle, ImageIcon, LoaderCircle, Pencil, RotateCcw, ScanSearch, Search, X } from 'lucide-react';
import { type ChangeEvent, type FormEventHandler, useEffect, useRef, useState } from 'react';

type Option = { id: string; name?: string; label?: string; extension?: string };
type DesignRow = {
    id: string;
    title: string;
    displayName?: string | null;
    pricePaise: number | null;
    thumbnailUri: string | null;
    ownerKey: string | null;
    ownerName?: string | null;
    categoryName?: string | null;
    typeName?: string | null;
    machineName?: string | null;
    machineTypeName?: string | null;
    designFileTypeName?: string | null;
    createdAt: string | null;
    similarityScore?: number;
};
type Props = {
    designs: { data: DesignRow[]; links: { url: string | null; label: string; active: boolean }[] };
    owners: Option[];
    machines: Option[];
    fileTypes: Option[];
    filters: { search: string; owner: string; machine: string; fileType: string };
    imageSearchActive?: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/dashboard' },
    { title: 'Designs', href: '/admin/designs' },
];

function formatInr(paise: number | null): string {
    if (paise === null) return 'Not set';
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(paise / 100);
}

export default function AdminDesignsIndex({ designs, owners, machines, fileTypes, filters, imageSearchActive = false }: Props) {
    const page = usePage<SharedData & { errors: Record<string, string> }>();
    const permissions = page.props.auth.permissions ?? [];
    const canCreate = permissions.includes('designs.create');
    const canUpdate = permissions.includes('designs.update');
    const canDelete = permissions.includes('designs.delete');
    const [search, setSearch] = useState(filters.search);
    const [owner, setOwner] = useState(filters.owner || 'all');
    const [machine, setMachine] = useState(filters.machine || 'all');
    const [fileType, setFileType] = useState(filters.fileType || 'all');
    const [imageSearching, setImageSearching] = useState(false);
    const [toastMessage, setToastMessage] = useState<string | null>(null);
    const imageInput = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (page.props.errors.image) {
            setToastMessage(page.props.errors.image);
        }
    }, [page.props.errors.image]);

    useEffect(() => {
        if (!toastMessage) return;

        const timeout = window.setTimeout(() => setToastMessage(null), 6000);

        return () => window.clearTimeout(timeout);
    }, [toastMessage]);

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        router.get(
            route('admin.designs.index'),
            {
                search: search || undefined,
                owner: owner === 'all' ? undefined : owner,
                machine: machine === 'all' ? undefined : machine,
                fileType: fileType === 'all' ? undefined : fileType,
            },
            { preserveState: true, replace: true },
        );
    };

    const reset = () => {
        setSearch('');
        setOwner('all');
        setMachine('all');
        setFileType('all');
        router.get(route('admin.designs.index'), {}, { preserveState: true, replace: true });
    };

    const searchByImage = (event: ChangeEvent<HTMLInputElement>) => {
        const image = event.target.files?.[0];

        if (!image) return;

        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        const maximumBytes = 10 * 1024 * 1024;

        if (!allowedTypes.includes(image.type)) {
            setToastMessage('Please select a JPG, PNG, or WebP image.');
            event.target.value = '';
            return;
        }

        if (image.size > maximumBytes) {
            setToastMessage('The image must not be larger than 10 MB.');
            event.target.value = '';
            return;
        }

        router.post(
            route('admin.designs.search-by-image'),
            {
                image,
                machineId: machine === 'all' ? undefined : machine,
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setImageSearching(true),
                onError: (errors) => {
                    const message = errors.image ?? Object.values(errors)[0];
                    setToastMessage(typeof message === 'string' ? message : 'Image search failed. Please try again.');
                },
                onFinish: () => setImageSearching(false),
            },
        );
        event.target.value = '';
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Designs" />
            {toastMessage ? (
                <div
                    role="alert"
                    className="fixed top-5 right-5 z-[100] flex max-w-md items-start gap-3 rounded-xl border border-red-200 bg-white px-4 py-3 text-sm text-red-700 shadow-xl dark:border-red-900 dark:bg-slate-950 dark:text-red-300"
                >
                    <AlertCircle className="mt-0.5 size-5 shrink-0" />
                    <span className="flex-1 font-medium">{toastMessage}</span>
                    <button
                        type="button"
                        className="rounded-md p-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                        aria-label="Close error"
                        onClick={() => setToastMessage(null)}
                    >
                        <X className="size-4" />
                    </button>
                </div>
            ) : null}
            <div className="space-y-6 p-4 md:p-6">
                <AdminPageHeader
                    title="Designs"
                    description="Create, review, and manage marketplace design listings."
                    createHref={canCreate ? route('admin.designs.create') : undefined}
                    createLabel="Add design"
                />

                <form onSubmit={submit}>
                    <AdminFilterBar
                        actions={
                            <>
                                <input
                                    ref={imageInput}
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    className="hidden"
                                    onChange={searchByImage}
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="rounded-xl"
                                    disabled={imageSearching}
                                    onClick={() => imageInput.current?.click()}
                                >
                                    {imageSearching ? <LoaderCircle className="size-4 animate-spin" /> : <ScanSearch className="size-4" />}
                                    {imageSearching ? 'Matching...' : 'Search by image'}
                                </Button>
                                <Button type="button" variant="ghost" className="rounded-xl" onClick={reset}>
                                    <RotateCcw className="size-4" /> Reset
                                </Button>
                                <Button type="submit" className="rounded-xl bg-indigo-600 hover:bg-indigo-700">
                                    <Search className="size-4" /> Apply
                                </Button>
                            </>
                        }
                    >
                        <AdminSearchInput
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            onClear={() => setSearch('')}
                            placeholder="Search title, ID, file, or seller..."
                        />
                        <Select value={owner} onValueChange={setOwner}>
                            <SelectTrigger className="h-10 rounded-xl border-slate-200 sm:w-48 dark:border-slate-700">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All sellers</SelectItem>
                                {owners.map((option) => (
                                    <SelectItem key={option.id} value={option.id}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={machine} onValueChange={setMachine}>
                            <SelectTrigger className="h-10 rounded-xl border-slate-200 sm:w-44 dark:border-slate-700">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All machines</SelectItem>
                                {machines.map((option) => (
                                    <SelectItem key={option.id} value={option.id}>
                                        {option.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={fileType} onValueChange={setFileType}>
                            <SelectTrigger className="h-10 rounded-xl border-slate-200 sm:w-44 dark:border-slate-700">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All file types</SelectItem>
                                {fileTypes.map((option) => (
                                    <SelectItem key={option.id} value={option.id}>
                                        {option.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </AdminFilterBar>
                </form>

                {page.props.errors.image ? (
                    <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300">
                        {page.props.errors.image}
                    </div>
                ) : null}

                {imageSearchActive ? (
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-indigo-200 bg-indigo-50 px-4 py-3 dark:border-indigo-900/60 dark:bg-indigo-950/30">
                        <div>
                            <p className="text-sm font-semibold text-indigo-900 dark:text-indigo-100">Image search results</p>
                            <p className="mt-0.5 text-xs text-indigo-700 dark:text-indigo-300">
                                Showing designs that meet the configured minimum similarity.
                            </p>
                        </div>
                        <Button type="button" variant="outline" size="sm" className="rounded-lg" onClick={reset}>
                            <RotateCcw className="size-3.5" /> Clear image search
                        </Button>
                    </div>
                ) : null}

                <AdminTablePanel footer={imageSearchActive ? undefined : <PaginationLinks paginator={designs} />}>
                    {designs.data.length === 0 ? (
                        <AdminEmptyState
                            icon={ImageIcon}
                            title="No designs found"
                            description="Change your filters or add the first marketplace design."
                        />
                    ) : (
                        <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                            <thead className="bg-slate-50/80 dark:bg-slate-950/60">
                                <tr>
                                    {['Design', 'Machine', 'Category / Type', 'File type', 'Price', 'Seller', 'Actions'].map((heading) => (
                                        <th
                                            key={heading}
                                            className="px-5 py-3.5 text-left text-[11px] font-bold tracking-wider text-slate-500 uppercase"
                                        >
                                            {heading}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {designs.data.map((design) => (
                                    <tr key={design.id} className="transition-colors hover:bg-indigo-50/40 dark:hover:bg-indigo-950/10">
                                        <td className="px-5 py-4">
                                            <div className="flex min-w-56 items-center gap-3">
                                                {design.thumbnailUri ? (
                                                    <img
                                                        src={design.thumbnailUri}
                                                        alt=""
                                                        className="size-14 rounded-xl object-cover ring-1 ring-slate-200 dark:ring-slate-700"
                                                    />
                                                ) : (
                                                    <span className="flex size-14 items-center justify-center rounded-xl bg-slate-100 text-slate-400 dark:bg-slate-800">
                                                        <ImageIcon className="size-5" />
                                                    </span>
                                                )}
                                                <div>
                                                    <p className="text-sm font-semibold text-slate-900 dark:text-white">
                                                        {design.displayName || design.title}
                                                    </p>
                                                    <p className="mt-1 text-xs text-slate-400">{design.id}</p>
                                                    {design.similarityScore !== undefined ? (
                                                        <span className="mt-2 inline-flex rounded-full bg-indigo-100 px-2 py-0.5 text-[11px] font-bold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                                                            {design.similarityScore}% match
                                                        </span>
                                                    ) : null}
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-5 py-4 text-sm text-slate-600 dark:text-slate-300">
                                            <p className="font-semibold text-slate-800 dark:text-slate-100">{design.machineName || 'Not assigned'}</p>
                                            <p className="mt-1 text-xs text-slate-400">{design.machineTypeName || 'No machine type'}</p>
                                        </td>
                                        <td className="px-5 py-4 text-sm text-slate-600 dark:text-slate-300">
                                            <p>{design.categoryName || 'Not assigned'}</p>
                                            <p className="mt-1 text-xs text-slate-400">{design.typeName || 'No type'}</p>
                                        </td>
                                        <td className="px-5 py-4">
                                            <AdminStatusBadge tone="info" dot={false}>
                                                {design.designFileTypeName || 'Not set'}
                                            </AdminStatusBadge>
                                        </td>
                                        <td className="px-5 py-4 text-sm font-semibold text-slate-800 dark:text-slate-100">
                                            {formatInr(design.pricePaise)}
                                        </td>
                                        <td className="px-5 py-4 text-sm text-slate-600 dark:text-slate-300">
                                            {design.ownerName || design.ownerKey || 'Not assigned'}
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="flex gap-2">
                                                {canUpdate ? (
                                                    <Button asChild variant="outline" size="sm" className="rounded-lg">
                                                        <Link href={route('admin.designs.edit', design.id)}>
                                                            <Pencil className="size-3.5" /> Edit
                                                        </Link>
                                                    </Button>
                                                ) : null}
                                                {canDelete ? (
                                                    <ConfirmDelete
                                                        title="Remove design?"
                                                        description="This hides the design from the marketplace while retaining purchase history."
                                                        onConfirm={() => router.delete(route('admin.designs.destroy', design.id))}
                                                    />
                                                ) : null}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </AdminTablePanel>
            </div>
        </AppLayout>
    );
}
