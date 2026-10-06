import { useState } from 'react';
import * as PopoverPrimitive from '@radix-ui/react-popover';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';
import { inputClass } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { Button } from '@/components/ui/button';

const yearLabel = (s) => `${s.replace('-', ' - ')} Academic Year`;
const termName = (t) => ['1st', '2nd', '3rd'][t - 1] + ' Term';

/**
 * Academic period picker: school years that expand into their terms, each with a checkbox.
 * Ticking a year ticks all its terms. `value` and `onChange` use term keys "YYYY-YYYY:N".
 * Changes apply on Done; Clear empties the selection.
 *
 * years: [{ session: '2026-2027', terms: [1, 2, 3] }]  (newest first)
 */
export function PeriodTree({ years, value, onChange, label = 'Academic Period', className }) {
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState(value);
    const [expanded, setExpanded] = useState(() => new Set([years[0]?.session]));
    const keys = (y) => y.terms.map((t) => `${y.session}:${t}`);
    const has = (k) => draft.includes(k);
    const yearState = (y) => {
        const n = keys(y).filter(has).length;
        return n === 0 ? false : n === y.terms.length ? true : 'indeterminate';
    };
    const toggleYear = (y) => {
        const ks = keys(y);
        setDraft((d) => (yearState(y) === true ? d.filter((k) => !ks.includes(k)) : [...new Set([...d, ...ks])]));
    };
    const toggleTerm = (k) => setDraft((d) => (d.includes(k) ? d.filter((x) => x !== k) : [...d, k]));
    const toggleOpen = (s) =>
        setExpanded((e) => {
            const n = new Set(e);
            n.has(s) ? n.delete(s) : n.add(s);
            return n;
        });

    return (
        <div className={className}>
            {label && <span className="mb-1.5 block text-sm font-medium">{label}</span>}
            <PopoverPrimitive.Root
                open={open}
                onOpenChange={(o) => {
                    setOpen(o);
                    if (o) setDraft(value);
                }}
            >
                <PopoverPrimitive.Trigger asChild>
                    <button type="button" className={cn(inputClass, 'flex items-center justify-between gap-2 text-left')}>
                        <span className={cn('truncate', !value.length && 'text-fg-subtle')}>{summary(years, value)}</span>
                        <ChevronDown className="size-4 shrink-0 text-fg-subtle" />
                    </button>
                </PopoverPrimitive.Trigger>
                <PopoverPrimitive.Portal>
                    <PopoverPrimitive.Content align="start" sideOffset={4} className="z-50 w-[max(var(--radix-popover-trigger-width),20rem)] rounded-lg border border-border bg-surface shadow-lg data-[state=open]:animate-pop-in">
                        <div className="scrollbar-thin max-h-80 overflow-y-auto p-2">
                            {years.map((y) => {
                                const isOpen = expanded.has(y.session);
                                const st = yearState(y);
                                return (
                                    <div key={y.session} className="py-0.5">
                                        <div className="flex items-center gap-2 rounded-md px-1 py-1.5 hover:bg-muted/60">
                                            <button type="button" aria-label={isOpen ? 'Collapse' : 'Expand'} onClick={() => toggleOpen(y.session)} className="rounded p-0.5 text-fg-subtle hover:bg-subtle">
                                                {isOpen ? <ChevronDown className="size-4" /> : <ChevronRight className="size-4" />}
                                            </button>
                                            <label className="flex flex-1 cursor-pointer items-center gap-2.5 text-sm">
                                                <Checkbox checked={st} onCheckedChange={() => toggleYear(y)} />
                                                <span className={cn(st ? 'font-medium text-primary' : '')}>{yearLabel(y.session)}</span>
                                            </label>
                                        </div>
                                        {isOpen && (
                                            <div className="ml-12 flex flex-col">
                                                {y.terms.map((t) => {
                                                    const k = `${y.session}:${t}`;
                                                    return (
                                                        <label key={k} className="flex cursor-pointer items-center gap-2.5 rounded-md px-1 py-1.5 text-sm hover:bg-muted/60">
                                                            <Checkbox checked={has(k)} onCheckedChange={() => toggleTerm(k)} />
                                                            {termName(t)}
                                                        </label>
                                                    );
                                                })}
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                        <div className="border-t border-border px-3 py-2 text-xs text-fg-muted">{summary(years, draft)}</div>
                        <div className="flex items-center justify-between border-t border-border px-3 py-2">
                            <Button size="xs" variant="ghost" onClick={() => setDraft([])}>
                                Clear
                            </Button>
                            <Button
                                size="xs"
                                variant="primary"
                                onClick={() => {
                                    onChange(draft);
                                    setOpen(false);
                                }}
                            >
                                Done
                            </Button>
                        </div>
                    </PopoverPrimitive.Content>
                </PopoverPrimitive.Portal>
            </PopoverPrimitive.Root>
        </div>
    );
}

/** "2026 - 2027 Academic Year", "1st Term 2025 - 2026", or "4 terms in 2 years". */
export function summary(years, value) {
    if (!value.length) return 'Choose a period';
    const bySession = {};
    value.forEach((k) => {
        const [s, t] = k.split(':');
        (bySession[s] ??= []).push(Number(t));
    });
    const sessions = Object.keys(bySession);
    if (sessions.length === 1) {
        const s = sessions[0];
        const y = years.find((x) => x.session === s);
        if (y && bySession[s].length === y.terms.length) return yearLabel(s);
        if (bySession[s].length === 1) return `${termName(bySession[s][0])} ${s.replace('-', ' - ')}`;
        return `${bySession[s].length} terms in ${s.replace('-', ' - ')}`;
    }
    const full = sessions.every((s) => years.find((x) => x.session === s)?.terms.length === bySession[s].length);
    return full ? `${sessions.length} academic years` : `${value.length} terms in ${sessions.length} years`;
}
