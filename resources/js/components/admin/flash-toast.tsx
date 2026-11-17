import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { CircleAlert, CircleCheck, Info, TriangleAlert, X } from 'lucide-react';
import { useEffect, useState } from 'react';

type ToastKind = 'success' | 'error' | 'warning' | 'info';
type ToastState = { kind: ToastKind; title: string; messages: string[] };

const styles: Record<ToastKind, { container: string; icon: string }> = {
    success: {
        container: 'border-emerald-300 bg-emerald-50 text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-100',
        icon: 'text-emerald-600 dark:text-emerald-300',
    },
    error: {
        container: 'border-red-300 bg-red-50 text-red-950 dark:border-red-800 dark:bg-red-950 dark:text-red-100',
        icon: 'text-red-600 dark:text-red-300',
    },
    warning: {
        container: 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100',
        icon: 'text-amber-600 dark:text-amber-300',
    },
    info: {
        container: 'border-sky-300 bg-sky-50 text-sky-950 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-100',
        icon: 'text-sky-600 dark:text-sky-300',
    },
};

const icons = {
    success: CircleCheck,
    error: CircleAlert,
    warning: TriangleAlert,
    info: Info,
};

export function FlashToast() {
    const { flash, errors } = usePage<SharedData>().props;
    const [toast, setToast] = useState<ToastState | null>(null);
    const validationMessages = [...new Set(Object.values(errors ?? {}).filter(Boolean))];
    const validationSignature = JSON.stringify(validationMessages);

    useEffect(() => {
        let nextToast: ToastState | null = null;

        if (validationMessages.length) {
            nextToast = {
                kind: 'error',
                title: validationMessages.length === 1 ? 'Please fix this error' : `Please fix ${validationMessages.length} errors`,
                messages: validationMessages.slice(0, 5),
            };
        } else if (flash?.error) {
            nextToast = { kind: 'error', title: 'Action failed', messages: [flash.error] };
        } else if (flash?.warning) {
            nextToast = { kind: 'warning', title: 'Attention required', messages: [flash.warning] };
        } else if (flash?.success) {
            nextToast = { kind: 'success', title: 'Success', messages: [flash.success] };
        } else if (flash?.info) {
            nextToast = { kind: 'info', title: 'Information', messages: [flash.info] };
        }

        if (!nextToast) return;

        setToast(nextToast);
        // validationSignature keeps this effect stable when Inertia recreates the errors object.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [flash?.error, flash?.info, flash?.success, flash?.warning, validationSignature]);

    useEffect(() => {
        const removeSuccessListener = router.on('success', (event) => {
            const props = event.detail.page.props as SharedData;
            const pageErrors = [...new Set(Object.values(props.errors ?? {}).filter(Boolean))];
            let nextToast: ToastState | null = null;

            if (pageErrors.length) {
                nextToast = {
                    kind: 'error',
                    title: pageErrors.length === 1 ? 'Please fix this error' : `Please fix ${pageErrors.length} errors`,
                    messages: pageErrors.slice(0, 5),
                };
            } else if (props.flash?.error) {
                nextToast = { kind: 'error', title: 'Action failed', messages: [props.flash.error] };
            } else if (props.flash?.warning) {
                nextToast = { kind: 'warning', title: 'Attention required', messages: [props.flash.warning] };
            } else if (props.flash?.success) {
                nextToast = { kind: 'success', title: 'Success', messages: [props.flash.success] };
            } else if (props.flash?.info) {
                nextToast = { kind: 'info', title: 'Information', messages: [props.flash.info] };
            }

            if (nextToast) setToast(nextToast);
        });
        const removeInvalidListener = router.on('invalid', (event) => {
            const status = event.detail.response.status;
            const messages: Record<number, string> = {
                401: 'Your login has expired. Refresh the page and sign in again.',
                403: 'You do not have permission to perform this action.',
                409: 'This record conflicts with connected data. Review its connections and try again.',
                413: 'The selected files are too large for the server. Upload fewer or smaller files.',
                419: 'Your session has expired. Refresh the page and try again.',
                422: 'The submitted information could not be processed. Check the highlighted fields.',
                500: 'The server could not complete this request. Check the Laravel log for the latest exception.',
                503: 'The service is temporarily unavailable. Please try again shortly.',
            };

            setToast({
                kind: 'error',
                title: `Request failed (${status || 'network error'})`,
                messages: [messages[status] ?? 'The server returned an unexpected response. Please try again.'],
            });

            return false;
        });
        const removeExceptionListener = router.on('exception', () => {
            setToast({
                kind: 'error',
                title: 'Connection error',
                messages: ['The request could not reach the server. Check your connection and try again.'],
            });

            return false;
        });

        return () => {
            removeSuccessListener();
            removeInvalidListener();
            removeExceptionListener();
        };
    }, []);

    useEffect(() => {
        if (!toast) return;

        const timeout = toast.kind === 'success' ? 5000 : 10000;
        const timer = window.setTimeout(() => setToast(null), timeout);

        return () => window.clearTimeout(timer);
    }, [toast]);

    if (!toast) {
        return null;
    }

    const Icon = icons[toast.kind];
    const tone = styles[toast.kind];

    return (
        <div
            role="alert"
            aria-live="assertive"
            className={`fixed top-4 right-4 z-[100] w-[calc(100%-2rem)] max-w-md rounded-2xl border p-4 shadow-2xl ${tone.container}`}
        >
            <div className="flex items-start gap-3">
                <Icon className={`mt-0.5 size-5 shrink-0 ${tone.icon}`} />
                <div className="min-w-0 flex-1">
                    <p className="font-bold">{toast.title}</p>
                    {toast.messages.length === 1 ? (
                        <p className="mt-1 text-sm leading-5 opacity-90">{toast.messages[0]}</p>
                    ) : (
                        <ul className="mt-2 list-disc space-y-1 pl-5 text-sm leading-5 opacity-90">
                            {toast.messages.map((message) => (
                                <li key={message}>{message}</li>
                            ))}
                        </ul>
                    )}
                    {validationMessages.length > 5 ? (
                        <p className="mt-2 text-xs font-semibold opacity-75">
                            And {validationMessages.length - 5} more. Check the highlighted fields below.
                        </p>
                    ) : null}
                </div>
                <button
                    type="button"
                    onClick={() => setToast(null)}
                    className="rounded-lg p-1 opacity-60 transition hover:bg-black/5 hover:opacity-100 dark:hover:bg-white/10"
                    aria-label="Dismiss notification"
                >
                    <X className="size-4" />
                </button>
            </div>
        </div>
    );
}
