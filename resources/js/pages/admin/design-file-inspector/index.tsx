import { AdminFormPanel } from '@/components/admin/admin-form-panel';
import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Braces, Check, CheckCircle2, Copy, FileSearch, LoaderCircle, Ruler, ScanLine, ShieldCheck, Upload } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

type InspectionResult = {
    file: { name: string; extension: string; mimeType: string; sizeBytes: number; sha256: string };
    format: { id: string; name: string };
    metadata: {
        label: string | null;
        stitchCount: number;
        colorChangeCount: number;
        threadColorCount: number;
        widthMm: number;
        heightMm: number;
        boundsMm: { left: number; right: number; top: number; bottom: number };
        finalPositionMm: { x: number; y: number };
        header: Record<string, string>;
    };
};

type Props = { result: InspectionResult | null; supportedExtensions: string[] };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/dashboard' },
    { title: 'File Inspector', href: '/admin/design-file-inspector' },
];

function formatBytes(bytes: number) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(2)} MB`;
}

const metricCards = (result: InspectionResult) => [
    { label: 'Stitches', value: result.metadata.stitchCount.toLocaleString(), icon: ScanLine },
    { label: 'Width', value: `${result.metadata.widthMm.toFixed(1)} mm`, icon: Ruler },
    { label: 'Height', value: `${result.metadata.heightMm.toFixed(1)} mm`, icon: Ruler },
    { label: 'Thread colors', value: result.metadata.threadColorCount.toString(), icon: CheckCircle2 },
];

export default function DesignFileInspectorPage({ result, supportedExtensions }: Props) {
    const { data, setData, post, processing, errors, progress } = useForm({ designFile: null as File | null });
    const [copied, setCopied] = useState(false);
    const responseJson = result ? JSON.stringify(result, null, 2) : '';

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('admin.design-file-inspector.inspect'), { forceFormData: true, preserveScroll: true });
    };

    const copyResponse = async () => {
        await navigator.clipboard.writeText(responseJson);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2000);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Design File Inspector" />
            <div className="mx-auto max-w-5xl space-y-6 p-4 md:p-6">
                <AdminPageHeader
                    title="Design File Inspector"
                    description="Safely inspect stitch-file metadata before connecting extraction to design create, frontend, and mobile flows."
                />

                <form onSubmit={submit}>
                    <AdminFormPanel
                        title="Upload a design file"
                        description={`Current demo parser: ${supportedExtensions.map((item) => `.${item.toUpperCase()}`).join(', ')}. The original file is not stored.`}
                        icon={FileSearch}
                    >
                        <div className="grid gap-3">
                            <Label htmlFor="designFile">Stitch file</Label>
                            <Input
                                id="designFile"
                                type="file"
                                accept={supportedExtensions.map((item) => `.${item}`).join(',')}
                                required
                                onChange={(event) => setData('designFile', event.target.files?.[0] ?? null)}
                                className="h-11 rounded-xl border-slate-200 dark:border-slate-700"
                            />
                            {data.designFile ? <p className="text-xs text-slate-500">Selected: {data.designFile.name}</p> : null}
                            <InputError message={errors.designFile} />
                            {progress ? (
                                <div className="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                    <div className="h-full bg-indigo-600 transition-all" style={{ width: `${progress.percentage}%` }} />
                                </div>
                            ) : null}
                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing || !data.designFile} className="rounded-xl bg-indigo-600 hover:bg-indigo-700">
                                    {processing ? <LoaderCircle className="size-4 animate-spin" /> : <Upload className="size-4" />}
                                    {processing ? 'Inspecting...' : 'Inspect metadata'}
                                </Button>
                            </div>
                        </div>
                    </AdminFormPanel>
                </form>

                {result ? (
                    <>
                        <AdminFormPanel title="Extracted metadata" description={`${result.format.name} · ${result.file.name}`} icon={ShieldCheck}>
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                {metricCards(result).map(({ label, value, icon: Icon }) => (
                                    <div key={label} className="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950/40">
                                        <Icon className="mb-3 size-4 text-indigo-600 dark:text-indigo-300" />
                                        <p className="text-xs font-medium text-slate-500">{label}</p>
                                        <p className="mt-1 text-xl font-bold text-slate-950 dark:text-white">{value}</p>
                                    </div>
                                ))}
                            </div>

                            <dl className="mt-5 grid gap-x-8 gap-y-3 border-t border-slate-100 pt-5 text-sm sm:grid-cols-2 dark:border-slate-800">
                                <div className="flex justify-between gap-4"><dt className="text-slate-500">Design label</dt><dd className="font-semibold">{result.metadata.label || 'Not provided'}</dd></div>
                                <div className="flex justify-between gap-4"><dt className="text-slate-500">Color changes</dt><dd className="font-semibold">{result.metadata.colorChangeCount}</dd></div>
                                <div className="flex justify-between gap-4"><dt className="text-slate-500">File size</dt><dd className="font-semibold">{formatBytes(result.file.sizeBytes)}</dd></div>
                                <div className="flex justify-between gap-4"><dt className="text-slate-500">MIME type</dt><dd className="font-semibold">{result.file.mimeType}</dd></div>
                            </dl>
                        </AdminFormPanel>

                        <AdminFormPanel title="Integrity details" description="Useful for auditing, duplicate detection, and future API responses." icon={ShieldCheck}>
                            <div className="space-y-3 text-sm">
                                <div><p className="text-xs font-medium text-slate-500">SHA-256</p><code className="mt-1 block break-all rounded-xl bg-slate-950 p-3 text-xs text-slate-100">{result.file.sha256}</code></div>
                                <p className="text-xs leading-5 text-slate-500">Demo inspection is read-only. The uploaded temporary file is discarded after the request and no database record is created.</p>
                            </div>
                        </AdminFormPanel>

                        <AdminFormPanel
                            title="Full response JSON"
                            description="Complete normalized response including file details, extracted metadata, bounds, and the original DST header fields."
                            icon={Braces}
                        >
                            <div className="mb-3 flex justify-end">
                                <Button type="button" variant="outline" size="sm" className="rounded-lg" onClick={copyResponse}>
                                    {copied ? <Check className="size-4 text-emerald-600" /> : <Copy className="size-4" />}
                                    {copied ? 'Copied' : 'Copy JSON'}
                                </Button>
                            </div>
                            <pre className="max-h-[32rem] overflow-auto rounded-xl bg-slate-950 p-4 font-mono text-xs leading-5 text-slate-100 shadow-inner">
                                <code>{responseJson}</code>
                            </pre>
                        </AdminFormPanel>
                    </>
                ) : null}
            </div>
        </AppLayout>
    );
}
