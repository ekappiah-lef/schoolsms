import { Fragment } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { ModuleHeader } from '@/components/app/module';
import { EmptyState, Panel } from '@/components/app/page';
import { Button } from '@/components/ui/button';
import { PeriodFilter } from '@/components/fees/period-picker';
import { downloadCsv } from '@/lib/use-visit-state';
import { cn, formatDate, formatMoney } from '@/lib/utils';

/**
 * Trial balance for the chosen terms (cash basis, the same money as the ledger):
 * money accounts and expenses on the debit side; the balance brought forward and income on the credit side.
 */
export default function TrialBalance({ selection, tb, urls }) {
    const groups = tb.rows.reduce((acc, r) => {
        (acc[r.group] = acc[r.group] ?? []).push(r);
        return acc;
    }, {});
    const balanced = Math.abs(tb.debit - tb.credit) < 0.005;
    const money = (v) => (v ? formatMoney(v) : '');

    const exportCsv = () =>
        downloadCsv(
            `trial-balance-${tb.from}-${tb.to}.csv`,
            [
                { label: 'Account', value: (r) => r.name },
                { label: 'Group', value: (r) => r.group },
                { label: 'Debit', value: (r) => (r.debit ? r.debit.toFixed(2) : '') },
                { label: 'Credit', value: (r) => (r.credit ? r.credit.toFixed(2) : '') },
            ],
            [...tb.rows, { name: 'Total', group: '', debit: tb.debit, credit: tb.credit }],
        );

    return (
        <>
            <Head title="Trial balance" />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6">
                <ModuleHeader crumbs={['Finance', 'Trial balance']} title="Trial balance" aside={<PeriodFilter selection={selection} url={urls.self} />} />

                {tb.rows.length ? (
                    <Panel
                        title={`${selection.label}`}
                        description={tb.from ? `${formatDate(tb.from, 'd MMM yyyy')} – ${formatDate(tb.to, 'd MMM yyyy')}` : null}
                        actions={
                            <Button size="sm" variant="ghost" onClick={exportCsv}>
                                <Download />
                                CSV
                            </Button>
                        }
                        flush
                    >
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-border bg-canvas text-left text-2xs font-semibold uppercase text-fg-muted">
                                    <th className="h-9 px-5">Account</th>
                                    <th className="h-9 w-40 px-3 text-right">Debit</th>
                                    <th className="h-9 w-40 px-5 text-right">Credit</th>
                                </tr>
                            </thead>
                            <tbody className="tabular">
                                {Object.entries(groups).map(([group, rows]) => (
                                    <Fragment key={group}>
                                        <tr className="border-b border-border bg-muted/40">
                                            <td colSpan={3} className="px-5 py-1.5 text-2xs font-semibold uppercase text-fg-muted">
                                                {group}
                                            </td>
                                        </tr>
                                        {rows.map((r) => (
                                            <tr key={group + r.name} className="border-b border-border">
                                                <td className="px-5 py-2 pl-8">{r.name}</td>
                                                <td className="px-3 py-2 text-right">{money(r.debit)}</td>
                                                <td className="px-5 py-2 text-right">{money(r.credit)}</td>
                                            </tr>
                                        ))}
                                    </Fragment>
                                ))}
                            </tbody>
                            <tfoot className="tabular">
                                <tr className="border-t-2 border-fg font-semibold">
                                    <td className="px-5 py-3">Total</td>
                                    <td className="px-3 py-3 text-right">{formatMoney(tb.debit)}</td>
                                    <td className="px-5 py-3 text-right">{formatMoney(tb.credit)}</td>
                                </tr>
                                <tr>
                                    <td colSpan={3} className={cn('px-5 pb-3 text-right text-xs font-semibold', balanced ? 'text-success-fg' : 'text-danger-fg')}>
                                        {balanced ? 'Balanced' : `Difference ${formatMoney(Math.abs(tb.debit - tb.credit))}`}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                        {tb.receivable > 0 && (
                            <div className="flex items-center justify-between gap-3 border-t border-border bg-muted/40 px-5 py-3 text-sm">
                                <span className="text-fg-muted">Fees still owed on these terms' bills (not yet received)</span>
                                <span className="tabular font-semibold">{formatMoney(tb.receivable)}</span>
                            </div>
                        )}
                    </Panel>
                ) : (
                    <EmptyState title="Nothing recorded in these terms" action={<Button asChild><Link href={urls.ledger}>Open ledger</Link></Button>} />
                )}
            </div>
        </>
    );
}

TrialBalance.layout = withAppLayout;
