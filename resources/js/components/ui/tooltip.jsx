import * as TooltipPrimitive from '@radix-ui/react-tooltip';

export const TooltipProvider = TooltipPrimitive.Provider;

export function Tooltip({ content, children, side = 'top', disabled = false, ...props }) {
    if (disabled || !content) return children;
    return (
        <TooltipPrimitive.Root delayDuration={250}>
            <TooltipPrimitive.Trigger asChild>{children}</TooltipPrimitive.Trigger>
            <TooltipPrimitive.Portal>
                <TooltipPrimitive.Content
                    side={side}
                    sideOffset={6}
                    className="z-50 max-w-xs rounded-md bg-slate-900 px-2 py-1 text-xs font-medium text-white shadow-md data-[state=delayed-open]:animate-fade-in"
                    {...props}
                >
                    {content}
                </TooltipPrimitive.Content>
            </TooltipPrimitive.Portal>
        </TooltipPrimitive.Root>
    );
}
