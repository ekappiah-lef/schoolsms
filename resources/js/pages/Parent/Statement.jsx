import { Head, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { ModuleHeader } from '@/components/app/module';
import { EmptyState, Panel, StatCard, StatStrip } from '@/components/app/page';
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
import { Select } from '@/components/ui/select';
import { cn, formatDate, formatMoney } from '@/lib/utils';

/** A parent's account statement: bills and payments for a period, with the running balance. */
export default function Statement({ children, filters, statement, urls }) {
    const go = (change) => router.get(urls.self, { ...filters, ...change }, { preserveScroll: true, preserveState: true });
    const many = filters.child === 'all' && children.length > 1;
    const pdf = `${urls.pdf}?${new URLSearchParams(filters).toString()}`;

    return (
        <>
            <Head title="Statement" />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6">
                <ModuleHeader crumbs={['Fees', 'Statement']} title="Statement" />

                <div className="flex flex-wrap items-end gap-3">
                    {children.length > 1 && (
                        <div className="w-full sm:w-56">
                            <span className="mb-1.5 block text-sm font-medium">Child</span>
                            <Select
                                value={filters.child}
                                onChange={(v) => go({ child: v || 'all' })}
                                options={[{ value: 'all', label: 'All children' }, ...children.map((c) => ({ value: c.id, label: c.name }))]}
                                clearable={false}
                            />
                        </div>
                    )}
                    <div className="w-full sm:w-44">
                        <span className="mb-1.5 block text-sm font-medium">From</span>
                        <DatePicker value={filters.from} onChange={(v) => v && go({ from: v })} fromYear={2022} />
                    </div>
                    <div className="w-full sm:w-44">
                        <span className="mb-1.5 block text-sm font-medium">To</span>
                        <DatePicker value={filters.to} onChange={(v) => v && go({ to: v })} fromYear={2022} />
                    </div>
                    <Button className="ml-auto" asChild>
                        <a href={pdf}>
                            <Download />
                            Download PDF
                        </a>
                    </Button>
                </div>

                <StatStrip>
                    <StatCard label="Opening balance" value={formatMoney(statement.opening)} />
                    <StatCard label="Billed" value={formatMoney(statement.billed)} />
                    <StatCard label="Paid" value={formatMoney(statement.paid)} />
                    <StatCard label="Closing balance" value={formatMoney(statement.closing)} tone={statement.closing > 0 ? 'danger' : 'neutral'} />
                </StatStrip>

                <Panel title={`${formatDate(filters.from, 'd MMM yyyy')} – ${formatDate(filters.to, 'd MMM yyyy')}`} flush>
                    {statement.rows.length ? (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-border bg-canvas text-left text-2xs font-semibold uppercase text-fg-muted">
                                        <th className="h-9 px-5">Date</th>
                                        {many && <th className="h-9 px-3">Child</th>}
                                        <th className="h-9 px-3">Details</th>
                                        <th className="h-9 px-3 text-right">Billed</th>
                                        <th className="h-9 px-3 text-right">Paid</th>
                                        <th className="h-9 px-5 text-right">Balance</th>
                                    </tr>
                                </thead>
                                <tbody className="tabular">
                                    <tr className="border-b border-border bg-muted/40">
                                        <td className="px-5 py-2" colSpan={many ? 5 : 4}>
                                            Opening balance
                                        </td>
                                        <td className="px-5 py-2 text-right font-medium">{formatMoney(statement.opening)}</td>
                                    </tr>
                                    {statement.rows.map((r, i) => (
                                        <tr key={i} className="border-b border-border last:border-0">
                                            <td className="whitespace-nowrap px-5 py-2">{formatDate(r.date, 'dd/MM/yyyy')}</td>
                                            {many && <td className="px-3 py-2 text-fg-muted">{r.student}</td>}
                                            <td className="px-3 py-2">
                                                {r.text}
                                                {r.ref && <span className="ml-2 text-xs text-fg-muted">{r.ref}</span>}
                                            </td>
                                            <td className="px-3 py-2 text-right">{r.debit ? formatMoney(r.debit) : ''}</td>
                                            <td className="px-3 py-2 text-right text-success-fg">{r.credit ? formatMoney(r.credit) : ''}</td>
                                            <td className={cn('px-5 py-2 text-right font-medium', r.balance > 0 && 'text-danger-fg')}>{formatMoney(r.balance)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <EmptyState compact title="Nothing billed or paid in these dates" />
                    )}
                </Panel>
            </div>
        </>
    );
}

Statement.layout = withAppLayout;
