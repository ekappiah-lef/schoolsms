import { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { withAppLayout } from '@/layouts/AppLayout';
import { ModuleHeader } from '@/components/app/module';
import { EmptyState, Panel } from '@/components/app/page';
import { usePaged } from '@/components/app/data-table';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/dialog';
import { Select } from '@/components/ui/select';
import { formatDate, formatMoney } from '@/lib/utils';
import { submitForm } from '@/lib/http';
import { ReceiptText } from 'lucide-react';

const TERMS = { 1: '1st term', 2: '2nd term', 3: '3rd term' };

/** Every direct bill in the school (AssignServicesController). */
export default function DirectBills({ bills }) {
    const [year, setYear] = useState('');
    const [term, setTerm] = useState('');
    const [removing, setRemoving] = useState(null);
    const [busy, setBusy] = useState(false);
    const years = useMemo(() => [...new Set(bills.map((b) => b.year))].map((y) => ({ value: y, label: y })), [bills]);
    const rows = bills.filter((b) => (!year || b.year === year) && (!term || String(b.term) === term));
    const { shown, pager } = usePaged(rows, 25, 'bills');
    const remove = async () => {
        setBusy(true);
        const r = await submitForm(removing.remove, {}, { method: 'delete' });
        setBusy(false);
        setRemoving(null);
        if (r.ok) {
            toast.success(r.message);
            router.reload({ only: ['bills'], preserveScroll: true });
        } else {
            toast.error(r.message);
        }
    };

    return (
        <>
            <Head title="Direct bills" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
                <ModuleHeader crumbs={['Finance', 'Services', 'Direct bills']} title="Direct bills" />
                <Panel
                    flush
                    title={`${rows.length} bills · GHS ${formatMoney(rows.reduce((t, b) => t + b.amount, 0))}`}
                    actions={
                        <>
                            <Select size="sm" className="w-36" value={year} onChange={(v) => setYear(v ?? '')} options={years} clearable clearLabel="All years" placeholder="All years" />
                            <Select size="sm" className="w-32" value={term} onChange={(v) => setTerm(v ?? '')} options={Object.entries(TERMS).map(([value, label]) => ({ value, label }))} clearable clearLabel="All terms" placeholder="All terms" />
                        </>
                    }
                >
                    {rows.length ? (
                        <>
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead>
                                        <tr className="border-b border-border text-2xs font-semibold uppercase text-fg-muted">
                                            <th className="h-9 px-4">Student</th>
                                            <th className="h-9 px-3">Reason</th>
                                            <th className="h-9 px-3">Term</th>
                                            <th className="h-9 px-3">Billed on</th>
                                            <th className="h-9 px-3 text-right">Amount</th>
                                            <th className="h-9 px-3 text-right">Paid</th>
                                            <th className="h-9 w-24 px-4" />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {shown.map((b) => (
                                            <tr key={b.id} className="border-b border-border last:border-0">
                                                <td className="px-4 py-2 font-medium">{b.student}</td>
                                                <td className="px-3 py-2">{b.label}</td>
                                                <td className="whitespace-nowrap px-3 py-2">{TERMS[b.term]} {b.year}</td>
                                                <td className="whitespace-nowrap px-3 py-2">{formatDate(b.date)}</td>
                                                <td className="tabular px-3 py-2 text-right">{formatMoney(b.amount)}</td>
                                                <td className="tabular px-3 py-2 text-right">{formatMoney(b.paid)}</td>
                                                <td className="px-4 py-2 text-right">
                                                    {b.remove && (
                                                        <Button size="sm" variant="ghost" onClick={() => setRemoving(b)}>
                                                            Remove
                                                        </Button>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            {pager}
                        </>
                    ) : (
                        <EmptyState icon={ReceiptText} title="No direct bills" />
                    )}
                </Panel>
            </div>
            <ConfirmDialog
                open={!!removing}
                onOpenChange={(o) => !o && !busy && setRemoving(null)}
                title={`Remove this bill for ${removing?.student ?? ''}?`}
                description={removing ? `${removing.label} · GHS ${formatMoney(removing.amount)}` : ''}
                confirmLabel="Remove"
                loading={busy}
                onConfirm={remove}
            />
        </>
    );
}

DirectBills.layout = withAppLayout;
