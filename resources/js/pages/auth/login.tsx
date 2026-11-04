import CountryCodeSelect from '@/components/country-code-select';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLoginLayout from '@/layouts/auth/admin-login-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Eye, EyeOff, KeyRound, LoaderCircle, LockKeyhole, Mail, Smartphone } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

type LoginMode = 'password' | 'otp';
type PasswordIdentifier = 'email' | 'mobile';
type LoginForm = {
    loginType: PasswordIdentifier;
    email: string;
    countryCode: string;
    mobileNumber: string;
    password: string;
    code: string;
    remember: boolean;
    [key: string]: string | boolean;
};
type LoginProps = { status?: string; canResetPassword: boolean };

export default function Login({ status, canResetPassword }: LoginProps) {
    const [mode, setMode] = useState<LoginMode>('password');
    const [otpSent, setOtpSent] = useState(false);
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<LoginForm>({
        loginType: 'email',
        email: '',
        countryCode: '+91',
        mobileNumber: '',
        password: '',
        code: '',
        remember: false,
    });

    const selectMode = (nextMode: LoginMode) => {
        setMode(nextMode);
        setOtpSent(false);
        clearErrors();
    };
    const submitPassword: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };
    const requestOtp = () => {
        clearErrors();
        post(route('login.otp.request'), {
            preserveScroll: true,
            onSuccess: () => {
                setOtpSent(true);
                setData('code', '');
            },
        });
    };
    const verifyOtp: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('login.otp.verify'), { preserveScroll: true });
    };
    const tabClass = (active: boolean) =>
        `flex h-10 flex-1 items-center justify-center gap-2 rounded-lg text-sm font-semibold transition ${active ? 'bg-white text-indigo-700 shadow-sm dark:bg-slate-800 dark:text-indigo-300' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400'}`;
    const inputClass = 'h-11 rounded-xl border-slate-300 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20 dark:border-slate-700';

    return (
        <AdminLoginLayout>
            <Head title="Login" />
            <div className="mb-7">
                <p className="text-xs font-semibold tracking-[0.18em] text-indigo-600 uppercase dark:text-indigo-400">Textile Design Hub</p>
                <h1 className="mt-2 text-3xl font-bold tracking-tight text-slate-950 dark:text-white">Welcome back</h1>
                <p className="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Sign in with your password or a mobile OTP.</p>
            </div>

            <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/50 sm:p-8 dark:border-slate-800 dark:bg-slate-900 dark:shadow-black/20">
                <div className="mb-6 flex rounded-xl bg-slate-100 p-1 dark:bg-slate-950">
                    <button type="button" className={tabClass(mode === 'password')} onClick={() => selectMode('password')}>
                        <LockKeyhole className="size-4" /> Password
                    </button>
                    <button type="button" className={tabClass(mode === 'otp')} onClick={() => selectMode('otp')}>
                        <KeyRound className="size-4" /> OTP
                    </button>
                </div>

                {status ? (
                    <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                        {status}
                    </div>
                ) : null}

                {mode === 'password' ? (
                    <form className="space-y-5" onSubmit={submitPassword}>
                        <div className="flex rounded-lg border border-slate-200 p-1 dark:border-slate-700">
                            <button
                                type="button"
                                className={tabClass(data.loginType === 'email')}
                                onClick={() => {
                                    setData('loginType', 'email');
                                    clearErrors();
                                }}
                            >
                                <Mail className="size-4" /> Email
                            </button>
                            <button
                                type="button"
                                className={tabClass(data.loginType === 'mobile')}
                                onClick={() => {
                                    setData('loginType', 'mobile');
                                    clearErrors();
                                }}
                            >
                                <Smartphone className="size-4" /> Mobile
                            </button>
                        </div>

                        {data.loginType === 'email' ? (
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    autoFocus
                                    autoComplete="email"
                                    value={data.email}
                                    onChange={(event) => setData('email', event.target.value)}
                                    placeholder="admin@example.com"
                                    className={inputClass}
                                />
                                <InputError message={errors.email} />
                            </div>
                        ) : (
                            <MobileFields
                                countryCode={data.countryCode}
                                mobileNumber={data.mobileNumber}
                                setCountryCode={(value) => setData('countryCode', value)}
                                setMobileNumber={(value) => setData('mobileNumber', value)}
                                error={errors.mobileNumber}
                                inputClass={inputClass}
                            />
                        )}

                        <div className="grid gap-2">
                            <div className="flex items-center justify-between gap-4">
                                <Label htmlFor="password">Password</Label>
                                {canResetPassword ? (
                                    <Link href={route('password.request')} className="text-xs font-semibold text-indigo-600 hover:underline">
                                        Forgot password?
                                    </Link>
                                ) : null}
                            </div>
                            <div className="relative">
                                <Input
                                    id="password"
                                    type={showPassword ? 'text' : 'password'}
                                    required
                                    autoComplete="current-password"
                                    value={data.password}
                                    onChange={(event) => setData('password', event.target.value)}
                                    className={`${inputClass} pr-11`}
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword((visible) => !visible)}
                                    className="absolute top-1/2 right-3 -translate-y-1/2 rounded-md p-1 text-slate-400 hover:text-indigo-600"
                                    aria-label={showPassword ? 'Hide password' : 'Show password'}
                                >
                                    {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                </button>
                            </div>
                            <InputError message={errors.password} />
                        </div>
                        <Remember checked={data.remember} setChecked={(value) => setData('remember', value)} />
                        <SubmitButton processing={processing} label="Sign in with password" />
                    </form>
                ) : (
                    <form className="space-y-5" onSubmit={verifyOtp}>
                        <MobileFields
                            countryCode={data.countryCode}
                            mobileNumber={data.mobileNumber}
                            setCountryCode={(value) => setData('countryCode', value)}
                            setMobileNumber={(value) => {
                                setData('mobileNumber', value);
                                setOtpSent(false);
                            }}
                            error={errors.mobileNumber}
                            inputClass={inputClass}
                        />
                        {otpSent ? (
                            <div className="grid gap-2">
                                <Label htmlFor="code">4-digit OTP</Label>
                                <Input
                                    id="code"
                                    inputMode="numeric"
                                    maxLength={4}
                                    required
                                    autoFocus
                                    value={data.code}
                                    onChange={(event) => setData('code', event.target.value.replace(/\D/g, ''))}
                                    placeholder="0000"
                                    className={`${inputClass} text-center text-lg tracking-[0.4em]`}
                                />
                                <InputError message={errors.code} />
                            </div>
                        ) : null}
                        <Remember checked={data.remember} setChecked={(value) => setData('remember', value)} />
                        {otpSent ? (
                            <>
                                <SubmitButton processing={processing} label="Verify OTP and sign in" />
                                <Button type="button" variant="outline" className="h-11 w-full rounded-xl" disabled={processing} onClick={requestOtp}>
                                    Resend OTP
                                </Button>
                            </>
                        ) : (
                            <Button
                                type="button"
                                className="h-11 w-full rounded-xl bg-indigo-600 font-semibold hover:bg-indigo-700"
                                disabled={processing}
                                onClick={requestOtp}
                            >
                                {processing ? <LoaderCircle className="size-4 animate-spin" /> : <KeyRound className="size-4" />}
                                {processing ? 'Sending OTP...' : 'Send OTP'}
                            </Button>
                        )}
                    </form>
                )}

                <p className="mt-6 border-t border-slate-100 pt-5 text-center text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">
                    Need an account?{' '}
                    <Link href={route('register')} className="font-semibold text-indigo-600 hover:underline">
                        Create one
                    </Link>
                </p>
            </div>
        </AdminLoginLayout>
    );
}

function MobileFields({
    countryCode,
    mobileNumber,
    setCountryCode,
    setMobileNumber,
    error,
    inputClass,
}: {
    countryCode: string;
    mobileNumber: string;
    setCountryCode: (value: string) => void;
    setMobileNumber: (value: string) => void;
    error?: string;
    inputClass: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor="mobileNumber">Mobile number</Label>
            <div className="grid grid-cols-[145px_1fr] gap-2">
                <CountryCodeSelect value={countryCode} onValueChange={setCountryCode} className={inputClass} />
                <Input
                    id="mobileNumber"
                    type="tel"
                    inputMode="tel"
                    required
                    autoComplete="tel-national"
                    value={mobileNumber}
                    onChange={(event) => setMobileNumber(event.target.value)}
                    placeholder="9876543210"
                    className={inputClass}
                />
            </div>
            <InputError message={error} />
        </div>
    );
}

function Remember({ checked, setChecked }: { checked: boolean; setChecked: (value: boolean) => void }) {
    return (
        <div className="flex items-center gap-2.5">
            <Checkbox id="remember" checked={checked} onCheckedChange={(value) => setChecked(value === true)} />
            <Label htmlFor="remember" className="cursor-pointer text-sm font-normal text-slate-600">
                Keep me signed in
            </Label>
        </div>
    );
}

function SubmitButton({ processing, label }: { processing: boolean; label: string }) {
    return (
        <Button type="submit" className="h-11 w-full rounded-xl bg-indigo-600 font-semibold hover:bg-indigo-700" disabled={processing}>
            {processing ? <LoaderCircle className="size-4 animate-spin" /> : <LockKeyhole className="size-4" />}
            {processing ? 'Signing in...' : label}
        </Button>
    );
}
