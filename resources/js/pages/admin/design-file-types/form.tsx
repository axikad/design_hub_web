import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEventHandler } from 'react';

type FileTypeForm = {
    id: string;
    name: string;
    extension: string;
    isActive: boolean;
    sortOrder: number;
};

type Props = { fileType: FileTypeForm | null };

export default function AdminDesignFileTypeForm({ fileType }: Props) {
    const editing = Boolean(fileType?.id);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Overview', href: '/dashboard' },
        { title: 'Design File Types', href: '/admin/design-file-types' },
        { title: editing ? 'Edit' : 'Add', href: '#' },
    ];

    const { data, setData, post, put, processing, errors } = useForm({
        name: fileType?.name ?? '',
        extension: fileType?.extension ?? '',
        isActive: fileType?.isActive ?? true,
        sortOrder: fileType?.sortOrder ?? 0,
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        if (editing && fileType?.id) put(route('admin.design-file-types.update', fileType.id));
        else post(route('admin.design-file-types.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={editing ? 'Edit design file type' : 'Add design file type'} />
            <div className="mx-auto max-w-lg space-y-6">
                <AdminPageHeader
                    title={editing ? 'Edit design file type' : 'Add design file type'}
                    description="Configure the name and extension shown to users."
                />
                <form
                    onSubmit={submit}
                    className="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input id="name" value={data.name} onChange={(event) => setData('name', event.target.value)} placeholder="DST" required />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="extension">Extension</Label>
                        <Input
                            id="extension"
                            value={data.extension}
                            onChange={(event) => setData('extension', event.target.value)}
                            placeholder="dst"
                            required
                        />
                        <p className="text-xs text-slate-500">Enter with or without the dot. It will be saved in lowercase.</p>
                        <InputError message={errors.extension} />
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
                        Active
                    </label>
                    <InputError message={errors.isActive} />
                    <div className="flex gap-3 pt-2">
                        <Button type="submit" disabled={processing} className="bg-indigo-600 hover:bg-indigo-700">
                            {editing ? 'Save changes' : 'Create file type'}
                        </Button>
                        <Button type="button" variant="outline" asChild>
                            <Link href={route('admin.design-file-types.index')}>Cancel</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
