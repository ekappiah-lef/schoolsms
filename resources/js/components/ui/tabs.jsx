import { forwardRef } from 'react';
import * as TabsPrimitive from '@radix-ui/react-tabs';
import { cn } from '@/lib/utils';

export const Tabs = TabsPrimitive.Root;
export const TabsContent = forwardRef(function TabsContent({ className, ...props }, ref) {
    return <TabsPrimitive.Content ref={ref} className={cn('outline-none', className)} {...props} />;
});

/** Underline tabs — used for page-level sections such as the student profile. */
export function TabsList({ className, ...props }) {
    return (
        <TabsPrimitive.List
            className={cn('scrollbar-thin -mb-px flex gap-5 overflow-x-auto border-b border-border', className)}
            {...props}
        />
    );
}

export function TabsTrigger({ className, count, children, ...props }) {
    return (
        <TabsPrimitive.Trigger
            className={cn(
                'focus-ring relative inline-flex h-10 shrink-0 items-center gap-1.5 whitespace-nowrap border-b-2 border-transparent text-base font-medium text-fg-muted transition-colors hover:text-fg data-[state=active]:border-primary data-[state=active]:text-fg [&_svg]:size-4',
                className,
            )}
            {...props}
        >
            {children}
            {count !== undefined && count !== null && (
                <span className="tabular rounded-full bg-subtle px-1.5 text-2xs font-semibold text-fg-muted">{count}</span>
            )}
        </TabsPrimitive.Trigger>
    );
}

/** Segmented control — used for small in-panel switches (as in the timetable design). */
export function Segmented({ value, onChange, options, className, size = 'md' }) {
    return (
        <div role="tablist" className={cn('inline-flex rounded-md border border-border bg-subtle p-0.5', className)}>
            {options.map((opt) => {
                const active = opt.value === value;
                return (
                    <button
                        key={opt.value}
                        type="button"
                        role="tab"
                        aria-selected={active}
                        onClick={() => onChange(opt.value)}
                        className={cn(
                            'focus-ring inline-flex items-center gap-1.5 rounded-[5px] px-2.5 font-medium transition-colors [&_svg]:size-3.5',
                            size === 'sm' ? 'h-6 text-xs' : 'h-7 text-sm',
                            active ? 'bg-surface text-fg shadow-sm' : 'text-fg-muted hover:text-fg',
                        )}
                    >
                        {opt.icon}
                        {opt.label}
                    </button>
                );
            })}
        </div>
    );
}
