import { AdminFormPanel } from '@/components/admin/admin-form-panel';
import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, FileArchive, ImageIcon, Layers3, LoaderCircle, Save, Tag, UserRound } from 'lucide-react';
import { type FormEventHandler } from 'react';

type OwnerOption = { id: string; label: string };
type TypeOption = { id: string; name: string };
type CategoryOption = { id: string; name: string; types: TypeOption[] };
type MachineTypeOption = { id: string; name: string; categories: CategoryOption[] };
type MachineOption = { id: string; name: string; machineTypes: MachineTypeOption[] };
type FileTypeOption = { id: string; name: string; extension: string };
type DesignForm = {
    id?: string;
    ownerId?: string;
    machineId?: string | null;
    machineTypeId?: string | null;
    categoryId?: string | null;
    typeId?: string | null;
    designFileTypeId?: string | null;
    title: string;
    priceRupees: string;
    size: string;
    color: string;
    fileName?: string | null;
    imageUri?: string | null;
};
type Props = {
    design: DesignForm | null;
    owners: OwnerOption[];
    catalog: MachineOption[];
    fileTypes: FileTypeOption[];
    sizeOptions: string[];
    colorOptions: string[];
};

const selectClass = 'h-11 rounded-xl border-slate-200 dark:border-slate-700';
const inputClass = 'h-11 rounded-xl border-slate-200 focus-visible:border-indigo-500 dark:border-slate-700';

export default function AdminDesignForm({ design, owners, catalog, fileTypes, sizeOptions, colorOptions }: Props) {
    const initialMachine =
        catalog.find((machine) => machine.id === design?.machineId || machine.machineTypes.some((type) => type.id === design?.machineTypeId)) ??
        catalog[0];
    const initialMachineType = initialMachine?.machineTypes.find((type) => type.id === design?.machineTypeId) ?? initialMachine?.machineTypes[0];
    const initialCategory =
        initialMachineType?.categories.find((category) => category.id === design?.categoryId) ?? initialMachineType?.categories[0];
    const initialType = initialCategory?.types.find((type) => type.id === design?.typeId) ?? initialCategory?.types[0];
    const editing = Boolean(design?.id);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Overview', href: '/dashboard' },
        { title: 'Designs', href: '/admin/designs' },
        { title: editing ? 'Edit design' : 'Add design', href: '#' },
    ];
    const form = useForm({
        ownerId: design?.ownerId ?? owners[0]?.id ?? '',
        machineId: initialMachine?.id ?? '',
        machineTypeId: initialMachineType?.id ?? '',
        categoryId: initialCategory?.id ?? '',
        typeId: initialType?.id ?? '',
        designFileTypeId: design?.designFileTypeId ?? fileTypes[0]?.id ?? '',
        title: design?.title ?? '',
        priceRupees: design?.priceRupees ?? '',
        size: design?.size ?? sizeOptions[1] ?? '',
        color: design?.color ?? colorOptions[0] ?? '',
        previewImage: null as File | null,
        stitchFile: null as File | null,
    });
    const { data, setData, post, processing, errors } = form;
    const machineTypes = catalog.find((machine) => machine.id === data.machineId)?.machineTypes ?? [];
    const categories = machineTypes.find((type) => type.id === data.machineTypeId)?.categories ?? [];
    const types = categories.find((category) => category.id === data.categoryId)?.types ?? [];

    const selectMachine = (machineId: string) => {
        const machineType = catalog.find((machine) => machine.id === machineId)?.machineTypes[0];
        const category = machineType?.categories[0];
        setData({ ...data, machineId, machineTypeId: machineType?.id ?? '', categoryId: category?.id ?? '', typeId: category?.types[0]?.id ?? '' });
    };
    const selectMachineType = (machineTypeId: string) => {
        const category = machineTypes.find((type) => type.id === machineTypeId)?.categories[0];
        setData({ ...data, machineTypeId, categoryId: category?.id ?? '', typeId: category?.types[0]?.id ?? '' });
    };
    const selectCategory = (categoryId: string) => {
        const type = categories.find((category) => category.id === categoryId)?.types[0];
        setData({ ...data, categoryId, typeId: type?.id ?? '' });
    };
    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        if (editing && design?.id) {
            form.transform((payload) => ({ ...payload, _method: 'put' }));
            post(route('admin.designs.update', design.id), { forceFormData: true });
        } else {
            post(route('admin.designs.store'), { forceFormData: true });
        }
    };
    const cannotSubmit =
        processing || !owners.length || !catalog.length || !machineTypes.length || !categories.length || !types.length || !fileTypes.length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={editing ? 'Edit design' : 'Add design'} />
            <div className="mx-auto max-w-5xl space-y-6 p-4 md:p-6">
                <AdminPageHeader
                    title={editing ? 'Edit design' : 'Add design'}
                    description="Configure the seller, catalog classification, pricing, preview, and stitch file."
                    actions={
                        <Button variant="outline" asChild className="rounded-xl">
                            <Link href={route('admin.designs.index')}>
                                <ArrowLeft className="size-4" /> Back
                            </Link>
                        </Button>
                    }
                />
                <form onSubmit={submit} className="space-y-5">
                    <AdminFormPanel title="Seller" description="Choose the marketplace user who owns this design." icon={UserRound}>
                        <div className="grid gap-2">
                            <Label>Seller</Label>
                            <Select value={data.ownerId} onValueChange={(value) => setData('ownerId', value)}>
                                <SelectTrigger className={selectClass}>
                                    <SelectValue placeholder="Select user" />
                                </SelectTrigger>
                                <SelectContent>
                                    {owners.map((owner) => (
                                        <SelectItem key={owner.id} value={owner.id}>
                                            {owner.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.ownerId} />
                            {!owners.length ? <p className="text-sm text-amber-700">Create a marketplace user before adding designs.</p> : null}
                        </div>
                    </AdminFormPanel>

                    <AdminFormPanel
                        title="Catalog classification"
                        description="Selections are linked: Machine → Machine Type → Category → Design Type."
                        icon={Layers3}
                    >
                        <div className="grid gap-5 sm:grid-cols-2">
                            <SelectField label="Machine" value={data.machineId} onChange={selectMachine} options={catalog} error={errors.machineId} />
                            <SelectField
                                label="Machine type"
                                value={data.machineTypeId}
                                onChange={selectMachineType}
                                options={machineTypes}
                                error={errors.machineTypeId}
                                disabled={!machineTypes.length}
                            />
                            <SelectField
                                label="Category"
                                value={data.categoryId}
                                onChange={selectCategory}
                                options={categories}
                                error={errors.categoryId}
                                disabled={!categories.length}
                            />
                            <SelectField
                                label="Design type"
                                value={data.typeId}
                                onChange={(value) => setData('typeId', value)}
                                options={types}
                                error={errors.typeId}
                                disabled={!types.length}
                            />
                        </div>
                        {!catalog.length ? <p className="mt-4 text-sm text-amber-700">Add active catalog options before creating designs.</p> : null}
                    </AdminFormPanel>

                    <AdminFormPanel title="Design details" description="Set the customer-facing name, format, price, size, and color." icon={Tag}>
                        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="title">Title</Label>
                                <Input
                                    id="title"
                                    value={data.title}
                                    onChange={(event) => setData('title', event.target.value)}
                                    maxLength={40}
                                    required
                                    className={inputClass}
                                />
                                <InputError message={errors.title} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="priceRupees">Price (INR)</Label>
                                <Input
                                    id="priceRupees"
                                    type="number"
                                    step="0.01"
                                    min="1"
                                    value={data.priceRupees}
                                    onChange={(event) => setData('priceRupees', event.target.value)}
                                    required
                                    className={inputClass}
                                />
                                <InputError message={errors.priceRupees} />
                            </div>
                            <SelectField
                                label="File type"
                                value={data.designFileTypeId}
                                onChange={(value) => setData('designFileTypeId', value)}
                                options={fileTypes.map((item) => ({ ...item, name: `${item.name} (.${item.extension})` }))}
                                error={errors.designFileTypeId}
                            />
                            <SelectField
                                label="Size"
                                value={data.size}
                                onChange={(value) => setData('size', value)}
                                options={sizeOptions.map((value) => ({ id: value, name: value }))}
                                error={errors.size}
                            />
                            <SelectField
                                label="Color"
                                value={data.color}
                                onChange={(value) => setData('color', value)}
                                options={colorOptions.map((value) => ({ id: value, name: value }))}
                                error={errors.color}
                            />
                        </div>
                    </AdminFormPanel>

                    <div className="grid gap-5 lg:grid-cols-2">
                        <AdminFormPanel
                            title="Preview image"
                            description={editing ? 'Leave empty to keep the current image.' : 'Upload the marketplace preview image.'}
                            icon={ImageIcon}
                        >
                            {design?.imageUri ? (
                                <img
                                    src={design.imageUri}
                                    alt="Current design preview"
                                    className="mb-4 h-36 w-36 rounded-2xl object-cover ring-1 ring-slate-200 dark:ring-slate-700"
                                />
                            ) : null}
                            <Input
                                id="previewImage"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                onChange={(event) => setData('previewImage', event.target.files?.[0] ?? null)}
                                className={inputClass}
                            />
                            <InputError message={errors.previewImage} className="mt-2" />
                        </AdminFormPanel>
                        <AdminFormPanel
                            title="Stitch file"
                            description={editing ? `Current file: ${design?.fileName ?? 'none'}` : 'Upload the downloadable stitch file.'}
                            icon={FileArchive}
                        >
                            <Input
                                id="stitchFile"
                                type="file"
                                onChange={(event) => setData('stitchFile', event.target.files?.[0] ?? null)}
                                className={inputClass}
                            />
                            <InputError message={errors.stitchFile} className="mt-2" />
                        </AdminFormPanel>
                    </div>

                    <div className="flex justify-end gap-3">
                        <Button variant="outline" asChild className="rounded-xl">
                            <Link href={route('admin.designs.index')}>Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={cannotSubmit} className="rounded-xl bg-indigo-600 px-5 font-semibold hover:bg-indigo-700">
                            {processing ? <LoaderCircle className="size-4 animate-spin" /> : <Save className="size-4" />}
                            {processing ? 'Saving...' : editing ? 'Save changes' : 'Create design'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

type SelectFieldProps = {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: { id: string; name: string }[];
    error?: string;
    disabled?: boolean;
};

function SelectField({ label, value, onChange, options, error, disabled = false }: SelectFieldProps) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Select value={value} onValueChange={onChange} disabled={disabled}>
                <SelectTrigger className={selectClass}>
                    <SelectValue placeholder={`Select ${label.toLowerCase()}`} />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem key={option.id} value={option.id}>
                            {option.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError message={error} />
        </div>
    );
}
