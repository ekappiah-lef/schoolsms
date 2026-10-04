import * as DialogPrimitive from '@radix-ui/react-dialog';
import * as AlertPrimitive from '@radix-ui/react-alert-dialog';
import { X } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from './button';

const overlay = 'fixed inset-0 z-50 bg-slate-900/40 data-[state=open]:animate-fade-in';

/** Centered modal. Keep for short, focused tasks; use a page for long forms. */
export function Modal({ open, onOpenChange, title, description, children, footer, className, trigger }) {
    return (
        <DialogPrimitive.Root open={open} onOpenChange={onOpenChange}>
            {trigger && <DialogPrimitive.Trigger asChild>{trigger}</DialogPrimitive.Trigger>}
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className={overlay} />
                <DialogPrimitive.Content
                    className={cn(
                        'fixed left-1/2 top-[12vh] z-50 w-[calc(100%-2rem)] max-w-lg -translate-x-1/2 rounded-xl border border-border bg-surface shadow-lg data-[state=open]:animate-pop-in',
                        className,
                    )}
                >
                    <div className="flex items-start justify-between gap-4 border-b border-border px-5 py-4">
                        <div>
                            <DialogPrimitive.Title className="text-md font-semibold">{title}</DialogPrimitive.Title>
                            {description && (
                                <DialogPrimitive.Description className="mt-0.5 text-sm text-fg-muted">{description}</DialogPrimitive.Description>
                            )}
                        </div>
                        <DialogPrimitive.Close asChild>
                            <Button variant="ghost" size="icon-sm" aria-label="Close">
                                <X />
                            </Button>
                        </DialogPrimitive.Close>
                    </div>
                    <div className="px-5 py-4">{children}</div>
                    {footer && <div className="flex justify-end gap-2 border-t border-border bg-muted/60 px-5 py-3">{footer}</div>}
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}

/** Side drawer, used for the mobile navigation and quick detail views. */
export function Sheet({ open, onOpenChange, side = 'right', title, description, children, className, hideTitle }) {
    return (
        <DialogPrimitive.Root open={open} onOpenChange={onOpenChange}>
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className={overlay} />
                <DialogPrimitive.Content
                    className={cn(
                        'fixed inset-y-0 z-50 flex w-[88vw] max-w-sm flex-col bg-surface shadow-lg outline-none',
                        side === 'left'
                            ? 'left-0 border-r border-border data-[state=open]:animate-slide-in-left'
                            : 'right-0 border-l border-border data-[state=open]:animate-slide-in-right',
                        className,
                    )}
                >
                    <DialogPrimitive.Title className={hideTitle ? 'sr-only' : 'border-b border-border px-5 py-4 text-md font-semibold'}>
                        {title}
                    </DialogPrimitive.Title>
                    {description ? (
                        <DialogPrimitive.Description className="sr-only">{description}</DialogPrimitive.Description>
                    ) : (
                        <DialogPrimitive.Description className="sr-only">{title}</DialogPrimitive.Description>
                    )}
                    {children}
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}

/** Confirmation for destructive or irreversible actions. */
export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel = 'Confirm',
    tone = 'danger',
    loading = false,
    onConfirm,
}) {
    return (
        <AlertPrimitive.Root open={open} onOpenChange={onOpenChange}>
            <AlertPrimitive.Portal>
                <AlertPrimitive.Overlay className={overlay} />
                <AlertPrimitive.Content className="fixed left-1/2 top-[18vh] z-50 w-[calc(100%-2rem)] max-w-md -translate-x-1/2 rounded-xl border border-border bg-surface p-5 shadow-lg data-[state=open]:animate-pop-in">
                    <AlertPrimitive.Title className="text-md font-semibold">{title}</AlertPrimitive.Title>
                    <AlertPrimitive.Description className="mt-1.5 text-base text-fg-muted">{description}</AlertPrimitive.Description>
                    <div className="mt-5 flex justify-end gap-2">
                        <AlertPrimitive.Cancel asChild>
                            <Button variant="secondary" disabled={loading}>
                                Cancel
                            </Button>
                        </AlertPrimitive.Cancel>
                        <Button
                            variant={tone === 'danger' ? 'danger' : 'primary'}
                            loading={loading}
                            onClick={(e) => {
                                e.preventDefault();
                                onConfirm?.();
                            }}
                        >
                            {confirmLabel}
                        </Button>
                    </div>
                </AlertPrimitive.Content>
            </AlertPrimitive.Portal>
        </AlertPrimitive.Root>
    );
}
