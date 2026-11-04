import CountryCodeSelect from '@/components/country-code-select';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLoginLayout from '@/layouts/auth/admin-login-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Eye, EyeOff, KeyRound, LoaderCircle, LockKeyhole, Mail, Smartphone } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

type IdentifierType = 'email' | 'mobile';
type LoginMethod = 'password' | 'otp';
type LoginStep = 'identifier' | 'method' | LoginMethod;
type LoginResolution = { password: boolean; otp: boolean; otpSent?: boolean };
type LoginForm = {
    loginType: IdentifierType;
    email: string;
    countryCode: string;
    mobileNumber: string;
    password: string;
    code: string;
    remember: boolean;
    [key: string]: string | boolean;
};
type LoginProps = {
    status?: string;
    canResetPassword: boolean;
    loginResolution?: LoginResolution;
};

export default function Login({ status, canResetPassword }: LoginProps) {
    const [step, setStep] = useState<LoginStep>('identifier');
    const [methods, setMethods] = useState<LoginResolution>({ password: false, otp: false });
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

    const resolveIdentifier: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('login.resolve'), {
            preserveScroll: true,
            onSuccess: (page) => {
                const resolution = page.props.loginResolution as LoginResolution | undefined;
                if (!resolution) return;
                setMethods(resolution);
                if (resolution.password && resolution.otp) setStep('method');
                else if (resolution.otp) {
                    setOtpSent(resolution.otpSent === true);
                    setData('code', '');
                    setStep('otp');
                } else setStep('password');
            },
        });
    };

    const submitPassword: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    const requestOtp = (showOtpStep = false) => {
        clearErrors();
        post(route('login.otp.request'), {
            preserveScroll: true,
            onSuccess: () => {
                setOtpSent(true);
                setData('code', '');
                if (showOtpStep) setStep('otp');
            },
        });
    };

    const verifyOtp: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('login.otp.verify'), { preserveScroll: true });
    };

    const startOver = () => {
        setStep('identifier');
        setMethods({ password: false, otp: false });
        setOtpSent(false);
        reset('password', 'code');
        clearErrors();
    };

    const identifier = data.loginType === 'email' ? data.email : `${data.countryCode} ${data.mobileNumber}`;
    const inputClass = 'h-11 rounded-xl border-slate-300 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20 dark:border-slate-700';
    const choiceClass = (active: boolean) =>
        `flex h-10 flex-1 items-center justify-center gap-2 rounded-lg text-sm font-semibold transition ${active ? 'bg-white text-indigo-700 shadow-sm dark:bg-slate-800 dark:text-indigo-300' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400'}`;

    return (
        <AdminLoginLayout>
            <Head title="Login" />
            <div className="mb-7">
                <p className="text-xs font-semibold tracking-[0.18em] text-indigo-600 uppercase dark:text-indigo-400">Textile Design Hub</p>
                <h1 className="mt-2 text-3xl font-bold tracking-tight text-slate-950 dark:text-white">Welcome back</h1>
                <p className="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">
                    {step === 'identifier' ? 'Enter your email or mobile number to continue.' : `Continue as ${identifier}.`}
                </p>
            </div>

            <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/50 sm:p-8 dark:border-slate-800 dark:bg-slate-900 dark:shadow-black/20">
                {step !== 'identifier' ? (
                    <button
                        type="button"
                        onClick={startOver}
                        className="mb-5 flex items-center gap-2 text-sm font-semibold text-indigo-600 hover:underline"
                    >
                        <ArrowLeft className="size-4" /> Change email or mobile
                    </button>
                ) : null}

                {status ? (
                    <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                        {status}
                    </div>
                ) : null}

                {step === 'identifier' ? (
                    <form className="space-y-5" onSubmit={resolveIdentifier}>
                        <div className="flex rounded-xl bg-slate-100 p-1 dark:bg-slate-950">
                            <button
                                type="button"
                                className={choiceClass(data.loginType === 'email')}
                                onClick={() => {
                                    setData('loginType', 'email');
                                    clearErrors();
                                }}
                            >
                                <Mail className="size-4" /> Email
                            </button>
                            <button
                                type="button"
                                className={choiceClass(data.loginType === 'mobile')}
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
                        <Button
                            type="submit"
                            className="h-11 w-full rounded-xl bg-indigo-600 font-semibold hover:bg-indigo-700"
                            disabled={processing}
                        >
                            {processing ? <LoaderCircle className="size-4 animate-spin" /> : null}
                            {processing ? 'Checking...' : 'Continue'}
                        </Button>
                    </form>
                ) : null}

                {step === 'method' ? (
                    <div className="space-y-4">
                        <p className="text-sm font-medium text-slate-700 dark:text-slate-200">Choose how you want to sign in.</p>
                        <button
                            type="button"
                            onClick={() => setStep('password')}
                            className="flex w-full items-center gap-4 rounded-xl border border-slate-200 p-4 text-left transition hover:border-indigo-400 hover:bg-indigo-50 dark:border-slate-700 dark:hover:bg-indigo-950/30"
                        >
                            <span className="rounded-lg bg-indigo-100 p-2 text-indigo-600">
                                <LockKeyhole className="size-5" />
                            </span>
                            <span>
                                <span className="block font-semibold">Use password</span>
                                <span className="text-sm text-slate-500">Enter your account password.</span>
                            </span>
                        </button>
                        <button
                            type="button"
                            onClick={() => requestOtp(true)}
                            disabled={processing}
                            className="flex w-full items-center gap-4 rounded-xl border border-slate-200 p-4 text-left transition hover:border-indigo-400 hover:bg-indigo-50 dark:border-slate-700 dark:hover:bg-indigo-950/30"
                        >
                            <span className="rounded-lg bg-indigo-100 p-2 text-indigo-600">
                                <KeyRound className="size-5" />
                            </span>
                            <span>
                                <span className="block font-semibold">Use mobile OTP</span>
                                <span className="text-sm text-slate-500">Receive a one-time code on your phone.</span>
                            </span>
                        </button>
                    </div>
                ) : null}

                {step === 'password' ? (
                    <form className="space-y-5" onSubmit={submitPassword}>
                        <div className="grid gap-2">
                            <div className="flex items-center justify-between gap-4">
                                <Label htmlFor="password">Password</Label>
                                {canResetPassword && data.loginType === 'email' ? (
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
                                    autoFocus
                                    autoComplete="current-password"
                                    value={data.password}
                                    onChange={(event) => setData('password', event.target.value)}
                                    className={`${inputClass} pr-11`}
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword((visible) => !visible)}
                                    className="absolute top-1/2 right-3 -translate-y-1/2 p-1 text-slate-400 hover:text-indigo-600"
                                    aria-label={showPassword ? 'Hide password' : 'Show password'}
                                >
                                    {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                </button>
                            </div>
                            <InputError message={errors.password} />
                            <InputError message={data.loginType === 'email' ? errors.email : errors.mobileNumber} />
                        </div>
                        <Remember checked={data.remember} setChecked={(value) => setData('remember', value)} />
                        <SubmitButton processing={processing} label="Sign in" />
                    </form>
                ) : null}

                {step === 'otp' ? (
                    <form className="space-y-5" onSubmit={verifyOtp}>
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
                        {otpSent ? <SubmitButton processing={processing} label="Verify OTP" /> : null}
                        {otpSent ? (
                            <Button
                                type="button"
                                variant="outline"
                                className="h-11 w-full rounded-xl"
                                disabled={processing}
                                onClick={() => requestOtp()}
                            >
                                Resend OTP
                            </Button>
                        ) : null}
                        <InputError message={errors.mobileNumber} />
                        {methods.password ? (
                            <button
                                type="button"
                                onClick={() => setStep('password')}
                                className="w-full text-sm font-semibold text-indigo-600 hover:underline"
                            >
                                Use password instead
                            </button>
                        ) : null}
                    </form>
                ) : null}
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
