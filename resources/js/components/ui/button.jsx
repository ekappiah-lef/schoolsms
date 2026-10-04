import { forwardRef } from 'react';
import { Slot } from '@radix-ui/react-slot';
import { cva } from 'class-variance-authority';
import { LoaderCircle } from 'lucide-react';
import { cn } from '@/lib/utils';

export const buttonVariants = cva(
    'focus-ring inline-flex shrink-0 select-none items-center justify-center gap-1.5 whitespace-nowrap rounded-md border text-sm font-medium transition-colors disabled:pointer-events-none disabled:opacity-50 [&_svg]:size-4 [&_svg]:shrink-0',
    {
        variants: {
            variant: {
                primary: 'border-primary-hover bg-primary text-primary-fg shadow-btn hover:bg-primary-hover',
                secondary: 'border-border bg-surface text-fg shadow-xs hover:border-border-strong hover:bg-muted',
                ghost: 'border-transparent text-fg-muted hover:bg-subtle hover:text-fg',
                danger: 'border-danger bg-danger text-white shadow-btn hover:bg-danger-fg',
                'danger-ghost': 'border-transparent text-danger-fg hover:bg-danger-soft',
                link: 'h-auto border-transparent px-0 text-primary hover:underline',
            },
            size: {
                xs: 'h-7 px-2 text-xs',
                sm: 'h-8 px-2.5',
                md: 'h-9 px-3.5',
                lg: 'h-10 px-4 text-base',
                icon: 'size-8 px-0',
                'icon-sm': 'size-7 px-0',
            },
        },
        defaultVariants: { variant: 'secondary', size: 'md' },
    },
);

export const Button = forwardRef(function Button(
    { className, variant, size, asChild = false, loading = false, children, disabled, ...props },
    ref,
) {
    const Comp = asChild ? Slot : 'button';
    return (
        <Comp
            ref={ref}
            className={cn(buttonVariants({ variant, size }), className)}
            disabled={disabled || loading}
            {...(Comp === 'button' && !props.type ? { type: 'button' } : {})}
            {...props}
        >
            {asChild ? (
                children
            ) : (
                <>
                    {loading && <LoaderCircle className="animate-spin" />}
                    {children}
                </>
            )}
        </Comp>
    );
});
