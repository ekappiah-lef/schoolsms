import { forwardRef } from 'react';
import * as CheckboxPrimitive from '@radix-ui/react-checkbox';
import { Check, Minus } from 'lucide-react';
import { cn } from '@/lib/utils';

export const Checkbox = forwardRef(function Checkbox({ className, checked, ...props }, ref) {
    return (
        <CheckboxPrimitive.Root
            ref={ref}
            checked={checked}
            className={cn(
                'focus-ring peer inline-flex size-4 shrink-0 items-center justify-center rounded-[4px] border border-border-strong bg-surface transition-colors hover:border-fg-subtle data-[state=checked]:border-primary data-[state=indeterminate]:border-primary data-[state=checked]:bg-primary data-[state=indeterminate]:bg-primary',
                className,
            )}
            {...props}
        >
            <CheckboxPrimitive.Indicator className="text-white">
                {checked === 'indeterminate' ? <Minus className="size-3" strokeWidth={3} /> : <Check className="size-3" strokeWidth={3} />}
            </CheckboxPrimitive.Indicator>
        </CheckboxPrimitive.Root>
    );
});
