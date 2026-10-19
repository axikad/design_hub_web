import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
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

export default function AdminDesignForm({ design, owners, catalog, fileTypes, sizeOptions, colorOptions }: Props) {
    const initialMachine =
        catalog.find(
            (machine) => machine.id === design?.machineId || machine.machineTypes.some((machineType) => machineType.id === design?.machineTypeId),
        ) ?? catalog[0];
    const initialMachineType =
        initialMachine?.machineTypes.find((machineType) => machineType.id === design?.machineTypeId) ?? initialMachine?.machineTypes[0];
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
    const categories = machineTypes.find((machineType) => machineType.id === data.machineTypeId)?.categories ?? [];
    const types = categories.find((category) => category.id === data.categoryId)?.types ?? [];

    const selectMachine = (machineId: string) => {
        const machineType = catalog.find((machine) => machine.id === machineId)?.machineTypes[0];
        const category = machineType?.categories[0];
        const type = category?.types[0];

        setData({ ...data, machineId, machineTypeId: machineType?.id ?? '', categoryId: category?.id ?? '', typeId: type?.id ?? '' });
    };

    const selectMachineType = (machineTypeId: string) => {
        const category = machineTypes.find((machineType) => machineType.id === machineTypeId)?.categories[0];
        const type = category?.types[0];

        setData({ ...data, machineTypeId, categoryId: category?.id ?? '', typeId: type?.id ?? '' });
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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={editing ? 'Edit design' : 'Add design'} />
            <div className="mx-auto max-w-2xl space-y-6">
                <AdminPageHeader
                    title={editing ? 'Edit design' : 'Add design'}
                    description="Upload preview and stitch file, set INR price and assign a seller."
                />
                <form
                    onSubmit={submit}
                    className="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <div className="grid gap-2">
                        <Label>Seller</Label>
                        <Select value={data.ownerId} onValueChange={(value) => setData('ownerId', value)}>
                            <SelectTrigger>
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

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Machine</Label>
                            <Select value={data.machineId} onValueChange={selectMachine}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select machine" />
                                </SelectTrigger>
                                <SelectContent>
                                    {catalog.map((machine) => (
                                        <SelectItem key={machine.id} value={machine.id}>
                                            {machine.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.machineId} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Machine type</Label>
                            <Select value={data.machineTypeId} onValueChange={selectMachineType} disabled={!machineTypes.length}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select machine type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {machineTypes.map((machineType) => (
                                        <SelectItem key={machineType.id} value={machineType.id}>
                                            {machineType.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.machineTypeId} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Category</Label>
                            <Select value={data.categoryId} onValueChange={selectCategory} disabled={!categories.length}>
                                <SelectTrigger>
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
                            <Label>Design type</Label>
                            <Select value={data.typeId} onValueChange={(value) => setData('typeId', value)} disabled={!types.length}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select design type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {types.map((type) => (
                                        <SelectItem key={type.id} value={type.id}>
                                            {type.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.typeId} />
                        </div>
                    </div>

                    {!catalog.length ? (
                        <p className="text-sm text-amber-700">Add active machines and catalog options before creating designs.</p>
                    ) : null}

                    <div className="grid gap-2">
                        <Label>Design file type</Label>
                        <Select value={data.designFileTypeId} onValueChange={(value) => setData('designFileTypeId', value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select file type" />
                            </SelectTrigger>
                            <SelectContent>
                                {fileTypes.map((fileType) => (
                                    <SelectItem key={fileType.id} value={fileType.id}>
                                        {fileType.name} (.{fileType.extension})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.designFileTypeId} />
                        {!fileTypes.length ? <p className="text-sm text-amber-700">Add an active design file type before creating designs.</p> : null}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="title">Title</Label>
                        <Input id="title" value={data.title} onChange={(event) => setData('title', event.target.value)} maxLength={40} required />
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
                        />
                        <InputError message={errors.priceRupees} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Size</Label>
                            <Select value={data.size} onValueChange={(value) => setData('size', value)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {sizeOptions.map((size) => (
                                        <SelectItem key={size} value={size}>
                                            {size}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.size} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Color</Label>
                            <Select value={data.color} onValueChange={(value) => setData('color', value)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {colorOptions.map((color) => (
                                        <SelectItem key={color} value={color}>
                                            {color}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.color} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="previewImage">Preview photo {editing ? '(leave empty to keep current)' : ''}</Label>
                        {design?.imageUri ? (
                            <img src={design.imageUri} alt="" className="mb-2 h-24 w-24 rounded-lg object-cover ring-1 ring-slate-200" />
                        ) : null}
                        <Input
                            id="previewImage"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            onChange={(event) => setData('previewImage', event.target.files?.[0] ?? null)}
                        />
                        <InputError message={errors.previewImage} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="stitchFile">Stitch file {editing ? `(current: ${design?.fileName ?? 'none'})` : ''}</Label>
                        <Input id="stitchFile" type="file" onChange={(event) => setData('stitchFile', event.target.files?.[0] ?? null)} />
                        <InputError message={errors.stitchFile} />
                    </div>

                    <div className="flex gap-3 pt-2">
                        <Button
                            type="submit"
                            disabled={
                                processing ||
                                !owners.length ||
                                !catalog.length ||
                                !machineTypes.length ||
                                !categories.length ||
                                !types.length ||
                                !fileTypes.length
                            }
                            className="bg-indigo-600 hover:bg-indigo-700"
                        >
                            {editing ? 'Save changes' : 'Create design'}
                        </Button>
                        <Button type="button" variant="outline" asChild>
                            <a href={route('admin.designs.index')}>Cancel</a>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
