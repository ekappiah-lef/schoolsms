import { useState } from 'react';
import { router } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import { Button } from '@/components/ui/button';

/**
 * Period for the finance pages: quick periods, school years (Aug–Jul),
 * calendar years, or a custom date range. Reloads the page with ?period= or ?from=&to=.
 */
export function PeriodPicker({ period, groups, url, only }) {
    const [custom, setCustom] = useState(period.key === 'custom');
    const [range, setRange] = useState({ from: period.from, to: period.to });
    const visit = (params) => router.get(url, params, { preserveScroll: true, preserveState: true, only });

    return (
        <div className="flex flex-wrap items-end gap-2">
            <label className="flex items-center gap-2 rounded-md bg-surface px-2.5 shadow-field">
                <CalendarDays className="size-4 text-fg-subtle" />
                <select
                    value={custom ? 'custom' : period.key}
                    onChange={(e) => {
                        if (e.target.value === 'custom') {
                            setCustom(true);
                            return;
                        }
                        setCustom(false);
                        visit({ period: e.target.value });
                    }}
                    aria-label="Period"
                    className="h-9 border-0 bg-transparent pr-1 text-sm font-medium focus:outline-none"
                >
                    {groups.map((g) => (
                        <optgroup key={g.label} label={g.label}>
                            {g.options.map((o) => (
                                <option key={o.value} value={o.value}>
                                    {o.label}
                                </option>
                            ))}
                        </optgroup>
                    ))}
                    <optgroup label="Other">
                        <option value="custom">Choose dates…</option>
                    </optgroup>
                </select>
            </label>
            {custom && (
                <form
                    className="flex flex-wrap items-end gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        visit({ from: range.from, to: range.to });
                    }}
                >
                    <input type="date" aria-label="From" value={range.from} max={range.to} onChange={(e) => setRange({ ...range, from: e.target.value })} className="h-9 rounded-md border-0 bg-surface px-2 text-sm shadow-field" />
                    <span className="pb-2 text-sm text-fg-muted">to</span>
                    <input type="date" aria-label="To" value={range.to} min={range.from} onChange={(e) => setRange({ ...range, to: e.target.value })} className="h-9 rounded-md border-0 bg-surface px-2 text-sm shadow-field" />
                    <Button size="sm" type="submit" className="h-9">
                        Apply
                    </Button>
                </form>
            )}
        </div>
    );
}

/** The four balance cards: opening, received, expenses, closing/current. No icons, no operators. */
/**
 * For a term or school year the cards cover that period only: the bills sent to
 * parents (invoiced), money received, expenses and the period's balance
 * (received − expenses). For other periods: opening balance, money in and out,
 * and the cash balance at the end.
 */
export function BalanceCards({ period, opening, invoiced, received, expenses, closing }) {
    const cards = [
        invoiced ? { label: `Total invoiced · ${period.label}`, value: invoiced.billed } : { label: `Opening balance · ${fmtDate(period.from)}`, value: opening },
        { label: `Money received · ${period.label}`, value: received, tone: 'success' },
        { label: `Expenses · ${period.label}`, value: expenses, tone: 'danger' },
        invoiced
            ? { label: `${period.term ? 'Term' : 'Year'} balance · ${period.label}`, value: received - expenses, strong: true }
            : { label: period.current ? 'Current balance' : `Closing balance · ${fmtDate(period.to)}`, value: closing, strong: true },
    ];
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {cards.map((c) => (
                <div key={c.label} className={`flex flex-col gap-2 rounded-lg p-5 shadow-card ${c.strong ? 'bg-primary-soft/50' : 'bg-surface'}`}>
                    <span className="text-2xs font-semibold uppercase tracking-wider text-fg-subtle">{c.label}</span>
                    <span
                        className={`tabular text-3xl font-semibold tracking-tight ${c.value < 0 ? 'text-danger-fg' : c.tone === 'success' ? 'text-success-fg' : c.tone === 'danger' ? 'text-danger-fg' : c.strong ? 'text-primary-hover' : 'text-fg'}`}
                    >
                        {c.value < 0 ? '−' : ''}
                        {Number(Math.abs(c.value)).toLocaleString('en-GB')}
                    </span>
                </div>
            ))}
        </div>
    );
}

function fmtDate(iso) {
    const [y, m, d] = String(iso).split('-');
    return `${d}/${m}/${y}`;
}
