import { forwardRef } from 'react';
import * as Menu from '@radix-ui/react-dropdown-menu';
import { cn } from '@/lib/utils';

export const DropdownMenu = Menu.Root;
export const DropdownMenuTrigger = Menu.Trigger;
export const DropdownMenuGroup = Menu.Group;

export const DropdownMenuContent = forwardRef(function DropdownMenuContent(
    { className, sideOffset = 4, align = 'end', ...props },
    ref,
) {
    return (
        <Menu.Portal>
            <Menu.Content
                ref={ref}
                sideOffset={sideOffset}
                align={align}
                className={cn(
                    'z-50 min-w-[11rem] overflow-hidden rounded-lg border border-border bg-surface p-1 text-sm text-fg shadow-lg data-[state=open]:animate-pop-in',
                    className,
                )}
                {...props}
            />
        </Menu.Portal>
    );
});

export const DropdownMenuItem = forwardRef(function DropdownMenuItem({ className, destructive, inset, ...props }, ref) {
    return (
        <Menu.Item
            ref={ref}
            className={cn(
                'relative flex h-8 cursor-default select-none items-center gap-2 rounded-md px-2 outline-none transition-colors data-[disabled]:pointer-events-none data-[disabled]:opacity-50 [&_svg]:size-4 [&_svg]:shrink-0',
                destructive
                    ? 'text-danger-fg data-[highlighted]:bg-danger-soft [&_svg]:text-danger-fg'
                    : 'data-[highlighted]:bg-subtle [&_svg]:text-fg-subtle data-[highlighted]:[&_svg]:text-fg-muted',
                inset && 'pl-8',
                className,
            )}
            {...props}
        />
    );
});

export function DropdownMenuLabel({ className, ...props }) {
    return <Menu.Label className={cn('px-2 pb-1 pt-1.5 text-2xs font-semibold uppercase text-fg-subtle', className)} {...props} />;
}

export function DropdownMenuSeparator({ className, ...props }) {
    return <Menu.Separator className={cn('-mx-1 my-1 h-px bg-border', className)} {...props} />;
}

export const DropdownMenuCheckboxItem = forwardRef(function DropdownMenuCheckboxItem({ className, children, ...props }, ref) {
    return (
        <Menu.CheckboxItem
            ref={ref}
            className={cn(
                'relative flex h-8 cursor-default select-none items-center gap-2 rounded-md pl-8 pr-2 outline-none data-[highlighted]:bg-subtle',
                className,
            )}
            {...props}
        >
            <span className="absolute left-2 flex size-4 items-center justify-center rounded-sm border border-border-strong bg-surface data-[state=checked]:border-primary">
                <Menu.ItemIndicator className="size-2 rounded-[2px] bg-primary" />
            </span>
            {children}
        </Menu.CheckboxItem>
    );
});
