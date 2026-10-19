import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export function FlashToast() {
    const { flash } = usePage<SharedData & { flash?: { success?: string | null } }>().props;
    const [message, setMessage] = useState<string | null>(null);

    useEffect(() => {
        if (flash?.success) {
            setMessage(flash.success);
            const timer = window.setTimeout(() => setMessage(null), 4000);
            return () => window.clearTimeout(timer);
        }
    }, [flash?.success]);

    if (!message) {
        return null;
    }

    return (
        <div className="fixed top-4 right-4 z-50 rounded-lg border border-indigo-200 bg-white px-4 py-3 text-sm font-medium text-indigo-900 shadow-lg dark:border-indigo-900 dark:bg-slate-900 dark:text-indigo-100">
            {message}
        </div>
    );
}
