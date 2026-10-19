import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLoginLayout from '@/layouts/auth/admin-login-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, LoaderCircle, Mail, Send } from 'lucide-react';
import { type FormEventHandler } from 'react';

type ForgotPasswordForm = {
    email: string;
    [key: string]: string;
};

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm<ForgotPasswordForm>({ email: '' });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('password.email'));
    };

    return (
        <AdminLoginLayout>
            <Head title="Forgot password" />

            <div className="mb-7">
                <p className="text-xs font-semibold tracking-[0.18em] text-indigo-600 uppercase dark:text-indigo-400">Account recovery</p>
                <h1 className="mt-2 text-3xl font-bold tracking-tight text-slate-950 dark:text-white">Reset your password</h1>
                <p className="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">
                    Enter your administrator email and we will send you a secure reset link.
                </p>
            </div>

            <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/50 sm:p-8 dark:border-slate-800 dark:bg-slate-900 dark:shadow-black/20">
                {status ? (
                    <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300">
                        {status}
                    </div>
                ) : null}

                <form className="space-y-5" onSubmit={submit}>
                    <div className="grid gap-2">
                        <Label htmlFor="email" className="text-sm font-semibold text-slate-700 dark:text-slate-200">
                            Email address
                        </Label>
                        <div className="relative">
                            <Mail className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoFocus
                                autoComplete="email"
                                value={data.email}
                                onChange={(event) => setData('email', event.target.value)}
                                disabled={processing}
                                placeholder="admin@example.com"
                                className="h-11 rounded-xl border-slate-300 pl-10 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20 dark:border-slate-700"
                            />
                        </div>
                        <InputError message={errors.email} />
                    </div>

                    <Button
                        type="submit"
                        className="h-11 w-full rounded-xl bg-indigo-600 font-semibold text-white shadow-lg shadow-indigo-500/20 hover:bg-indigo-700"
                        disabled={processing}
                    >
                        {processing ? <LoaderCircle className="size-4 animate-spin" /> : <Send className="size-4" />}
                        {processing ? 'Sending reset link...' : 'Email password reset link'}
                    </Button>
                </form>

                <div className="mt-6 border-t border-slate-100 pt-5 text-center dark:border-slate-800">
                    <Link
                        href={route('login')}
                        className="inline-flex items-center gap-2 text-sm font-semibold text-indigo-600 hover:underline dark:text-indigo-400"
                    >
                        <ArrowLeft className="size-4" />
                        Back to sign in
                    </Link>
                </div>
            </div>
        </AdminLoginLayout>
    );
}
