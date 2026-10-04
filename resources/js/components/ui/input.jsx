import { forwardRef } from 'react';
import { cn } from '@/lib/utils';

export const inputClass =
    // Stitch fields: borderless white with a soft shadow ring; focus shows a primary ring.
    'h-10 w-full rounded-lg border-0 bg-surface px-3.5 text-base text-fg shadow-field transition-shadow placeholder:text-fg-subtle hover:shadow-[0_1px_2px_0_rgb(19_27_46/0.06),0_0_0_1px_rgb(var(--border-strong))] focus-visible:shadow-[0_0_0_2px_rgb(var(--primary)/0.35)] focus-visible:outline-none disabled:cursor-not-allowed disabled:bg-muted disabled:text-fg-muted aria-[invalid=true]:shadow-[0_0_0_1.5px_rgb(var(--danger))]';

export const Input = forwardRef(function Input({ className, type = 'text', invalid, ...props }, ref) {
    return <input ref={ref} type={type} aria-invalid={invalid || undefined} className={cn(inputClass, className)} {...props} />;
});

export const Textarea = forwardRef(function Textarea({ className, invalid, ...props }, ref) {
    return <textarea ref={ref} aria-invalid={invalid || undefined} className={cn(inputClass, 'h-auto min-h-[76px] py-2', className)} {...props} />;
});
