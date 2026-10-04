import { useState } from 'react';
import * as PopoverPrimitive from '@radix-ui/react-popover';
import { DayPicker } from 'react-day-picker';
import 'react-day-picker/style.css';
import { format } from 'date-fns';
import { CalendarDays, X } from 'lucide-react';
import { cn, parseDate } from '@/lib/utils';
import { inputClass } from './input';

/**
 * Date picker that emits `yyyy-MM-dd` strings (what the users.dob column stores).
 * Month/year dropdowns make it quick to reach birth dates years back.
 */
export function DatePicker({ value, onChange, placeholder = 'dd/mm/yyyy', id, invalid, fromYear = 1990, toYear, disabled, defaultMonth }) {
    const [open, setOpen] = useState(false);
    const date = parseDate(value);
    const endYear = toYear ?? new Date().getFullYear();

    return (
        <PopoverPrimitive.Root open={open} onOpenChange={setOpen}>
            <PopoverPrimitive.Trigger asChild disabled={disabled}>
                <button
                    type="button"
                    id={id}
                    aria-invalid={invalid || undefined}
                    className={cn(inputClass, 'group flex items-center gap-2 text-left')}
                >
                    <CalendarDays className="size-4 shrink-0 text-fg-subtle" />
                    <span className={cn('flex-1 truncate', !date && 'text-fg-subtle')}>{date ? format(date, 'dd/MM/yyyy') : placeholder}</span>
                    {date && !disabled && (
                        <span
                            role="button"
                            tabIndex={-1}
                            aria-label="Clear date"
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
                </button>
            </PopoverPrimitive.Trigger>
            <PopoverPrimitive.Portal>
                <PopoverPrimitive.Content
                    align="start"
                    sideOffset={4}
                    className="z-50 rounded-lg border border-border bg-surface p-3 shadow-lg data-[state=open]:animate-pop-in"
                >
                    <DayPicker
                        mode="single"
                        captionLayout="dropdown"
                        startMonth={new Date(fromYear, 0)}
                        endMonth={new Date(endYear, 11)}
                        defaultMonth={date ?? defaultMonth ?? new Date(endYear - 8, 0)}
                        selected={date ?? undefined}
                        onSelect={(d) => {
                            onChange?.(d ? format(d, 'yyyy-MM-dd') : '');
                            setOpen(false);
                        }}
                    />
                </PopoverPrimitive.Content>
            </PopoverPrimitive.Portal>
        </PopoverPrimitive.Root>
    );
}
