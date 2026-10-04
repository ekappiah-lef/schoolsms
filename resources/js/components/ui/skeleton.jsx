import { cn } from '@/lib/utils';

export function Skeleton({ className, ...props }) {
    return <div className={cn('animate-pulse rounded-md bg-subtle', className)} {...props} />;
}

/** Placeholder rows for a table while a partial reload is in flight. */
export function TableSkeleton({ rows = 6, columns = 5 }) {
    return (
        <div className="divide-y divide-border">
            {Array.from({ length: rows }).map((_, r) => (
                <div key={r} className="flex items-center gap-4 px-4 py-3">
                    {Array.from({ length: columns }).map((_, c) => (
                        <Skeleton key={c} className={cn('h-3.5', c === 0 ? 'w-1/4' : 'flex-1')} />
                    ))}
                </div>
            ))}
        </div>
    );
}
