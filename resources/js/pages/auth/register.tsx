import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLoginLayout from '@/layouts/auth/admin-login-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Eye, EyeOff, LoaderCircle, LockKeyhole, Mail, UserRound, UserRoundPlus } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

type RegisterForm = {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    [key: string]: string;
};

export default function Register() {
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm<RegisterForm>({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AdminLoginLayout>
            <Head title="Create account" />

            <div className="mb-7">
                <p className="text-xs font-semibold tracking-[0.18em] text-indigo-600 uppercase dark:text-indigo-400">Admin portal</p>
                <h1 className="mt-2 text-3xl font-bold tracking-tight text-slate-950 dark:text-white">Create your account</h1>
                <p className="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">
                    Enter your details to request access to the DesignHub workspace.
                </p>
            </div>

            <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/50 sm:p-8 dark:border-slate-800 dark:bg-slate-900 dark:shadow-black/20">
                <form className="space-y-5" onSubmit={submit}>
                    <div className="grid gap-2">
                        <Label htmlFor="name" className="text-sm font-semibold text-slate-700 dark:text-slate-200">
                            Full name
                        </Label>
                        <div className="relative">
                            <UserRound className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <Input
                                id="name"
                                type="text"
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="name"
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                                disabled={processing}
                                placeholder="Your full name"
                                className="h-11 rounded-xl border-slate-300 pl-10 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20 dark:border-slate-700"
                            />
                        </div>
                        <InputError message={errors.name} />
                    </div>

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
                                tabIndex={2}
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

                    <div className="grid gap-2 sm:grid-cols-2 sm:gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="password" className="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                Password
                            </Label>
                            <div className="relative">
                                <LockKeyhole className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                <Input
                                    id="password"
                                    type={showPassword ? 'text' : 'password'}
                                    required
                                    tabIndex={3}
                                    autoComplete="new-password"
                                    value={data.password}
                                    onChange={(event) => setData('password', event.target.value)}
                                    disabled={processing}
                                    placeholder="Password"
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
                                    required
                                    tabIndex={4}
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
                        tabIndex={5}
                        disabled={processing}
                    >
                        {processing ? <LoaderCircle className="size-4 animate-spin" /> : <UserRoundPlus className="size-4" />}
                        {processing ? 'Creating account...' : 'Create account'}
                    </Button>
                </form>

                <p className="mt-6 border-t border-slate-100 pt-5 text-center text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">
                    Already have an account?{' '}
                    <Link href={route('login')} className="font-semibold text-indigo-600 hover:underline dark:text-indigo-400" tabIndex={6}>
                        Sign in
                    </Link>
                </p>
            </div>

            <p className="mt-6 text-center text-xs text-slate-400">Protected administrator registration</p>
        </AdminLoginLayout>
    );
}
