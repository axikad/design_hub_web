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
import { ArrowLeft, Cpu, ImageIcon, LoaderCircle, Save, SlidersHorizontal, Trash2, Upload } from 'lucide-react';
import { type ChangeEvent, type FormEventHandler, useRef, useState } from 'react';

type DesignFormConfig = {
    machineType: boolean;
    category: boolean;
    designTypes: boolean;
    designSpecifications: boolean;
    sequenceType: boolean;
    designFileType: boolean;
    previewImages: boolean;
    stitchFiles: boolean;
    pricing: boolean;
};
type MachineForm = {
    id: string;
    name: string;
    imageUri: string | null;
    isActive: boolean;
    sortOrder: number;
    designFormConfig: DesignFormConfig;
};
type Props = { machine: MachineForm | null; nextSortOrder: number; defaultDesignFormConfig: DesignFormConfig };
type MachineFormData = {
    name: string;
    image: File | null;
    removeImage: boolean;
    isActive: boolean;
    sortOrder: number;
    designFormConfig: DesignFormConfig;
    _method: 'post' | 'put';
};

const designFieldOptions: { key: keyof DesignFormConfig; label: string; description: string }[] = [
    { key: 'machineType', label: 'Machine type', description: 'Require a machine type selection.' },
    { key: 'category', label: 'Category', description: 'Require a compatible category.' },
    { key: 'designTypes', label: 'Design types', description: 'Allow catalog and custom design types.' },
    { key: 'designSpecifications', label: 'Design type specifications', description: 'Collect stitch count, needle, area, height and width.' },
    { key: 'sequenceType', label: 'Sequence type', description: 'Collect the common sequence measurement in mm.' },
    { key: 'designFileType', label: 'Design file type', description: 'Require EMB, DST, DHB or another configured format.' },
    { key: 'previewImages', label: 'Preview images', description: 'Require at least one preview image.' },
    { key: 'stitchFiles', label: 'Stitch files', description: 'Require at least one uploaded machine file.' },
    { key: 'pricing', label: 'Pricing', description: 'Require a selling price for the design.' },
];

export default function AdminMachineForm({ machine, nextSortOrder, defaultDesignFormConfig }: Props) {
    const editing = Boolean(machine?.id);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Overview', href: '/dashboard' },
        { title: 'Machines', href: '/admin/machines' },
        { title: editing ? 'Edit machine' : 'Add machine', href: '#' },
    ];
    const [imagePreview, setImagePreview] = useState<string | null>(machine?.imageUri ?? null);
    const imageInput = useRef<HTMLInputElement>(null);
    const { data, setData, post, processing, errors } = useForm<MachineFormData>({
        name: machine?.name ?? '',
        image: null,
        removeImage: false,
        isActive: machine?.isActive ?? true,
        sortOrder: machine?.sortOrder ?? nextSortOrder,
        designFormConfig: machine?.designFormConfig ?? defaultDesignFormConfig,
        _method: editing ? 'put' : 'post',
    });

    const toggleDesignField = (key: keyof DesignFormConfig, enabled: boolean) => {
        setData('designFormConfig', {
            ...data.designFormConfig,
            [key]: enabled,
            ...(key === 'machineType' && !enabled ? { category: false, designTypes: false, designSpecifications: false } : {}),
            ...(key === 'category' && !enabled ? { designTypes: false, designSpecifications: false } : {}),
            ...(key === 'designTypes' && !enabled ? { designSpecifications: false } : {}),
            ...(key === 'stitchFiles' && !enabled ? { designFileType: false } : {}),
        });
    };

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(editing && machine?.id ? route('admin.machines.update', machine.id) : route('admin.machines.store'), {
            forceFormData: true,
        });
    };

    const selectImage = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0] ?? null;

        if (!file) return;

        setData('image', file);
        setData('removeImage', false);

        const reader = new FileReader();
        reader.onload = () => setImagePreview(typeof reader.result === 'string' ? reader.result : null);
        reader.readAsDataURL(file);
    };

    const removeImage = () => {
        setData('image', null);
        setData('removeImage', true);
        setImagePreview(null);

        if (imageInput.current) imageInput.current.value = '';
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={editing ? 'Edit machine' : 'Add machine'} />
            <div className="mx-auto max-w-4xl space-y-6 p-4 md:p-6">
                <AdminPageHeader
                    title={editing ? 'Edit machine' : 'Add machine'}
                    description="Machines are the top-level grouping used to organize compatible machine types."
                    actions={
                        <Button variant="outline" asChild className="rounded-xl">
                            <Link href={route('admin.machines.index')}>
                                <ArrowLeft className="size-4" /> Back
                            </Link>
                        </Button>
                    }
                />

                <form onSubmit={submit} className="space-y-5">
                    <AdminFormPanel title="Machine details" description="Set its catalog name, display order and availability." icon={Cpu}>
                        <div className="grid gap-5 sm:grid-cols-[1fr_180px]">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Machine name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(event) => setData('name', event.target.value)}
                                    required
                                    autoFocus
                                    placeholder="e.g. Embroidery"
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
                        <label className="mt-5 flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                            <Checkbox checked={data.isActive} onCheckedChange={(value) => setData('isActive', value === true)} className="mt-0.5" />
                            <span>
                                <span className="block text-sm font-semibold text-slate-800 dark:text-slate-100">Active in catalog</span>
                                <span className="mt-1 block text-xs text-slate-500">
                                    Allow this machine to appear in admin and mobile catalog selections.
                                </span>
                            </span>
                        </label>
                    </AdminFormPanel>

                    <AdminFormPanel
                        title="Machine image"
                        description="Upload the image shown for this machine in the admin and mobile catalog."
                        icon={ImageIcon}
                    >
                        <div className="flex flex-col gap-5 sm:flex-row sm:items-center">
                            <div className="flex size-32 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900">
                                {imagePreview ? (
                                    <img src={imagePreview} alt="Machine preview" className="size-full object-cover" />
                                ) : (
                                    <ImageIcon className="size-8 text-slate-300" />
                                )}
                            </div>
                            <div className="space-y-3">
                                <input
                                    ref={imageInput}
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    className="hidden"
                                    onChange={selectImage}
                                />
                                <div className="flex flex-wrap gap-2">
                                    <Button type="button" variant="outline" className="rounded-xl" onClick={() => imageInput.current?.click()}>
                                        <Upload className="size-4" /> {imagePreview ? 'Change image' : 'Choose image'}
                                    </Button>
                                    {imagePreview ? (
                                        <Button type="button" variant="outline" className="rounded-xl text-red-600" onClick={removeImage}>
                                            <Trash2 className="size-4" /> Remove
                                        </Button>
                                    ) : null}
                                </div>
                                <p className="text-xs text-slate-500">JPG, PNG or WebP. Maximum size 5 MB.</p>
                                <InputError message={errors.image} />
                            </div>
                        </div>
                    </AdminFormPanel>

                    <AdminFormPanel
                        title="Design creation fields"
                        description="Choose which fields are used when a design is created for this machine."
                        icon={SlidersHorizontal}
                    >
                        <div className="grid gap-3 sm:grid-cols-2">
                            {designFieldOptions.map((option) => (
                                <label
                                    key={option.key}
                                    className="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                                >
                                    <Checkbox
                                        checked={data.designFormConfig[option.key]}
                                        onCheckedChange={(value) => toggleDesignField(option.key, value === true)}
                                        className="mt-0.5"
                                    />
                                    <span>
                                        <span className="block text-sm font-semibold text-slate-800 dark:text-slate-100">{option.label}</span>
                                        <span className="mt-1 block text-xs text-slate-500">{option.description}</span>
                                    </span>
                                </label>
                            ))}
                        </div>
                        <InputError message={errors.designFormConfig} />
                    </AdminFormPanel>

                    <div className="flex justify-end gap-3">
                        <Button variant="outline" asChild className="rounded-xl">
                            <Link href={route('admin.machines.index')}>Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={processing} className="rounded-xl bg-indigo-600 px-5 font-semibold hover:bg-indigo-700">
                            {processing ? <LoaderCircle className="size-4 animate-spin" /> : <Save className="size-4" />}
                            {processing ? 'Saving...' : editing ? 'Save changes' : 'Create machine'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
