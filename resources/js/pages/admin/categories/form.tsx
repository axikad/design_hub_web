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
import { ArrowLeft, Cog, FolderTree, LoaderCircle, Save } from 'lucide-react';
import { type FormEventHandler } from 'react';

type MachineTypeOption = { id: string; name: string; machineName: string };
type CategoryForm = { id: string; name: string; description: string; isActive: boolean; sortOrder: number; machineTypeIds: string[] };
type Props = { category: CategoryForm | null; machineTypes: MachineTypeOption[]; nextSortOrder: number };

export default function AdminCategoryForm({ category, machineTypes, nextSortOrder }: Props) {
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
        sortOrder: category?.sortOrder ?? nextSortOrder,
        machineTypeIds: category?.machineTypeIds ?? [],
    });
    const allMachineTypeIds = machineTypes.map((machineType) => machineType.id);
    const allMachineTypesSelected = machineTypes.length > 0 && allMachineTypeIds.every((id) => data.machineTypeIds.includes(id));
    const someMachineTypesSelected = data.machineTypeIds.length > 0 && !allMachineTypesSelected;

    const toggleMachineType = (machineTypeId: string) => {
        setData(
            'machineTypeIds',
            data.machineTypeIds.includes(machineTypeId)
                ? data.machineTypeIds.filter((id) => id !== machineTypeId)
                : [...data.machineTypeIds, machineTypeId],
        );
    };
    const toggleAllMachineTypes = (checked: boolean) => setData('machineTypeIds', checked ? allMachineTypeIds : []);

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        editing && category?.id ? put(route('admin.categories.update', category.id)) : post(route('admin.categories.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={editing ? 'Edit category' : 'Add category'} />
            <div className="mx-auto max-w-4xl space-y-6 p-4 md:p-6">
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
                                <Label htmlFor="sortOrder">Position</Label>
                                <Input
                                    id="sortOrder"
                                    type="number"
                                    min={1}
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

                    <AdminFormPanel
                        title="Compatible machine types"
                        description="Select every machine type where this category should be available."
                        icon={Cog}
                    >
                        {machineTypes.length > 0 && (
                            <div className="mb-4 flex items-center justify-between gap-4 rounded-xl border border-indigo-200 bg-indigo-50/60 p-4 dark:border-indigo-900 dark:bg-indigo-950/20">
                                <label className="flex cursor-pointer items-center gap-3">
                                    <Checkbox
                                        checked={allMachineTypesSelected ? true : someMachineTypesSelected ? 'indeterminate' : false}
                                        onCheckedChange={(value) => toggleAllMachineTypes(value === true)}
                                    />
                                    <span>
                                        <span className="block text-sm font-semibold text-slate-800 dark:text-slate-100">
                                            Select all machine types
                                        </span>
                                        <span className="mt-0.5 block text-xs text-slate-500">
                                            {data.machineTypeIds.length} of {machineTypes.length} selected
                                        </span>
                                    </span>
                                </label>
                                {data.machineTypeIds.length > 0 && (
                                    <Button type="button" variant="ghost" size="sm" onClick={() => toggleAllMachineTypes(false)}>
                                        Clear all
                                    </Button>
                                )}
                            </div>
                        )}
                        <div className="grid gap-3 sm:grid-cols-2">
                            {machineTypes.map((machineType) => (
                                <label
                                    key={machineType.id}
                                    className="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                                >
                                    <Checkbox
                                        checked={data.machineTypeIds.includes(machineType.id)}
                                        onCheckedChange={() => toggleMachineType(machineType.id)}
                                    />
                                    <span>
                                        <span className="block text-sm font-semibold text-slate-800 dark:text-slate-100">{machineType.name}</span>
                                        <span className="mt-0.5 block text-xs text-slate-400">{machineType.machineName}</span>
                                    </span>
                                </label>
                            ))}
                            {!machineTypes.length ? <p className="text-sm text-amber-700">Create an active machine type first.</p> : null}
                        </div>
                        <InputError message={errors.machineTypeIds} className="mt-3" />
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
