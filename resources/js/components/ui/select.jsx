import { forwardRef, useMemo, useState } from 'react';
import * as SelectPrimitive from '@radix-ui/react-select';
import * as PopoverPrimitive from '@radix-ui/react-popover';
import { Command } from 'cmdk';
import { Check, ChevronDown, Plus, Search, X } from 'lucide-react';
import { cn } from '@/lib/utils';
import { inputClass } from './input';

const EMPTY = '__none__';

/**
 * Simple select for short option lists (gender, per-page, filters).
 * `options`: [{ value, label }]. Values are compared as strings.
 */
export const Select = forwardRef(function Select(
    { value, onChange, options = [], placeholder = 'Select…', clearable = false, clearLabel = 'Any', className, size = 'md', invalid, disabled, id, icon },
    ref,
) {
    const current = value === null || value === undefined || value === '' ? (clearable ? EMPTY : undefined) : String(value);
    return (
        <SelectPrimitive.Root
            value={current}
            onValueChange={(v) => onChange?.(v === EMPTY ? '' : v)}
            disabled={disabled}
        >
            <SelectPrimitive.Trigger
                ref={ref}
                id={id}
                aria-invalid={invalid || undefined}
                className={cn(
                    inputClass,
                    'flex items-center justify-between gap-2 text-left data-[placeholder]:text-fg-subtle [&>span]:truncate',
                    size === 'sm' && 'h-8 text-sm',
                    className,
                )}
            >
                <span className="flex min-w-0 items-center gap-2">
                    {icon}
                    <SelectPrimitive.Value placeholder={placeholder} />
                </span>
                <SelectPrimitive.Icon>
                    <ChevronDown className="size-4 text-fg-subtle" />
                </SelectPrimitive.Icon>
            </SelectPrimitive.Trigger>
            <SelectPrimitive.Portal>
                <SelectPrimitive.Content
                    position="popper"
                    sideOffset={4}
                    className="z-50 max-h-[min(20rem,var(--radix-select-content-available-height))] min-w-[var(--radix-select-trigger-width)] overflow-hidden rounded-lg border border-border bg-surface shadow-lg data-[state=open]:animate-pop-in"
                >
                    <SelectPrimitive.Viewport className="p-1">
                        {clearable && <Item value={EMPTY}>{clearLabel}</Item>}
                        {options.map((o) => (
                            <Item key={o.value} value={String(o.value)}>
                                {o.label}
                            </Item>
                        ))}
                    </SelectPrimitive.Viewport>
                </SelectPrimitive.Content>
            </SelectPrimitive.Portal>
        </SelectPrimitive.Root>
    );
});

function Item({ children, ...props }) {
    return (
        <SelectPrimitive.Item
            className="relative flex h-8 cursor-default select-none items-center rounded-md pl-2 pr-8 text-sm outline-none data-[highlighted]:bg-subtle data-[state=checked]:font-medium"
            {...props}
        >
            <SelectPrimitive.ItemText>{children}</SelectPrimitive.ItemText>
            <SelectPrimitive.ItemIndicator className="absolute right-2">
                <Check className="size-4 text-primary" />
            </SelectPrimitive.ItemIndicator>
        </SelectPrimitive.Item>
    );
}

/**
 * Searchable select for long lists (nationality, state, parent, class).
 * `options`: [{ value, label, hint? }].
 */
export function Combobox({
    value,
    onChange,
    options = [],
    placeholder = 'Select…',
    searchPlaceholder = 'Search…',
    emptyText = 'No matches',
    clearable = true,
    loading = false,
    disabled,
    invalid,
    id,
    className,
    creatable = false, // typing a name that is not listed offers "Add …"; the value becomes "new:Name"
}) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const selected = useMemo(() => {
        const v = String(value ?? '');
        if (creatable && v.startsWith('new:')) return { value: v, label: v.slice(4) };
        return options.find((o) => String(o.value) === v);
    }, [options, value, creatable]);
    const typed = search.trim();
    const canCreate = creatable && typed.length > 1 && !options.some((o) => o.label.toLowerCase() === typed.toLowerCase());

    return (
        <PopoverPrimitive.Root open={open} onOpenChange={setOpen}>
            <PopoverPrimitive.Trigger asChild disabled={disabled}>
                <button
                    type="button"
                    id={id}
                    aria-invalid={invalid || undefined}
                    aria-expanded={open}
                    className={cn(inputClass, 'group flex items-center justify-between gap-2 text-left', className)}
                >
                    <span className={cn('truncate', !selected && 'text-fg-subtle')}>
                        {loading ? 'Loading…' : selected ? selected.label : placeholder}
                    </span>
                    <span className="flex items-center gap-1">
                        {clearable && selected && !disabled && (
                            <span
                                role="button"
                                tabIndex={-1}
                                aria-label="Clear"
                                onPointerDown={(e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    onChange?.('');
                                }}
                                className="rounded p-0.5 text-fg-subtle opacity-0 hover:bg-subtle hover:text-fg group-hover:opacity-100"
                            >
                                <X className="size-3.5" />
                            </span>
                        )}
                        <ChevronDown className="size-4 text-fg-subtle" />
                    </span>
                </button>
            </PopoverPrimitive.Trigger>
            <PopoverPrimitive.Portal>
                <PopoverPrimitive.Content
                    align="start"
                    sideOffset={4}
                    className="z-50 w-[max(var(--radix-popover-trigger-width),16rem)] overflow-hidden rounded-lg border border-border bg-surface shadow-lg data-[state=open]:animate-pop-in"
                >
                    <Command loop>
                        <div className="flex items-center gap-2 border-b border-border px-3">
                            <Search className="size-4 shrink-0 text-fg-subtle" />
                            <Command.Input
                                value={search}
                                onValueChange={setSearch}
                                placeholder={creatable ? 'Search or type a new name…' : searchPlaceholder}
                                className="h-9 w-full bg-transparent text-sm outline-none placeholder:text-fg-subtle"
                            />
                        </div>
                        <Command.List className="scrollbar-thin max-h-64 overflow-y-auto p-1">
                            {!canCreate && <Command.Empty className="px-2 py-6 text-center text-sm text-fg-muted">{emptyText}</Command.Empty>}
                            {canCreate && (
                                <Command.Item
                                    value={`__create ${typed}`}
                                    forceMount
                                    onSelect={() => {
                                        onChange?.(`new:${typed}`);
                                        setSearch('');
                                        setOpen(false);
                                    }}
                                    className="flex h-8 cursor-default select-none items-center gap-2 rounded-md px-2 text-sm font-medium text-primary outline-none data-[selected=true]:bg-subtle"
                                >
                                    <Plus className="size-4 shrink-0" />
                                    <span className="truncate">Add “{typed}”</span>
                                </Command.Item>
                            )}
                            {options.map((o) => (
                                <Command.Item
                                    key={o.value}
                                    value={`${o.label} ${o.hint ?? ''} ${o.value}`}
                                    onSelect={() => {
                                        onChange?.(String(o.value));
                                        setOpen(false);
                                    }}
                                    className="flex h-8 cursor-default select-none items-center gap-2 rounded-md px-2 text-sm outline-none data-[selected=true]:bg-subtle"
                                >
                                    <Check className={cn('size-4 shrink-0 text-primary', String(o.value) === String(value) ? 'opacity-100' : 'opacity-0')} />
                                    <span className="truncate">{o.label}</span>
                                    {o.hint && <span className="ml-auto truncate pl-2 text-xs text-fg-subtle">{o.hint}</span>}
                                </Command.Item>
                            ))}
                        </Command.List>
                    </Command>
                </PopoverPrimitive.Content>
            </PopoverPrimitive.Portal>
        </PopoverPrimitive.Root>
    );
}
