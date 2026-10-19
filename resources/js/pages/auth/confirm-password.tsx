import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLoginLayout from '@/layouts/auth/admin-login-layout';
import { Head, useForm } from '@inertiajs/react';
import { Eye, EyeOff, LoaderCircle, LockKeyhole, ShieldCheck } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

type ConfirmPasswordForm = {
    password: string;
    [key: string]: string;
};

export default function ConfirmPassword() {
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm<ConfirmPasswordForm>({ password: '' });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AdminLoginLayout>
            <Head title="Confirm password" />

            <div className="mb-7">
                <p className="text-xs font-semibold tracking-[0.18em] text-indigo-600 uppercase dark:text-indigo-400">Security check</p>
                <h1 className="mt-2 text-3xl font-bold tracking-tight text-slate-950 dark:text-white">Confirm your password</h1>
                <p className="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">
                    This is a protected area. Re-enter your password before continuing.
                </p>
            </div>

            <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/50 sm:p-8 dark:border-slate-800 dark:bg-slate-900 dark:shadow-black/20">
                <div className="mb-5 flex items-center gap-3 rounded-xl border border-indigo-100 bg-indigo-50/70 px-4 py-3 dark:border-indigo-900/50 dark:bg-indigo-950/30">
                    <ShieldCheck className="size-5 shrink-0 text-indigo-600 dark:text-indigo-300" />
                    <p className="text-sm text-indigo-800 dark:text-indigo-200">Your session is active. We only need to verify it is really you.</p>
                </div>

                <form className="space-y-5" onSubmit={submit}>
                    <div className="grid gap-2">
                        <Label htmlFor="password" className="text-sm font-semibold text-slate-700 dark:text-slate-200">
                            Password
                        </Label>
                        <div className="relative">
                            <LockKeyhole className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <Input
                                id="password"
                                type={showPassword ? 'text' : 'password'}
                                name="password"
                                required
                                autoFocus
                                autoComplete="current-password"
                                value={data.password}
                                onChange={(event) => setData('password', event.target.value)}
                                disabled={processing}
                                placeholder="Enter your password"
                                className="h-11 rounded-xl border-slate-300 pr-11 pl-10 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20 dark:border-slate-700"
                            />
                            <button
                                type="button"
                                onClick={() => setShowPassword((visible) => !visible)}
                                className="absolute top-1/2 right-3 -translate-y-1/2 rounded-md p-1 text-slate-400 transition hover:bg-slate-100 hover:text-indigo-600 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none dark:hover:bg-slate-800"
                                aria-label={showPassword ? 'Hide password' : 'Show password'}
                                aria-pressed={showPassword}
                            >
                                {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                            </button>
                        </div>
                        <InputError message={errors.password} />
                    </div>

                    <Button
                        type="submit"
                        className="h-11 w-full rounded-xl bg-indigo-600 font-semibold text-white shadow-lg shadow-indigo-500/20 hover:bg-indigo-700"
                        disabled={processing}
                    >
                        {processing ? <LoaderCircle className="size-4 animate-spin" /> : <ShieldCheck className="size-4" />}
                        {processing ? 'Confirming...' : 'Confirm and continue'}
                    </Button>
                </form>
            </div>
        </AdminLoginLayout>
    );
}
