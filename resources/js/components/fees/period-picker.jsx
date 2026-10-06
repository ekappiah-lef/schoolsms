import { router } from '@inertiajs/react';
import { PeriodTree } from '@/components/fees/period-tree';

/**
 * The Academic Period filter used by every finance page: school years that open into their terms,
 * with checkboxes. Reloads the page with ?periods=2025-2026:3,2026-2027:1.
 * `selection` comes from App\Support\TermSelection::toArray().
 */
export function PeriodFilter({ selection, url, params = {}, only, className = 'w-72' }) {
    return (
        <PeriodTree
            years={selection.tree}
            value={selection.keys}
            label={null}
            className={className}
            onChange={(keys) => router.get(url, { ...params, periods: keys.join(',') }, { preserveScroll: true, preserveState: true, only })}
        />
    );
}

/** Query string for links that keep the same terms selected. */
export const periodQuery = (selection) => new URLSearchParams({ periods: selection.keys.join(',') }).toString();

/**
 * The four cards for the chosen terms: bills sent to parents, money received, expenses and the
 * balance (received − expenses). Each term starts from zero. No icons, no operators.
 */
export function BalanceCards({ selection, invoiced, received, expenses }) {
    const cards = [
        { label: `Total invoiced · ${selection.label}`, value: invoiced.billed },
        { label: `Money received · ${selection.label}`, value: received, tone: 'success' },
        { label: `Expenses · ${selection.label}`, value: expenses, tone: 'danger' },
        { label: `${selection.balanceName} · ${selection.label}`, value: received - expenses, strong: true },
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
