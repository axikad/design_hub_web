import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type SiteSettings = {
    name: string;
    logoUrl: string | null;
    faviconUrl: string | null;
};

type Props = {
    settings: SiteSettings;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/dashboard' },
    { title: 'Site settings', href: '/admin/settings/site' },
];

export default function AdminSiteSettings({ settings }: Props) {
    const form = useForm({
        name: settings.name,
        logo: null as File | null,
        favicon: null as File | null,
    });

    const { data, setData, post, processing, errors } = form;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.settings.site.update'), { forceFormData: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Site settings" />
            <div className="mx-auto max-w-xl space-y-6">
                <AdminPageHeader
                    title="Site settings"
                    description="Change the website name shown in the admin panel and browser tab, plus favicon and sidebar logo."
                />
                <form onSubmit={submit} className="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Website name</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required maxLength={80} />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="favicon">Favicon</Label>
                        {settings.faviconUrl ? (
                            <img src={settings.faviconUrl} alt="" className="mb-2 h-8 w-8 rounded object-contain ring-1 ring-slate-200" />
                        ) : null}
                        <Input
                            id="favicon"
                            type="file"
                            accept="image/png,image/jpeg,image/x-icon,image/svg+xml,.ico"
                            onChange={(e) => setData('favicon', e.target.files?.[0] ?? null)}
                        />
                        <p className="text-xs text-slate-500">PNG, JPG, ICO or SVG. Max 1 MB. Shown in the browser tab.</p>
                        <InputError message={errors.favicon} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="logo">Sidebar icon / logo</Label>
                        {settings.logoUrl ? (
                            <img src={settings.logoUrl} alt="" className="mb-2 h-12 w-12 rounded-lg object-contain ring-1 ring-slate-200" />
                        ) : null}
                        <Input
                            id="logo"
                            type="file"
                            accept="image/png,image/jpeg,image/webp,image/svg+xml"
                            onChange={(e) => setData('logo', e.target.files?.[0] ?? null)}
                        />
                        <p className="text-xs text-slate-500">PNG, JPG, WebP or SVG. Max 2 MB. Shown next to the site name in the sidebar.</p>
                        <InputError message={errors.logo} />
                    </div>

                    <Button type="submit" disabled={processing} className="bg-indigo-600 hover:bg-indigo-700">
                        Save settings
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
