import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLoginLayout from '@/layouts/auth/admin-login-layout';
import { Head, useForm } from '@inertiajs/react';
import { Eye, EyeOff, KeyRound, LoaderCircle, LockKeyhole, Mail } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

type ResetPasswordProps = {
    token: string;
    email: string;
};

type ResetPasswordForm = {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
    [key: string]: string;
};

export default function ResetPassword({ token, email }: ResetPasswordProps) {
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm<ResetPasswordForm>({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AdminLoginLayout>
            <Head title="Reset password" />

            <div className="mb-7">
                <p className="text-xs font-semibold tracking-[0.18em] text-indigo-600 uppercase dark:text-indigo-400">Account recovery</p>
                <h1 className="mt-2 text-3xl font-bold tracking-tight text-slate-950 dark:text-white">Choose a new password</h1>
                <p className="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">
                    Create a strong password to secure your DesignHub administrator account.
                </p>
            </div>

            <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/50 sm:p-8 dark:border-slate-800 dark:bg-slate-900 dark:shadow-black/20">
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
                                autoComplete="email"
                                value={data.email}
                                readOnly
                                className="h-11 rounded-xl border-slate-300 bg-slate-50 pl-10 text-slate-500 dark:border-slate-700 dark:bg-slate-950/50"
                            />
                        </div>
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2 sm:grid-cols-2 sm:gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="password" className="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                New password
                            </Label>
                            <div className="relative">
                                <LockKeyhole className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                <Input
                                    id="password"
                                    type={showPassword ? 'text' : 'password'}
                                    name="password"
                                    required
                                    autoFocus
                                    autoComplete="new-password"
                                    value={data.password}
                                    onChange={(event) => setData('password', event.target.value)}
                                    disabled={processing}
                                    placeholder="New password"
                                    className="h-11 rounded-xl border-slate-300 pr-10 pl-10 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20 dark:border-slate-700"
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword((visible) => !visible)}
                                    className="absolute top-1/2 right-3 -translate-y-1/2 rounded-md p-1 text-slate-400 transition hover:bg-slate-100 hover:text-indigo-600 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none dark:hover:bg-slate-800"
                                    aria-label={showPassword ? 'Hide passwords' : 'Show passwords'}
                                    aria-pressed={showPassword}
                                >
                                    {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                </button>
                            </div>
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation" className="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                Confirm password
                            </Label>
                            <div className="relative">
                                <LockKeyhole className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                <Input
                                    id="password_confirmation"
                                    type={showPassword ? 'text' : 'password'}
                                    name="password_confirmation"
                                    required
                                    autoComplete="new-password"
                                    value={data.password_confirmation}
                                    onChange={(event) => setData('password_confirmation', event.target.value)}
                                    disabled={processing}
                                    placeholder="Repeat password"
                                    className="h-11 rounded-xl border-slate-300 pl-10 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20 dark:border-slate-700"
                                />
                            </div>
                            <InputError message={errors.password_confirmation} />
                        </div>
                    </div>

                    <Button
                        type="submit"
                        className="h-11 w-full rounded-xl bg-indigo-600 font-semibold text-white shadow-lg shadow-indigo-500/20 hover:bg-indigo-700"
                        disabled={processing}
                    >
                        {processing ? <LoaderCircle className="size-4 animate-spin" /> : <KeyRound className="size-4" />}
                        {processing ? 'Updating password...' : 'Reset password'}
                    </Button>
                </form>
            </div>
        </AdminLoginLayout>
    );
}
