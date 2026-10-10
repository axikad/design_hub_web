import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type OwnerOption = { id: string; label: string };

type CatalogGroup = { id: string; name: string; types: { id: string; name: string }[] };

type DesignForm = {
    id?: string;
    ownerId?: string;
    typeId?: string;
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
    catalog: CatalogGroup[];
    sizeOptions: string[];
    colorOptions: string[];
};

export default function AdminDesignForm({ design, owners, catalog, sizeOptions, colorOptions }: Props) {
    const defaultTypeId = design?.typeId ?? catalog.flatMap((group) => group.types)[0]?.id ?? '';
    const editing = Boolean(design?.id);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Overview', href: '/dashboard' },
        { title: 'Designs', href: '/admin/designs' },
        { title: editing ? 'Edit design' : 'Add design', href: '#' },
    ];

    const form = useForm({
        ownerId: design?.ownerId ?? owners[0]?.id ?? '',
        typeId: defaultTypeId,
        title: design?.title ?? '',
        priceRupees: design?.priceRupees ?? '',
        size: design?.size ?? sizeOptions[1] ?? '',
        color: design?.color ?? colorOptions[0] ?? '',
        previewImage: null as File | null,
        stitchFile: null as File | null,
    });

    const { data, setData, post, processing, errors } = form;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
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
                <AdminPageHeader title={editing ? 'Edit design' : 'Add design'} description="Upload preview and stitch file, set INR price and assign a seller." />
                <form onSubmit={submit} className="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950">
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
                        {owners.length === 0 ? (
                            <p className="text-sm text-amber-700">Create a marketplace user before adding designs.</p>
                        ) : null}
                    </div>

                    <div className="grid gap-2">
                        <Label>Design type</Label>
                        <Select value={data.typeId} onValueChange={(value) => setData('typeId', value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                {catalog.map((group) =>
                                    group.types.map((type) => (
                                        <SelectItem key={type.id} value={type.id}>
                                            {group.name} → {type.name}
                                        </SelectItem>
                                    )),
                                )}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.typeId} />
                        {!catalog.length ? (
                            <p className="text-sm text-amber-700">Add categories and types before creating designs.</p>
                        ) : null}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="title">Title</Label>
                        <Input id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} maxLength={40} required />
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
                            onChange={(e) => setData('priceRupees', e.target.value)}
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
                            onChange={(e) => setData('previewImage', e.target.files?.[0] ?? null)}
                        />
                        <InputError message={errors.previewImage} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="stitchFile">
                            Stitch file {editing ? `(current: ${design?.fileName ?? 'none'})` : ''}
                        </Label>
                        <Input id="stitchFile" type="file" onChange={(e) => setData('stitchFile', e.target.files?.[0] ?? null)} />
                        <InputError message={errors.stitchFile} />
                    </div>

                    <div className="flex gap-3 pt-2">
                        <Button type="submit" disabled={processing || owners.length === 0 || !catalog.length} className="bg-indigo-600 hover:bg-indigo-700">
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
