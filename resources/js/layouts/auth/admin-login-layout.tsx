import AppLogoIcon from '@/components/app-logo-icon';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Layers3, ShieldCheck, Sparkles } from 'lucide-react';
import { type ReactNode } from 'react';

type Props = {
    children: ReactNode;
};

export default function AdminLoginLayout({ children }: Props) {
    const { siteBranding } = usePage<SharedData>().props;
    const name = siteBranding?.name ?? 'DesignHub Admin';
    const logoUrl = siteBranding?.logoUrl;

    return (
        <main className="min-h-svh bg-slate-50 text-slate-950 dark:bg-slate-950 dark:text-white">
            <div className="grid min-h-svh lg:grid-cols-[1.08fr_0.92fr]">
                <section className="relative hidden overflow-hidden bg-slate-950 p-10 text-white lg:flex lg:flex-col xl:p-14">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_15%_15%,rgba(99,102,241,0.3),transparent_34%),radial-gradient(circle_at_85%_78%,rgba(14,165,233,0.2),transparent_30%)]" />
                    <div className="absolute inset-0 [background-image:linear-gradient(rgba(255,255,255,.7)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.7)_1px,transparent_1px)] [background-size:48px_48px] opacity-[0.08]" />

                    <Link href={route('home')} className="relative z-10 flex w-fit items-center gap-3">
                        {logoUrl ? (
                            <img src={logoUrl} alt="" className="size-11 rounded-xl bg-white/10 object-contain p-1.5 ring-1 ring-white/15" />
                        ) : (
                            <span className="flex size-11 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-500 shadow-lg shadow-indigo-950/40">
                                <AppLogoIcon className="size-6 fill-current" />
                            </span>
                        )}
                        <span className="text-lg font-semibold tracking-tight">{name}</span>
                    </Link>

                    <div className="relative z-10 my-auto max-w-xl py-16">
                        <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-indigo-300/20 bg-indigo-400/10 px-3 py-1.5 text-xs font-semibold text-indigo-100">
                            <Sparkles className="size-3.5" />
                            Design marketplace administration
                        </div>
                        <h1 className="max-w-lg text-4xl leading-[1.08] font-bold tracking-tight xl:text-5xl">
                            Keep every design operation in one clear workspace.
                        </h1>
                        <p className="mt-6 max-w-lg text-base leading-7 text-slate-300">
                            Manage the catalog, creators, purchases and access permissions from a secure dashboard built for your team.
                        </p>

                        <div className="mt-10 grid max-w-lg gap-3 sm:grid-cols-2">
                            <div className="rounded-2xl border border-white/10 bg-white/[0.06] p-4 backdrop-blur-sm">
                                <ShieldCheck className="mb-3 size-5 text-indigo-300" />
                                <p className="text-sm font-semibold">Role-based access</p>
                                <p className="mt-1 text-xs leading-5 text-slate-400">Only approved team members can access each module.</p>
                            </div>
                            <div className="rounded-2xl border border-white/10 bg-white/[0.06] p-4 backdrop-blur-sm">
                                <Layers3 className="mb-3 size-5 text-sky-300" />
                                <p className="text-sm font-semibold">Connected catalog</p>
                                <p className="mt-1 text-xs leading-5 text-slate-400">Machines, categories and designs stay organized.</p>
                            </div>
                        </div>
                    </div>

                    <p className="relative z-10 text-xs text-slate-500">Secure access for authorized DesignHub administrators.</p>
                </section>

                <section className="relative flex items-center justify-center px-5 py-10 sm:px-8 lg:px-12">
                    <div className="absolute inset-x-0 top-0 h-48 bg-gradient-to-b from-indigo-100/60 to-transparent lg:hidden dark:from-indigo-950/20" />
                    <div className="relative w-full max-w-md">
                        <Link href={route('home')} className="mb-8 flex items-center justify-center gap-3 lg:hidden">
                            {logoUrl ? (
                                <img src={logoUrl} alt="" className="size-10 rounded-xl object-contain" />
                            ) : (
                                <span className="flex size-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-violet-500 text-white shadow-lg shadow-indigo-500/20">
                                    <AppLogoIcon className="size-5 fill-current" />
                                </span>
                            )}
                            <span className="font-semibold tracking-tight">{name}</span>
                        </Link>

                        {children}
                    </div>
                </section>
            </div>
        </main>
    );
}
