import { Button } from '@/components/ui/button';
import AdminLoginLayout from '@/layouts/auth/admin-login-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle, LogOut, MailCheck, RefreshCw } from 'lucide-react';
import { type FormEventHandler } from 'react';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('verification.send'));
    };

    return (
        <AdminLoginLayout>
            <Head title="Email verification" />

            <div className="mb-7">
                <p className="text-xs font-semibold tracking-[0.18em] text-indigo-600 uppercase dark:text-indigo-400">Secure your account</p>
                <h1 className="mt-2 text-3xl font-bold tracking-tight text-slate-950 dark:text-white">Verify your email</h1>
                <p className="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">
                    Open the verification link we sent to your email address to continue to DesignHub.
                </p>
            </div>

            <div className="rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-xl shadow-slate-200/50 sm:p-8 dark:border-slate-800 dark:bg-slate-900 dark:shadow-black/20">
                <div className="mx-auto flex size-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-300">
                    <MailCheck className="size-7" />
                </div>

                <h2 className="mt-5 text-lg font-bold text-slate-900 dark:text-white">Check your inbox</h2>
                <p className="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">
                    Didn&apos;t receive the message? You can request another verification email below.
                </p>

                {status === 'verification-link-sent' ? (
                    <div className="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-left text-sm font-medium text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300">
                        A new verification link has been sent to your email address.
                    </div>
                ) : null}

                <form onSubmit={submit} className="mt-6 space-y-4">
                    <Button
                        type="submit"
                        className="h-11 w-full rounded-xl bg-indigo-600 font-semibold text-white shadow-lg shadow-indigo-500/20 hover:bg-indigo-700"
                        disabled={processing}
                    >
                        {processing ? <LoaderCircle className="size-4 animate-spin" /> : <RefreshCw className="size-4" />}
                        {processing ? 'Sending email...' : 'Resend verification email'}
                    </Button>

                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-indigo-600 hover:underline dark:text-slate-400 dark:hover:text-indigo-400"
                    >
                        <LogOut className="size-4" />
                        Log out
                    </Link>
                </form>
            </div>
        </AdminLoginLayout>
    );
}
