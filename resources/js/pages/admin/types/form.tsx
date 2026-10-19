import { AdminFormPanel } from '@/components/admin/admin-form-panel';
import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, LoaderCircle, Save, Shapes } from 'lucide-react';
import { type FormEventHandler } from 'react';

type Category = { id: string; name: string };
type TypeForm = { id: string; categoryId: string | null; name: string; description: string; isActive: boolean; sortOrder: number };
type Props = { type: TypeForm | null; categories: Category[] };

export default function AdminTypeForm({ type, categories }: Props) {
    const editing = Boolean(type?.id);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Overview', href: '/dashboard' },
        { title: 'Design Types', href: '/admin/types' },
        { title: editing ? 'Edit design type' : 'Add design type', href: '#' },
    ];
    const { data, setData, post, put, processing, errors } = useForm({
        categoryId: type?.categoryId ?? categories[0]?.id ?? '',
        name: type?.name ?? '',
        description: type?.description ?? '',
        isActive: type?.isActive ?? true,
        sortOrder: type?.sortOrder ?? 0,
    });

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
                    description="Each design type belongs to one category and is selectable when creating a design."
                    actions={
                        <Button variant="outline" asChild className="rounded-xl">
                            <Link href={route('admin.types.index')}>
                                <ArrowLeft className="size-4" /> Back
                            </Link>
                        </Button>
                    }
                />

                <form onSubmit={submit} className="space-y-5">
                    <AdminFormPanel title="Design type details" description="Set its parent category, catalog name and display order." icon={Shapes}>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label>Category</Label>
                                <Select value={data.categoryId} onValueChange={(value) => setData('categoryId', value)}>
                                    <SelectTrigger className="h-11 rounded-xl border-slate-200 dark:border-slate-700">
                                        <SelectValue placeholder="Select category" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categories.map((category) => (
                                            <SelectItem key={category.id} value={category.id}>
                                                {category.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.categoryId} />
                            </div>
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
                        </div>
                        <div className="mt-5 grid gap-5 sm:grid-cols-[1fr_180px]">
                            <div className="grid gap-2">
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
                        <label className="mt-5 flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                            <Checkbox checked={data.isActive} onCheckedChange={(value) => setData('isActive', value === true)} className="mt-0.5" />
                            <span>
                                <span className="block text-sm font-semibold text-slate-800 dark:text-slate-100">Active in catalog</span>
                                <span className="mt-1 block text-xs text-slate-500">Show this design type in admin and mobile selections.</span>
                            </span>
                        </label>
                    </AdminFormPanel>

                    <div className="flex justify-end gap-3">
                        <Button variant="outline" asChild className="rounded-xl">
                            <Link href={route('admin.types.index')}>Cancel</Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || !categories.length}
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
