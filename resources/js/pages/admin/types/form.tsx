import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Cat = { id: string; name: string };
type TypeForm = { id: string; categoryId: string | null; name: string; description: string; isActive: boolean; sortOrder: number };

type Props = { type: TypeForm | null; categories: Cat[] };

export default function AdminTypeForm({ type, categories }: Props) {
    const editing = Boolean(type?.id);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Overview', href: '/dashboard' },
        { title: 'Types', href: '/admin/types' },
        { title: editing ? 'Edit' : 'Add', href: '#' },
    ];

    const { data, setData, post, put, processing, errors } = useForm({
        categoryId: type?.categoryId ?? categories[0]?.id ?? '',
        name: type?.name ?? '',
        description: type?.description ?? '',
        isActive: type?.isActive ?? true,
        sortOrder: type?.sortOrder ?? 0,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (editing && type?.id) put(route('admin.types.update', type.id));
        else post(route('admin.types.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={editing ? 'Edit type' : 'Add type'} />
            <div className="mx-auto max-w-lg space-y-6">
                <AdminPageHeader title={editing ? 'Edit type' : 'Add type'} description="Every design links to exactly one type." />
                <form onSubmit={submit} className="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                    <div className="grid gap-2">
                        <Label>Category</Label>
                        <Select value={data.categoryId} onValueChange={(v) => setData('categoryId', v)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select category" />
                            </SelectTrigger>
                            <SelectContent>
                                {categories.map((c) => (
                                    <SelectItem key={c.id} value={c.id}>
                                        {c.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.categoryId} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Type name</Label>
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
                        Active
                    </label>
                    <Button type="submit" disabled={processing || !categories.length} className="bg-indigo-600 hover:bg-indigo-700">
                        {editing ? 'Save' : 'Create'}
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
