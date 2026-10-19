import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLoginLayout from '@/layouts/auth/admin-login-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Eye, EyeOff, LoaderCircle, LockKeyhole, Mail } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

type LoginForm = {
    email: string;
    password: string;
    remember: boolean;
    [key: string]: string | boolean;
};

type LoginProps = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: LoginProps) {
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AdminLoginLayout>
            <Head title="Admin login" />

            <div className="mb-7">
                <p className="text-xs font-semibold tracking-[0.18em] text-indigo-600 uppercase dark:text-indigo-400">Admin portal</p>
                <h1 className="mt-2 text-3xl font-bold tracking-tight text-slate-950 dark:text-white">Welcome back</h1>
                <p className="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Sign in with your administrator email and password.</p>
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
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="email"
                                value={data.email}
                                onChange={(event) => setData('email', event.target.value)}
                                placeholder="admin@example.com"
                                className="h-11 rounded-xl border-slate-300 pl-10 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20 dark:border-slate-700"
                            />
                        </div>
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <div className="flex items-center justify-between gap-4">
                            <Label htmlFor="password" className="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                Password
                            </Label>
                            {canResetPassword ? (
                                <Link
                                    href={route('password.request')}
                                    className="text-xs font-semibold text-indigo-600 hover:text-indigo-700 hover:underline dark:text-indigo-400"
                                    tabIndex={5}
                                >
                                    Forgot password?
                                </Link>
                            ) : null}
                        </div>
                        <div className="relative">
                            <LockKeyhole className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <Input
                                id="password"
                                type={showPassword ? 'text' : 'password'}
                                required
                                tabIndex={2}
                                autoComplete="current-password"
                                value={data.password}
                                onChange={(event) => setData('password', event.target.value)}
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

                    <div className="flex items-center gap-2.5">
                        <Checkbox
                            id="remember"
                            checked={data.remember}
                            onCheckedChange={(checked) => setData('remember', checked === true)}
                            tabIndex={3}
                        />
                        <Label htmlFor="remember" className="cursor-pointer text-sm font-normal text-slate-600 dark:text-slate-400">
                            Keep me signed in
                        </Label>
                    </div>

                    <Button
                        type="submit"
                        className="h-11 w-full rounded-xl bg-indigo-600 font-semibold text-white shadow-lg shadow-indigo-500/20 hover:bg-indigo-700"
                        tabIndex={4}
                        disabled={processing}
                    >
                        {processing ? <LoaderCircle className="size-4 animate-spin" /> : <LockKeyhole className="size-4" />}
                        {processing ? 'Signing in...' : 'Sign in to dashboard'}
                    </Button>
                </form>

                <p className="mt-6 border-t border-slate-100 pt-5 text-center text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">
                    Need an account?{' '}
                    <Link href={route('register')} className="font-semibold text-indigo-600 hover:underline dark:text-indigo-400">
                        Create one
                    </Link>
                </p>
            </div>

            <p className="mt-6 text-center text-xs text-slate-400">Protected administrator access</p>
        </AdminLoginLayout>
    );
}
