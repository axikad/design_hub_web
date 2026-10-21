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
type VariantInput = {
    typeId: string;
    nameOrNumber: string;
    stitchCount: string;
    needle: string;
    area: string;
    height: string;
    width: string;
};
type DesignForm = {
    id?: string;
    ownerId?: string;
    machineId?: string | null;
    machineTypeId?: string | null;
    categoryId?: string | null;
    designFileTypeId?: string | null;
    priceRupees: string;
    variants?: VariantInput[];
    images?: { name: string; uri: string; thumbnailUri?: string | null }[];
    files?: { name: string }[];
};
type Props = {
    design: DesignForm | null;
    owners: OwnerOption[];
    catalog: MachineOption[];
    fileTypes: FileTypeOption[];
};

const selectClass = 'h-11 rounded-xl border-slate-200 dark:border-slate-700';
const inputClass = 'h-11 rounded-xl border-slate-200 focus-visible:border-indigo-500 dark:border-slate-700';

const emptyVariant = (typeId: string): VariantInput => ({
    typeId,
    nameOrNumber: '',
    stitchCount: '0',
    needle: '0',
    area: '0',
    height: '0',
    width: '0',
});

export default function AdminDesignForm({ design, owners, catalog, fileTypes }: Props) {
    const initialMachine =
        catalog.find((machine) => machine.id === design?.machineId || machine.machineTypes.some((type) => type.id === design?.machineTypeId)) ??
        catalog[0];
    const initialMachineType = initialMachine?.machineTypes.find((type) => type.id === design?.machineTypeId) ?? initialMachine?.machineTypes[0];
    const initialCategory =
        initialMachineType?.categories.find((category) => category.id === design?.categoryId) ?? initialMachineType?.categories[0];
    const initialType = initialCategory?.types[0];
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
        designFileTypeId: design?.designFileTypeId ?? fileTypes[0]?.id ?? '',
        priceRupees: design?.priceRupees ?? '',
        variants:
            design?.variants?.length
                ? design.variants.map((variant) => ({
                      ...variant,
                      stitchCount: variant.stitchCount.toString(),
                      needle: variant.needle.toString(),
                      area: variant.area.toString(),
                      height: variant.height.toString(),
                      width: variant.width.toString(),
                  }))
                : initialType
                  ? [emptyVariant(initialType.id)]
                  : [],
        previewImages: [] as File[],
        stitchFiles: [] as File[],
    });
    const { data, setData, post, processing, errors } = form;
    const machineTypes = catalog.find((machine) => machine.id === data.machineId)?.machineTypes ?? [];
    const categories = machineTypes.find((type) => type.id === data.machineTypeId)?.categories ?? [];
    const types = categories.find((category) => category.id === data.categoryId)?.types ?? [];

    const selectMachine = (machineId: string) => {
        const machineType = catalog.find((machine) => machine.id === machineId)?.machineTypes[0];
        const category = machineType?.categories[0];
        const typeId = category?.types[0]?.id;
        setData((current) => ({
            ...current,
            machineId,
            machineTypeId: machineType?.id ?? '',
            categoryId: category?.id ?? '',
            variants: typeId ? [emptyVariant(typeId)] : [],
        }));
    };
    const selectMachineType = (machineTypeId: string) => {
        const category = machineTypes.find((type) => type.id === machineTypeId)?.categories[0];
        const typeId = category?.types[0]?.id;
        setData((current) => ({
            ...current,
            machineTypeId,
            categoryId: category?.id ?? '',
            variants: typeId ? [emptyVariant(typeId)] : [],
        }));
    };
    const selectCategory = (categoryId: string) => {
        const typeId = categories.find((category) => category.id === categoryId)?.types[0]?.id;
        setData((current) => ({ ...current, categoryId, variants: typeId ? [emptyVariant(typeId)] : [] }));
    };
    const toggleType = (typeId: string) => {
        const selected = data.variants.some((variant) => variant.typeId === typeId);
        setData('variants', selected ? data.variants.filter((variant) => variant.typeId !== typeId) : [...data.variants, emptyVariant(typeId)]);
    };
    const updateVariant = (index: number, field: keyof VariantInput, value: string) => {
        setData(
            'variants',
            data.variants.map((variant, variantIndex) => (variantIndex === index ? { ...variant, [field]: value } : variant)),
        );
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
        processing ||
        !owners.length ||
        !catalog.length ||
        !machineTypes.length ||
        !categories.length ||
        !types.length ||
        !data.variants.length ||
        !fileTypes.length;

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
                        <div className="grid gap-5 sm:grid-cols-3">
                            <SelectField label="Machine" value={data.machineId} onChange={selectMachine} options={catalog} error={errors.machineId} />
                            <SelectField
                                key={`machine-type-${data.machineId}`}
                                label="Machine type"
                                value={data.machineTypeId}
                                onChange={selectMachineType}
                                options={machineTypes}
                                error={errors.machineTypeId}
                                disabled={!machineTypes.length}
                            />
                            <SelectField
                                key={`category-${data.machineTypeId}`}
                                label="Category"
                                value={data.categoryId}
                                onChange={selectCategory}
                                options={categories}
                                error={errors.categoryId}
                                disabled={!categories.length}
                            />
                        </div>
                        <div className="mt-5 grid gap-2">
                            <Label>Design types</Label>
                            <div className="flex flex-wrap gap-2">
                                {types.map((type) => {
                                    const selected = data.variants.some((variant) => variant.typeId === type.id);

                                    return (
                                        <button
                                            key={type.id}
                                            type="button"
                                            onClick={() => toggleType(type.id)}
                                            className={`rounded-full border px-4 py-2 text-sm font-semibold transition ${
                                                selected
                                                    ? 'border-indigo-600 bg-indigo-600 text-white'
                                                    : 'border-slate-200 bg-white text-slate-600 hover:border-indigo-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300'
                                            }`}
                                        >
                                            {type.name}
                                        </button>
                                    );
                                })}
                            </div>
                            <InputError message={errors.variants} />
                        </div>
                        {!catalog.length ? <p className="mt-4 text-sm text-amber-700">Add active catalog options before creating designs.</p> : null}
                    </AdminFormPanel>

                    <AdminFormPanel
                        title="Design type specifications"
                        description="Enter separate specifications for every selected design type."
                        icon={Layers3}
                    >
                        <div className="space-y-4">
                            {data.variants.map((variant, index) => {
                                const type = types.find((option) => option.id === variant.typeId);
                                const fieldError = (field: keyof VariantInput) =>
                                    (errors as Record<string, string>)[`variants.${index}.${field}`];

                                return (
                                    <div key={variant.typeId} className="rounded-2xl border border-slate-200 p-4 dark:border-slate-700">
                                        <h3 className="mb-4 font-semibold text-slate-900 dark:text-white">{type?.name ?? 'Design type'}</h3>
                                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                            <VariantField
                                                label="Name or number"
                                                value={variant.nameOrNumber}
                                                onChange={(value) => updateVariant(index, 'nameOrNumber', value)}
                                                error={fieldError('nameOrNumber')}
                                            />
                                            <VariantField
                                                label="Stitch count"
                                                type="number"
                                                min="0"
                                                value={variant.stitchCount}
                                                onChange={(value) => updateVariant(index, 'stitchCount', value)}
                                                error={fieldError('stitchCount')}
                                            />
                                            <VariantField
                                                label="Needle"
                                                type="number"
                                                min="0"
                                                value={variant.needle}
                                                onChange={(value) => updateVariant(index, 'needle', value)}
                                                error={fieldError('needle')}
                                            />
                                            <VariantField
                                                label="Area"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={variant.area}
                                                onChange={(value) => updateVariant(index, 'area', value)}
                                                error={fieldError('area')}
                                            />
                                            <VariantField
                                                label="Height"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={variant.height}
                                                onChange={(value) => updateVariant(index, 'height', value)}
                                                error={fieldError('height')}
                                            />
                                            <VariantField
                                                label="Width"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={variant.width}
                                                onChange={(value) => updateVariant(index, 'width', value)}
                                                error={fieldError('width')}
                                            />
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </AdminFormPanel>

                    <AdminFormPanel title="Pricing and format" description="Set the design file format and marketplace price." icon={Tag}>
                        <div className="grid gap-5 sm:grid-cols-2">
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
                        </div>
                    </AdminFormPanel>

                    <div className="grid gap-5 lg:grid-cols-2">
                        <AdminFormPanel
                            title="Preview images"
                            description={
                                editing
                                    ? 'Select new images only when you want to replace all current preview images.'
                                    : 'Upload up to 20 marketplace preview images.'
                            }
                            icon={ImageIcon}
                        >
                            {design?.images?.length ? (
                                <div className="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                                    {design.images.map((image, index) => (
                                        <div key={`${image.name}-${index}`} className="min-w-0">
                                            <img
                                                src={image.thumbnailUri ?? image.uri}
                                                alt={image.name}
                                                className="h-28 w-full rounded-xl object-cover ring-1 ring-slate-200 dark:ring-slate-700"
                                            />
                                            <p className="mt-1 truncate text-xs text-slate-500" title={image.name}>
                                                {image.name}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            ) : null}
                            <Input
                                id="previewImages"
                                type="file"
                                multiple
                                accept="image/jpeg,image/png,image/webp"
                                onChange={(event) => setData('previewImages', Array.from(event.target.files ?? []))}
                                className={inputClass}
                            />
                            {data.previewImages.length ? (
                                <ul className="mt-3 space-y-1 text-xs text-slate-500">
                                    {data.previewImages.map((image, index) => (
                                        <li key={`${image.name}-${index}`} className="truncate">
                                            {image.name}
                                        </li>
                                    ))}
                                </ul>
                            ) : null}
                            <p className="mt-2 text-xs text-slate-500">Maximum 20 images, 5 MB per image.</p>
                            <InputError message={errors.previewImages} className="mt-2" />
                        </AdminFormPanel>
                        <AdminFormPanel
                            title="Stitch files"
                            description={editing ? 'Select new files only when you want to replace all current stitch files.' : 'Upload up to 20 downloadable stitch files.'}
                            icon={FileArchive}
                        >
                            {editing && design?.files?.length ? (
                                <div className="mb-4 rounded-xl bg-slate-50 p-3 dark:bg-slate-800/70">
                                    <p className="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">Current files</p>
                                    <ul className="space-y-1 text-sm text-slate-700 dark:text-slate-200">
                                        {design.files.map((file, index) => (
                                            <li key={`${file.name}-${index}`}>{file.name}</li>
                                        ))}
                                    </ul>
                                </div>
                            ) : null}
                            <Input
                                id="stitchFiles"
                                type="file"
                                multiple
                                onChange={(event) => setData('stitchFiles', Array.from(event.target.files ?? []))}
                                className={inputClass}
                            />
                            <p className="mt-2 text-xs text-slate-500">Maximum 20 files, 5 MB per file.</p>
                            <InputError message={errors.stitchFiles} className="mt-2" />
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

type VariantFieldProps = {
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    type?: 'text' | 'number';
    min?: string;
    step?: string;
};

function VariantField({ label, value, onChange, error, type = 'text', min, step }: VariantFieldProps) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Input
                type={type}
                min={min}
                step={step}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                placeholder={type === 'number' ? '0' : 'Enter name or number'}
                required
                className={inputClass}
            />
            <InputError message={error} />
        </div>
    );
}
