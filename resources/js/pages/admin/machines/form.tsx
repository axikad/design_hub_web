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

type MachineForm = { id: string; name: string; isActive: boolean; sortOrder: number };
type Props = { machine: MachineForm | null };

export default function AdminMachineForm({ machine }: Props) {
    const editing = Boolean(machine?.id);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Overview', href: '/dashboard' },
        { title: 'Machines', href: '/admin/machines' },
        { title: editing ? 'Edit' : 'Add', href: '#' },
    ];
    const { data, setData, post, put, processing, errors } = useForm({
        name: machine?.name ?? '',
        isActive: machine?.isActive ?? true,
        sortOrder: machine?.sortOrder ?? 0,
    });
    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        if (editing && machine?.id) put(route('admin.machines.update', machine.id));
        else post(route('admin.machines.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={editing ? 'Edit machine' : 'Add machine'} />
            <div className="mx-auto max-w-lg space-y-6">
                <AdminPageHeader title={editing ? 'Edit machine' : 'Add machine'} />
                <form
                    onSubmit={submit}
                    className="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="name">Machine name</Label>
                        <Input id="name" value={data.name} onChange={(event) => setData('name', event.target.value)} required />
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
                        />
                        <InputError message={errors.sortOrder} />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={data.isActive} onCheckedChange={(value) => setData('isActive', value === true)} />
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
