import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type CategoryForm = { id: string; name: string; description: string; isActive: boolean; sortOrder: number };

type Props = { category: CategoryForm | null };

export default function AdminCategoryForm({ category }: Props) {
    const editing = Boolean(category?.id);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Overview', href: '/dashboard' },
        { title: 'Categories', href: '/admin/categories' },
        { title: editing ? 'Edit' : 'Add', href: '#' },
    ];

    const { data, setData, post, put, processing, errors } = useForm({
        name: category?.name ?? '',
        description: category?.description ?? '',
        isActive: category?.isActive ?? true,
        sortOrder: category?.sortOrder ?? 0,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (editing && category?.id) put(route('admin.categories.update', category.id));
        else post(route('admin.categories.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={editing ? 'Edit category' : 'Add category'} />
            <div className="mx-auto max-w-lg space-y-6">
                <AdminPageHeader title={editing ? 'Edit category' : 'Add category'} />
                <form onSubmit={submit} className="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="description">Description</Label>
                        <Input id="description" value={data.description} onChange={(e) => setData('description', e.target.value)} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="sortOrder">Sort order</Label>
                        <Input id="sortOrder" type="number" min={0} value={data.sortOrder} onChange={(e) => setData('sortOrder', Number(e.target.value))} />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={data.isActive} onCheckedChange={(v) => setData('isActive', v === true)} />
                        Active (visible in catalog)
                    </label>
                    <Button type="submit" disabled={processing} className="bg-indigo-600 hover:bg-indigo-700">
                        {editing ? 'Save' : 'Create'}
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
