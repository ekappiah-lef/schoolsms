import { cva } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const badgeVariants = cva(
    'inline-flex h-5 shrink-0 items-center gap-1 whitespace-nowrap rounded-full border px-2 text-2xs font-semibold tracking-normal',
    {
        variants: {
            tone: {
                neutral: 'border-border bg-subtle text-fg-muted',
                primary: 'border-primary/20 bg-primary-soft text-primary-hover',
                success: 'border-success/30 bg-success-soft text-success-fg',
                warning: 'border-warning/30 bg-warning-soft text-warning-fg',
                danger: 'border-danger/25 bg-danger-soft text-danger-fg',
                info: 'border-info/30 bg-info-soft text-info-fg',
                outline: 'border-border bg-surface text-fg-muted',
            },
            shape: {
                pill: 'rounded-full',
                tag: 'rounded-sm',
            },
        },
        defaultVariants: { tone: 'neutral', shape: 'pill' },
    },
);

export function Badge({ className, tone, shape, dot = false, children, ...props }) {
    return (
        <span className={cn(badgeVariants({ tone, shape }), className)} {...props}>
            {dot && <span className="size-1.5 rounded-full bg-current" />}
            {children}
        </span>
    );
}
