import { AdminFormPanel } from '@/components/admin/admin-form-panel';
import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, FileCode2, LoaderCircle, Save } from 'lucide-react';
import { type FormEventHandler } from 'react';

type FileTypeForm = { id: string; name: string; extension: string; isActive: boolean; sortOrder: number };
type Props = { fileType: FileTypeForm | null };

export default function AdminDesignFileTypeForm({ fileType }: Props) {
    const editing = Boolean(fileType?.id);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Overview', href: '/dashboard' },
        { title: 'Design File Types', href: '/admin/design-file-types' },
        { title: editing ? 'Edit file type' : 'Add file type', href: '#' },
    ];
    const { data, setData, post, put, processing, errors } = useForm({
        name: fileType?.name ?? '',
        extension: fileType?.extension ?? '',
        isActive: fileType?.isActive ?? true,
        sortOrder: fileType?.sortOrder ?? 0,
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        editing && fileType?.id ? put(route('admin.design-file-types.update', fileType.id)) : post(route('admin.design-file-types.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={editing ? 'Edit design file type' : 'Add design file type'} />
            <div className="mx-auto max-w-3xl space-y-6 p-4 md:p-6">
                <AdminPageHeader
                    title={editing ? 'Edit design file type' : 'Add design file type'}
                    description="Configure the stitch format name, extension, availability, and display order."
                    actions={
                        <Button variant="outline" asChild className="rounded-xl">
                            <Link href={route('admin.design-file-types.index')}>
                                <ArrowLeft className="size-4" /> Back
                            </Link>
                        </Button>
                    }
                />

                <form onSubmit={submit} className="space-y-5">
                    <AdminFormPanel
                        title="File type details"
                        description="Extensions are stored without a dot and normalized to lowercase."
                        icon={FileCode2}
                    >
                        <div className="grid gap-5 sm:grid-cols-[1fr_1fr_180px]">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Display name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(event) => setData('name', event.target.value)}
                                    placeholder="e.g. DST"
                                    required
                                    autoFocus
                                    className="h-11 rounded-xl border-slate-200 focus-visible:border-indigo-500 dark:border-slate-700"
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="extension">Extension</Label>
                                <div className="relative">
                                    <span className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 font-mono text-slate-400">.</span>
                                    <Input
                                        id="extension"
                                        value={data.extension.replace(/^\./, '')}
                                        onChange={(event) => setData('extension', event.target.value.replace(/^\./, ''))}
                                        placeholder="dst"
                                        required
                                        className="h-11 rounded-xl border-slate-200 pl-7 font-mono focus-visible:border-indigo-500 dark:border-slate-700"
                                    />
                                </div>
                                <InputError message={errors.extension} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="sortOrder">Sort order</Label>
                                <Input
                                    id="sortOrder"
                                    type="number"
                                    min={0}
                                    value={data.sortOrder}
                                    onChange={(event) => setData('sortOrder', Number(event.target.value))}
                                    className="h-11 rounded-xl border-slate-200 focus-visible:border-indigo-500 dark:border-slate-700"
                                />
                                <InputError message={errors.sortOrder} />
                            </div>
                        </div>

                        <div className="mt-5 rounded-xl border border-dashed border-indigo-200 bg-indigo-50/50 p-4 dark:border-indigo-900 dark:bg-indigo-950/20">
                            <p className="text-xs font-semibold tracking-wide text-indigo-500 uppercase">Display preview</p>
                            <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
                                A design titled <strong>Floral Border</strong> will display as{' '}
                                <strong className="font-mono text-slate-900 dark:text-white">
                                    Floral Border.{data.extension.replace(/^\./, '').toLowerCase() || 'ext'}
                                </strong>
                            </p>
                        </div>

                        <label className="mt-5 flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                            <Checkbox checked={data.isActive} onCheckedChange={(value) => setData('isActive', value === true)} className="mt-0.5" />
                            <span>
                                <span className="block text-sm font-semibold text-slate-800 dark:text-slate-100">Active in catalog</span>
                                <span className="mt-1 block text-xs text-slate-500">
                                    Allow users to select this file type when creating a design.
                                </span>
                            </span>
                        </label>
                        <InputError message={errors.isActive} className="mt-2" />
                    </AdminFormPanel>

                    <div className="flex justify-end gap-3">
                        <Button variant="outline" asChild className="rounded-xl">
                            <Link href={route('admin.design-file-types.index')}>Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={processing} className="rounded-xl bg-indigo-600 px-5 font-semibold hover:bg-indigo-700">
                            {processing ? <LoaderCircle className="size-4 animate-spin" /> : <Save className="size-4" />}
                            {processing ? 'Saving...' : editing ? 'Save changes' : 'Create file type'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
