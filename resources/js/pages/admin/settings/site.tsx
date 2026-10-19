import { AdminFormPanel } from '@/components/admin/admin-form-panel';
import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Globe2, ImageIcon, LoaderCircle, Save } from 'lucide-react';
import { type FormEventHandler } from 'react';

type SiteSettings = { name: string; logoUrl: string | null; faviconUrl: string | null };
type Props = { settings: SiteSettings };
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/dashboard' },
    { title: 'Site settings', href: '/admin/settings/site' },
];
const inputClass = 'h-11 rounded-xl border-slate-200 focus-visible:border-indigo-500 dark:border-slate-700';

export default function AdminSiteSettings({ settings }: Props) {
    const form = useForm({ name: settings.name, logo: null as File | null, favicon: null as File | null });
    const { data, setData, post, processing, errors } = form;
    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('admin.settings.site.update'), { forceFormData: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Site settings" />
            <div className="mx-auto max-w-4xl space-y-6 p-4 md:p-6">
                <AdminPageHeader title="Site settings" description="Manage the admin panel name, sidebar logo, and browser favicon." />
                <form onSubmit={submit} className="space-y-5">
                    <AdminFormPanel title="Brand identity" description="This name is displayed throughout the admin interface." icon={Globe2}>
                        <div className="grid gap-2">
                            <Label htmlFor="name">Website name</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                                required
                                maxLength={80}
                                className={inputClass}
                            />
                            <InputError message={errors.name} />
                        </div>
                    </AdminFormPanel>

                    <AdminFormPanel title="Brand images" description="Upload optimized images for the sidebar and browser tab." icon={ImageIcon}>
                        <div className="grid gap-6 sm:grid-cols-2">
                            <UploadField
                                id="logo"
                                label="Sidebar logo"
                                currentUrl={settings.logoUrl}
                                previewClass="size-20 rounded-2xl"
                                help="PNG, JPG, WebP or SVG. Maximum 2 MB."
                                accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                onChange={(file) => setData('logo', file)}
                                error={errors.logo}
                            />
                            <UploadField
                                id="favicon"
                                label="Browser favicon"
                                currentUrl={settings.faviconUrl}
                                previewClass="size-16 rounded-xl"
                                help="PNG, JPG, ICO or SVG. Maximum 1 MB."
                                accept="image/png,image/jpeg,image/x-icon,image/svg+xml,.ico"
                                onChange={(file) => setData('favicon', file)}
                                error={errors.favicon}
                            />
                        </div>
                    </AdminFormPanel>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing} className="rounded-xl bg-indigo-600 px-5 font-semibold hover:bg-indigo-700">
                            {processing ? <LoaderCircle className="size-4 animate-spin" /> : <Save className="size-4" />}
                            {processing ? 'Saving...' : 'Save settings'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

function UploadField({
    id,
    label,
    currentUrl,
    previewClass,
    help,
    accept,
    onChange,
    error,
}: {
    id: string;
    label: string;
    currentUrl: string | null;
    previewClass: string;
    help: string;
    accept: string;
    onChange: (file: File | null) => void;
    error?: string;
}) {
    return (
        <div className="grid gap-3 rounded-2xl border border-slate-200 p-4 dark:border-slate-700">
            <div className="flex items-center gap-3">
                {currentUrl ? (
                    <img
                        src={currentUrl}
                        alt=""
                        className={`${previewClass} bg-slate-50 object-contain p-2 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700`}
                    />
                ) : (
                    <span className={`${previewClass} flex items-center justify-center bg-slate-100 text-slate-400 dark:bg-slate-800`}>
                        <ImageIcon className="size-5" />
                    </span>
                )}
                <div>
                    <Label htmlFor={id}>{label}</Label>
                    <p className="mt-1 text-xs text-slate-500">{currentUrl ? 'Current image' : 'No image uploaded'}</p>
                </div>
            </div>
            <Input id={id} type="file" accept={accept} onChange={(event) => onChange(event.target.files?.[0] ?? null)} className={inputClass} />
            <p className="text-xs leading-5 text-slate-500">{help}</p>
            <InputError message={error} />
        </div>
    );
}
