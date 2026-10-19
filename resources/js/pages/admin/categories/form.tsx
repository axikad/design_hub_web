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
import { ArrowLeft, FolderTree, LoaderCircle, Save } from 'lucide-react';
import { type FormEventHandler } from 'react';

type CategoryForm = { id: string; name: string; description: string; isActive: boolean; sortOrder: number };
type Props = { category: CategoryForm | null };

export default function AdminCategoryForm({ category }: Props) {
    const editing = Boolean(category?.id);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Overview', href: '/dashboard' },
        { title: 'Categories', href: '/admin/categories' },
        { title: editing ? 'Edit category' : 'Add category', href: '#' },
    ];
    const { data, setData, post, put, processing, errors } = useForm({
        name: category?.name ?? '',
        description: category?.description ?? '',
        isActive: category?.isActive ?? true,
        sortOrder: category?.sortOrder ?? 0,
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        editing && category?.id ? put(route('admin.categories.update', category.id)) : post(route('admin.categories.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={editing ? 'Edit category' : 'Add category'} />
            <div className="mx-auto max-w-3xl space-y-6 p-4 md:p-6">
                <AdminPageHeader
                    title={editing ? 'Edit category' : 'Add category'}
                    description="Categories group related design types and define machine type compatibility."
                    actions={
                        <Button variant="outline" asChild className="rounded-xl">
                            <Link href={route('admin.categories.index')}>
                                <ArrowLeft className="size-4" /> Back
                            </Link>
                        </Button>
                    }
                />

                <form onSubmit={submit} className="space-y-5">
                    <AdminFormPanel title="Category details" description="Set its catalog name, description and display order." icon={FolderTree}>
                        <div className="grid gap-5 sm:grid-cols-[1fr_180px]">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Category name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(event) => setData('name', event.target.value)}
                                    required
                                    autoFocus
                                    placeholder="e.g. Saree"
                                    className="h-11 rounded-xl border-slate-200 focus-visible:border-indigo-500 dark:border-slate-700"
                                />
                                <InputError message={errors.name} />
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
                        <div className="mt-5 grid gap-2">
                            <Label htmlFor="description">Description</Label>
                            <Input
                                id="description"
                                value={data.description}
                                onChange={(event) => setData('description', event.target.value)}
                                placeholder="Short description for administrators"
                                className="h-11 rounded-xl border-slate-200 focus-visible:border-indigo-500 dark:border-slate-700"
                            />
                            <InputError message={errors.description} />
                        </div>
                        <label className="mt-5 flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                            <Checkbox checked={data.isActive} onCheckedChange={(value) => setData('isActive', value === true)} className="mt-0.5" />
                            <span>
                                <span className="block text-sm font-semibold text-slate-800 dark:text-slate-100">Active in catalog</span>
                                <span className="mt-1 block text-xs text-slate-500">Show this category in admin and mobile catalog selections.</span>
                            </span>
                        </label>
                    </AdminFormPanel>

                    <div className="flex justify-end gap-3">
                        <Button variant="outline" asChild className="rounded-xl">
                            <Link href={route('admin.categories.index')}>Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={processing} className="rounded-xl bg-indigo-600 px-5 font-semibold hover:bg-indigo-700">
                            {processing ? <LoaderCircle className="size-4 animate-spin" /> : <Save className="size-4" />}
                            {processing ? 'Saving...' : editing ? 'Save changes' : 'Create category'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
