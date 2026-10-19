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

type Option = { id: string; name: string };
type MachineTypeForm = { id: string; machineId: string | null; name: string; categoryIds: string[]; isActive: boolean; sortOrder: number };
type Props = { machineType: MachineTypeForm | null; machines: Option[]; categories: Option[] };

export default function AdminMachineTypeForm({ machineType, machines, categories }: Props) {
    const editing = Boolean(machineType?.id);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Overview', href: '/dashboard' },
        { title: 'Machine Types', href: '/admin/machine-types' },
        { title: editing ? 'Edit' : 'Add', href: '#' },
    ];
    const { data, setData, post, put, processing, errors } = useForm({
        machineId: machineType?.machineId ?? machines[0]?.id ?? '',
        name: machineType?.name ?? '',
        categoryIds: machineType?.categoryIds ?? [],
        isActive: machineType?.isActive ?? true,
        sortOrder: machineType?.sortOrder ?? 0,
    });
    const toggleCategory = (id: string, checked: boolean) =>
        setData('categoryIds', checked ? [...data.categoryIds, id] : data.categoryIds.filter((value) => value !== id));
    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        if (editing && machineType?.id) put(route('admin.machine-types.update', machineType.id));
        else post(route('admin.machine-types.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={editing ? 'Edit machine type' : 'Add machine type'} />
            <div className="mx-auto max-w-2xl space-y-6">
                <AdminPageHeader
                    title={editing ? 'Edit machine type' : 'Add machine type'}
                    description="Choose its parent machine and compatible design categories."
                />
                <form
                    onSubmit={submit}
                    className="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <div className="grid gap-2">
                        <Label>Machine</Label>
                        <Select value={data.machineId} onValueChange={(value) => setData('machineId', value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select machine" />
                            </SelectTrigger>
                            <SelectContent>
                                {machines.map((machine) => (
                                    <SelectItem key={machine.id} value={machine.id}>
                                        {machine.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.machineId} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Machine type name</Label>
                        <Input id="name" value={data.name} onChange={(event) => setData('name', event.target.value)} required />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-3">
                        <Label>Compatible categories</Label>
                        <div className="grid gap-3 rounded-lg border border-slate-200 p-4 sm:grid-cols-2 dark:border-slate-800">
                            {categories.map((category) => (
                                <label key={category.id} className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={data.categoryIds.includes(category.id)}
                                        onCheckedChange={(value) => toggleCategory(category.id, value === true)}
                                    />
                                    {category.name}
                                </label>
                            ))}
                            {!categories.length && <p className="text-sm text-slate-500">Create categories first.</p>}
                        </div>
                        <InputError message={errors.categoryIds} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="sortOrder">Sort order</Label>
                        <Input
                            id="sortOrder"
                            type="number"
                            min={0}
                            value={data.sortOrder}
                            onChange={(event) => setData('sortOrder', Number(event.target.value))}
                        />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={data.isActive} onCheckedChange={(value) => setData('isActive', value === true)} />
                        Active (visible in catalog)
                    </label>
                    <Button type="submit" disabled={processing || !machines.length} className="bg-indigo-600 hover:bg-indigo-700">
                        {editing ? 'Save' : 'Create'}
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
