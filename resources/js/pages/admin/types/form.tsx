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
import { ArrowLeft, LoaderCircle, Save, Shapes } from 'lucide-react';
import { type FormEventHandler } from 'react';

type Category = { id: string; name: string };
type TypeForm = { id: string; categoryIds: string[]; name: string; description: string; isActive: boolean; sortOrder: number };
type Props = { type: TypeForm | null; categories: Category[] };

export default function AdminTypeForm({ type, categories }: Props) {
    const editing = Boolean(type?.id);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Overview', href: '/dashboard' },
        { title: 'Design Types', href: '/admin/types' },
        { title: editing ? 'Edit design type' : 'Add design type', href: '#' },
    ];
    const { data, setData, post, put, processing, errors } = useForm({
        categoryIds: type?.categoryIds ?? [],
        name: type?.name ?? '',
        description: type?.description ?? '',
        isActive: type?.isActive ?? true,
        sortOrder: type?.sortOrder ?? 0,
    });
    const allCategoryIds = categories.map((category) => category.id);
    const allCategoriesSelected = categories.length > 0 && allCategoryIds.every((id) => data.categoryIds.includes(id));
    const someCategoriesSelected = data.categoryIds.length > 0 && !allCategoriesSelected;

    const toggleCategory = (categoryId: string) => {
        setData(
            'categoryIds',
            data.categoryIds.includes(categoryId) ? data.categoryIds.filter((id) => id !== categoryId) : [...data.categoryIds, categoryId],
        );
    };
    const toggleAllCategories = (checked: boolean) => setData('categoryIds', checked ? allCategoryIds : []);

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        editing && type?.id ? put(route('admin.types.update', type.id)) : post(route('admin.types.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={editing ? 'Edit design type' : 'Add design type'} />
            <div className="mx-auto max-w-3xl space-y-6 p-4 md:p-6">
                <AdminPageHeader
                    title={editing ? 'Edit design type' : 'Add design type'}
                    description="Connect each design type to one or more categories."
                    actions={
                        <Button variant="outline" asChild className="rounded-xl">
                            <Link href={route('admin.types.index')}>
                                <ArrowLeft className="size-4" /> Back
                            </Link>
                        </Button>
                    }
                />

                <form onSubmit={submit} className="space-y-5">
                    <AdminFormPanel title="Design type details" description="Set its catalog name, description and display order." icon={Shapes}>
                        <div className="grid gap-5 sm:grid-cols-[1fr_180px]">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Design type name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(event) => setData('name', event.target.value)}
                                    required
                                    autoFocus
                                    placeholder="e.g. Pallu"
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
                                <span className="mt-1 block text-xs text-slate-500">Show this design type in admin and mobile selections.</span>
                            </span>
                        </label>
                    </AdminFormPanel>

                    <AdminFormPanel title="Categories" description="Select every category where this design type should be available." icon={Shapes}>
                        {categories.length > 0 && (
                            <div className="mb-4 flex items-center justify-between gap-4 rounded-xl border border-indigo-200 bg-indigo-50/60 p-4 dark:border-indigo-900 dark:bg-indigo-950/20">
                                <label className="flex cursor-pointer items-center gap-3">
                                    <Checkbox
                                        checked={allCategoriesSelected ? true : someCategoriesSelected ? 'indeterminate' : false}
                                        onCheckedChange={(value) => toggleAllCategories(value === true)}
                                    />
                                    <span>
                                        <span className="block text-sm font-semibold text-slate-800 dark:text-slate-100">Select all categories</span>
                                        <span className="mt-0.5 block text-xs text-slate-500">
                                            {data.categoryIds.length} of {categories.length} selected
                                        </span>
                                    </span>
                                </label>
                                {data.categoryIds.length > 0 && (
                                    <Button type="button" variant="ghost" size="sm" onClick={() => toggleAllCategories(false)}>
                                        Clear all
                                    </Button>
                                )}
                            </div>
                        )}
                        <div className="grid gap-3 sm:grid-cols-2">
                            {categories.map((category) => (
                                <label
                                    key={category.id}
                                    className="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                                >
                                    <Checkbox checked={data.categoryIds.includes(category.id)} onCheckedChange={() => toggleCategory(category.id)} />
                                    <span className="text-sm font-semibold text-slate-800 dark:text-slate-100">{category.name}</span>
                                </label>
                            ))}
                            {!categories.length ? <p className="text-sm text-amber-700">Create an active category first.</p> : null}
                        </div>
                        <InputError message={errors.categoryIds} className="mt-3" />
                    </AdminFormPanel>

                    <div className="flex justify-end gap-3">
                        <Button variant="outline" asChild className="rounded-xl">
                            <Link href={route('admin.types.index')}>Cancel</Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || !categories.length || !data.categoryIds.length}
                            className="rounded-xl bg-indigo-600 px-5 font-semibold hover:bg-indigo-700"
                        >
                            {processing ? <LoaderCircle className="size-4 animate-spin" /> : <Save className="size-4" />}
                            {processing ? 'Saving...' : editing ? 'Save changes' : 'Create design type'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
