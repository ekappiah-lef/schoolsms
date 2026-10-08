import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { RefreshCw } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { InfoCallout, ModuleHeader } from '@/components/app/module';
import { EmptyState, Panel } from '@/components/app/page';
import { usePaged } from '@/components/app/data-table';
import { Button } from '@/components/ui/button';
import { Segmented } from '@/components/ui/tabs';
import { submitForm } from '@/lib/http';
import { cn, formatDate, formatMoney } from '@/lib/utils';

/**
 * Keeps ClearEnroll in step with fees here: every student who owes (with the amount) is sent at the start
 * of each term and with "Sync now"; each payment recorded here sends that student's new balance straight away.
 */
export default function ClearEnrollSync({ connection, debtors, history, canSync, urls }) {
    const [busy, setBusy] = useState(false);
    const [view, setView] = useState('debtors');
    const ready = debtors.filter((d) => d.consented && !d.problem);
    const notSent = debtors.filter((d) => !d.consented || d.problem);
    const list = view === 'debtors' ? debtors : notSent;
    const { shown, pager } = usePaged(list, 10, 'students');
    const total = ready.reduce((a, d) => a + d.balance, 0);

    const run = async () => {
        setBusy(true);
        const r = await submitForm(urls.run, {});
        setBusy(false);
        r.ok ? toast.success(r.message) : toast.error(r.message);
        router.reload({ only: ['history', 'debtors'] });
    };

    return (
        <>
            <Head title="ClearEnroll fee status" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
                <ModuleHeader
                    crumbs={['ClearEnroll', 'Fee status sync']}
                    title="Fee status sync"
                    aside={
                        <Button variant="primary" onClick={run} disabled={!canSync || busy}>
                            <RefreshCw className={cn(busy && 'animate-spin')} />
                            {busy ? 'Sending…' : 'Sync now'}
                        </Button>
                    }
                />

                <div className={cn('rounded-lg px-5 py-3 text-sm', connection.ok ? 'bg-success-soft text-success-fg' : 'bg-warning-soft text-warning-fg')}>
                    {connection.ok ? `Connected to ClearEnroll as ${connection.school}.` : connection.message}
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Tile label="Students owing, to send" value={ready.length} />
                    <Tile label="Amount owed, to send" value={formatMoney(total)} />
                    <Tile label="Not sent (details or consent missing)" value={notSent.length} tone={notSent.length ? 'warn' : ''} />
                </div>

                <InfoCallout>
                    At the start of each term every student who owes fees is sent to ClearEnroll with the amount. Each payment recorded here sends the student’s new balance straight away, and ClearEnroll clears the
                    flag once nothing is owed. Only students whose parent accepted the admission agreement are sent.
                </InfoCallout>

                <Panel
                    title="Students owing"
                    flush
                    actions={
                        <Segmented
                            size="sm"
                            value={view}
                            onChange={setView}
                            options={[
                                { value: 'debtors', label: `All (${debtors.length})` },
                                { value: 'problems', label: `Not sent (${notSent.length})` },
                            ]}
                        />
                    }
                >
                    {list.length ? (
                        <>
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                                        <th className="h-9 px-5">Student</th>
                                        <th className="h-9 px-3">Class</th>
                                        <th className="h-9 px-3">ClearEnroll</th>
                                        <th className="h-9 px-5 text-right">Owes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {shown.map((d) => (
                                        <tr key={d.id} className="border-b border-border last:border-0">
                                            <td className="px-5 py-2.5">
                                                <Link href={d.url} className="font-medium hover:text-primary hover:underline">
                                                    {d.name}
                                                </Link>
                                            </td>
                                            <td className="px-3 py-2.5 text-fg-muted">{d.class}</td>
                                            <td className={cn('px-3 py-2.5', d.problem || !d.consented ? 'text-warning-fg' : 'text-fg-muted')}>
                                                {!d.consented ? 'No consent on file' : d.problem || 'Sent'}
                                            </td>
                                            <td className="tabular px-5 py-2.5 text-right font-semibold">{formatMoney(d.balance)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            {pager}
                        </>
                    ) : (
                        <EmptyState compact title={view === 'debtors' ? 'Nobody owes fees' : 'Every student owing can be sent'} />
                    )}
                </Panel>

                <Panel title="History" flush>
                    {history.length ? (
                        <ul className="divide-y divide-border">
                            {history.map((h) => (
                                <li key={h.id} className="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-2.5 text-sm">
                                    <span className="tabular w-36 text-fg-muted">{formatDate(h.at, 'dd/MM/yyyy HH:mm')}</span>
                                    <span className="font-medium">{h.kind === 'full' ? `Full sync · ${h.term}` : `Payment update · ${h.student ?? ''}`}</span>
                                    {h.error ? (
                                        <span className="text-danger-fg">Failed: {h.error}</span>
                                    ) : (
                                        <span className="text-fg-muted">
                                            {h.sent} sent · {h.flagged} flagged · {h.updated} updated · {h.cleared} cleared{h.rejected ? ` · ${h.rejected} not taken` : ''}
                                        </span>
                                    )}
                                    <span className="ml-auto text-xs text-fg-subtle">{h.by}</span>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <EmptyState compact title="Nothing sent yet" />
                    )}
                </Panel>
            </div>
        </>
    );
}

ClearEnrollSync.layout = withAppLayout;

function Tile({ label, value, tone }) {
    return (
        <div className="flex flex-col gap-2 rounded-lg bg-surface p-5 shadow-card">
            <span className="text-2xs font-semibold uppercase tracking-wider text-fg-subtle">{label}</span>
            <span className={cn('tabular text-2xl font-semibold tracking-tight', tone === 'warn' && 'text-warning-fg')}>{value}</span>
        </div>
    );
}
