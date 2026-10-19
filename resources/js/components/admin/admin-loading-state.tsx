import { Skeleton } from '@/components/ui/skeleton';

export function AdminLoadingState({ rows = 5 }: { rows?: number }) {
    return (
        <div className="space-y-3 p-5" aria-label="Loading" aria-busy="true">
            {Array.from({ length: rows }).map((_, index) => (
                <div key={index} className="flex items-center gap-4 rounded-xl border border-slate-100 p-3 dark:border-slate-800">
                    <Skeleton className="size-10 rounded-xl" />
                    <div className="flex-1 space-y-2">
                        <Skeleton className="h-3.5 w-1/3" />
                        <Skeleton className="h-3 w-2/3" />
                    </div>
                    <Skeleton className="h-8 w-20 rounded-lg" />
                </div>
            ))}
        </div>
    );
}
