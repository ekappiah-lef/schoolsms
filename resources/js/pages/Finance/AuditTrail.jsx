import { useMemo, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { withAppLayout } from '@/layouts/AppLayout';
import { ModuleHeader } from '@/components/app/module';
import { EmptyState } from '@/components/app/page';
import { SearchInput, usePaged } from '@/components/app/data-table';
import { Segmented } from '@/components/ui/tabs';
import { cn, formatDate, formatMoney } from '@/lib/utils';

const FIELD = { type: 'Type', category: 'Category', amount: 'Amount', date: 'Date', method: 'Mode', reference: 'Reference', description: 'Description' };

/** Read-only trail of reversed payments and changes to income / expense entries. Nothing here can be edited or deleted. */
export default function AuditTrail({ rows, actions, totals }) {
    const [action, setAction] = useState('all');
    const [q, setQ] = useState('');
    const filtered = useMemo(() => {
        const s = q.trim().toLowerCase();
        return rows.filter((r) => (action === 'all' || r.action === action) && (!s || `${r.by ?? ''} ${r.student ?? ''} ${r.reason ?? ''} ${JSON.stringify(r.before ?? {})}`.toLowerCase().includes(s)));
    }, [rows, action, q]);
    const { shown, pager } = usePaged(filtered, 10, 'entries');

    return (
        <>
            <Head title="Audit trail" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
                <ModuleHeader
                    crumbs={['Finance', 'Audit trail']}
                    title="Audit trail"
                    description="Every reversed payment and every change to income or expense entries: who, when, why and the figures before and after. Nothing here can be changed or deleted."
                />

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div className="flex flex-col gap-2 rounded-lg bg-surface p-5 shadow-card">
                        <span className="text-2xs font-semibold uppercase tracking-wider text-fg-subtle">Payments reversed</span>
                        <span className="tabular text-3xl font-semibold tracking-tight">{totals.reversedCount}</span>
                    </div>
                    <div className="flex flex-col gap-2 rounded-lg bg-surface p-5 shadow-card">
                        <span className="text-2xs font-semibold uppercase tracking-wider text-fg-subtle">Amount reversed</span>
                        <span className="tabular text-3xl font-semibold tracking-tight text-danger-fg">{formatMoney(totals.reversed)}</span>
                    </div>
                </div>

                <div className="overflow-hidden rounded-lg bg-surface shadow-card">
                    <div className="flex flex-wrap items-center gap-3 border-b border-border px-5 py-4">
                        <SearchInput value={q} onChange={setQ} placeholder="Search name, reason, receipt…" className="w-full sm:w-72" delay={0} />
                        <Segmented size="sm" value={action} onChange={setAction} options={[{ value: 'all', label: 'All' }, ...actions]} />
                    </div>
                    {filtered.length ? (
                        <div className="scrollbar-thin overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                                        <th className="h-10 px-5">When / who</th>
                                        <th className="h-10 px-3">What</th>
                                        <th className="h-10 px-3">Details</th>
                                        <th className="h-10 px-3">Reason</th>
                                        <th className="h-10 px-5 text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {shown.map((r) => (
                                        <tr key={r.id} className="border-b border-border align-top last:border-0">
                                            <td className="whitespace-nowrap px-5 py-3">
                                                <div className="tabular">{formatDate(r.at, 'dd/MM/yyyy HH:mm')}</div>
                                                <div className="text-xs text-fg-muted">{r.by ?? '—'}</div>
                                            </td>
                                            <td className="px-3 py-3">
                                                <span className={cn('rounded px-1.5 py-0.5 text-2xs font-semibold uppercase', r.action === 'update_entry' ? 'bg-warning-soft text-warning-fg' : 'bg-danger-soft text-danger-fg')}>{r.label}</span>
                                                {r.student && (
                                                    <div className="mt-1">
                                                        <Link href={r.student_url} className="font-medium hover:text-primary hover:underline">
                                                            {r.student}
                                                        </Link>
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-3 py-3">
                                                <Details r={r} />
                                            </td>
                                            <td className="max-w-xs px-3 py-3 text-fg-muted">{r.reason || '—'}</td>
                                            <td className="tabular whitespace-nowrap px-5 py-3 text-right font-semibold">{r.amount !== null ? formatMoney(r.amount) : '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            {pager}
                        </div>
                    ) : (
                        <EmptyState compact title={rows.length ? 'Nothing matches' : 'Nothing recorded yet'} description="Reversed payments and changes to income or expenses will appear here." />
                    )}
                </div>
            </div>
        </>
    );
}

AuditTrail.layout = withAppLayout;

function Details({ r }) {
    const b = r.before ?? {};
    if (r.action === 'void_receipt') {
        return (
            <div className="text-xs text-fg-muted">
                <div className="font-medium text-fg">Receipt {b.number}</div>
                <div>{b.bill}</div>
                <div>
                    Paid {b.paid_on ? formatDate(b.paid_on, 'dd/MM/yyyy') : '—'} · {b.method}
                    {b.reference ? ` · ${b.reference}` : ''}
                </div>
            </div>
        );
    }
    if (r.action === 'update_entry') {
        const a = r.after ?? {};
        const changed = Object.keys(FIELD).filter((k) => String(b[k] ?? '') !== String(a[k] ?? ''));
        return (
            <ul className="text-xs text-fg-muted">
                <li className="font-medium text-fg">{a.category ?? b.category}</li>
                {changed.map((k) => (
                    <li key={k}>
                        {FIELD[k]}: <span className="line-through">{String(b[k] ?? '—')}</span> → <span className="text-fg">{String(a[k] ?? '—')}</span>
                    </li>
                ))}
            </ul>
        );
    }
    return (
        <div className="text-xs text-fg-muted">
            <div className="font-medium text-fg">
                {b.type === 'income' ? 'Income' : 'Expense'} · {b.category}
            </div>
            <div>
                {b.date} · {b.method ?? '—'}
                {b.reference ? ` · ${b.reference}` : ''}
            </div>
            {b.description && <div>{b.description}</div>}
        </div>
    );
}
