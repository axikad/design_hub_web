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
import { ArrowLeft, Cog, Layers3, LoaderCircle, Save } from 'lucide-react';
import { type FormEventHandler } from 'react';

type Option = { id: string; name: string };
type MachineTypeForm = { id: string; machineId: string | null; name: string; categoryIds: string[]; isActive: boolean; sortOrder: number };
type Props = { machineType: MachineTypeForm | null; machines: Option[]; categories: Option[] };

export default function AdminMachineTypeForm({ machineType, machines, categories }: Props) {
    const editing = Boolean(machineType?.id);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Overview', href: '/dashboard' },
        { title: 'Machine Types', href: '/admin/machine-types' },
        { title: editing ? 'Edit machine type' : 'Add machine type', href: '#' },
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
        editing && machineType?.id ? put(route('admin.machine-types.update', machineType.id)) : post(route('admin.machine-types.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={editing ? 'Edit machine type' : 'Add machine type'} />
            <div className="mx-auto max-w-3xl space-y-6 p-4 md:p-6">
                <AdminPageHeader
                    title={editing ? 'Edit machine type' : 'Add machine type'}
                    description="Choose its parent machine and compatible design categories."
                    actions={
                        <Button variant="outline" asChild className="rounded-xl">
                            <Link href={route('admin.machine-types.index')}>
                                <ArrowLeft className="size-4" /> Back
                            </Link>
                        </Button>
                    }
                />

                <form onSubmit={submit} className="space-y-5">
                    <AdminFormPanel title="Machine type details" description="Set the parent machine, catalog name and display order." icon={Cog}>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label>Parent machine</Label>
                                <Select value={data.machineId} onValueChange={(value) => setData('machineId', value)}>
                                    <SelectTrigger className="h-11 rounded-xl border-slate-200 dark:border-slate-700">
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
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(event) => setData('name', event.target.value)}
                                    required
                                    autoFocus
                                    placeholder="e.g. Multi Head"
                                    className="h-11 rounded-xl border-slate-200 focus-visible:border-indigo-500 dark:border-slate-700"
                                />
                                <InputError message={errors.name} />
                            </div>
                        </div>
                        <div className="mt-5 grid gap-5 sm:grid-cols-[180px_1fr]">
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
                            <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                                <Checkbox
                                    checked={data.isActive}
                                    onCheckedChange={(value) => setData('isActive', value === true)}
                                    className="mt-0.5"
                                />
                                <span>
                                    <span className="block text-sm font-semibold text-slate-800 dark:text-slate-100">Active in catalog</span>
                                    <span className="mt-1 block text-xs text-slate-500">Show this type in admin and mobile selections.</span>
                                </span>
                            </label>
                        </div>
                    </AdminFormPanel>

                    <AdminFormPanel
                        title="Compatible categories"
                        description="Only selected categories will be available for this machine and machine type combination."
                        icon={Layers3}
                    >
                        <div className="grid gap-3 sm:grid-cols-2">
                            {categories.map((category) => (
                                <label
                                    key={category.id}
                                    className="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-4 text-sm font-medium transition-colors hover:border-indigo-300 hover:bg-indigo-50/40 dark:border-slate-700 dark:hover:border-indigo-800 dark:hover:bg-indigo-950/20"
                                >
                                    <Checkbox
                                        checked={data.categoryIds.includes(category.id)}
                                        onCheckedChange={(value) => toggleCategory(category.id, value === true)}
                                    />
                                    {category.name}
                                </label>
                            ))}
                            {!categories.length && <p className="text-sm text-slate-500">Create categories first.</p>}
                        </div>
                        <InputError message={errors.categoryIds} className="mt-3" />
                    </AdminFormPanel>

                    <div className="flex justify-end gap-3">
                        <Button variant="outline" asChild className="rounded-xl">
                            <Link href={route('admin.machine-types.index')}>Cancel</Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || !machines.length}
                            className="rounded-xl bg-indigo-600 px-5 font-semibold hover:bg-indigo-700"
                        >
                            {processing ? <LoaderCircle className="size-4 animate-spin" /> : <Save className="size-4" />}
                            {processing ? 'Saving...' : editing ? 'Save changes' : 'Create machine type'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
