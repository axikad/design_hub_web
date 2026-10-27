import { AdminFormPanel } from '@/components/admin/admin-form-panel';
import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { ExternalLink, FileText, LoaderCircle, Save } from 'lucide-react';
import { type FormEvent } from 'react';

type ContentPage = {
    id: number;
    slug: string;
    title: string;
    content: string;
    isActive: boolean;
    publicUrl: string | null;
    updatedAt: string | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/dashboard' },
    { title: 'Content pages', href: '/admin/content-pages' },
];

export default function ContentPagesIndex({ pages }: { pages: ContentPage[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Content pages" />
            <div className="mx-auto max-w-5xl space-y-6 p-4 md:p-6">
                <AdminPageHeader
                    title="Content pages"
                    description="Manage the public pages opened by the mobile app in a WebView. Markdown formatting is supported."
                />
                <div className="space-y-6">
                    {pages.map((page) => (
                        <PageEditor key={page.id} page={page} />
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}

function PageEditor({ page }: { page: ContentPage }) {
    const form = useForm({
        title: page.title,
        content: page.content,
        isActive: page.isActive,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(route('admin.content-pages.update', page.id), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit}>
            <AdminFormPanel title={page.title} description={`Public page: ${page.slug}`} icon={FileText}>
                <div className="space-y-5">
                    <div className="grid gap-2">
                        <Label htmlFor={`title-${page.id}`}>Page title</Label>
                        <Input
                            id={`title-${page.id}`}
                            value={form.data.title}
                            onChange={(event) => form.setData('title', event.target.value)}
                            maxLength={160}
                            required
                            className="h-11 rounded-xl"
                        />
                        <InputError message={form.errors.title} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`content-${page.id}`}>Content</Label>
                        <textarea
                            id={`content-${page.id}`}
                            value={form.data.content}
                            onChange={(event) => form.setData('content', event.target.value)}
                            required
                            rows={14}
                            className="min-h-72 w-full rounded-xl border border-slate-200 bg-transparent px-3 py-2 font-mono text-sm leading-6 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700"
                        />
                        <p className="text-xs text-slate-500">Use Markdown for headings, lists, links, and emphasis.</p>
                        <InputError message={form.errors.content} />
                    </div>

                    <div className="flex flex-col justify-between gap-4 rounded-xl border border-slate-200 p-4 sm:flex-row sm:items-center dark:border-slate-700">
                        <div className="flex items-center gap-3">
                            <Checkbox
                                id={`active-${page.id}`}
                                checked={form.data.isActive}
                                onCheckedChange={(checked) => form.setData('isActive', checked === true)}
                            />
                            <div>
                                <Label htmlFor={`active-${page.id}`}>Publicly visible</Label>
                                <p className="text-xs text-slate-500">Inactive pages return a 404 response.</p>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {page.publicUrl && (
                                <Button type="button" variant="outline" asChild className="rounded-xl">
                                    <a href={page.publicUrl} target="_blank" rel="noreferrer">
                                        <ExternalLink className="size-4" />
                                        Preview
                                    </a>
                                </Button>
                            )}
                            <Button type="submit" disabled={form.processing} className="rounded-xl bg-indigo-600 font-semibold hover:bg-indigo-700">
                                {form.processing ? <LoaderCircle className="size-4 animate-spin" /> : <Save className="size-4" />}
                                Save page
                            </Button>
                        </div>
                    </div>
                </div>
            </AdminFormPanel>
        </form>
    );
}
