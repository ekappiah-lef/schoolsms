import { Head, router } from '@inertiajs/react';
import { withAppLayout } from '@/layouts/AppLayout';
import { ModuleHeader, RegistryCard } from '@/components/app/module';
import { StatCard, StatStrip } from '@/components/app/page';
import { Badge } from '@/components/ui/badge';
import { DatePicker } from '@/components/ui/date-picker';
import { Select } from '@/components/ui/select';
import { formatDate } from '@/lib/utils';

const CHANNEL = { sms: 'SMS', email: 'Email', whatsapp: 'WhatsApp' };
const STATUS = { sent: ['Sent', 'success'], held: ['Held (demo)', 'neutral'], failed: ['Failed', 'danger'] };

/** Every SMS, email and WhatsApp sent to a person: sent, held back (demo mode) or failed, and why. */
export default function Delivery({ filters, counts, rows, urls }) {
    const go = (change) => router.get(urls.self, { ...filters, ...change }, { preserveScroll: true, preserveState: true });

    return (
        <>
            <Head title="Delivery report" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
                <ModuleHeader crumbs={['Communication', 'Delivery report']} title="Delivery report" />

                <div className="flex flex-wrap items-end gap-3">
                    <div className="w-full sm:w-44">
                        <span className="mb-1.5 block text-sm font-medium">From</span>
                        <DatePicker value={filters.from} onChange={(v) => v && go({ from: v })} fromYear={2022} />
                    </div>
                    <div className="w-full sm:w-44">
                        <span className="mb-1.5 block text-sm font-medium">To</span>
                        <DatePicker value={filters.to} onChange={(v) => v && go({ to: v })} fromYear={2022} />
                    </div>
                    <div className="w-full sm:w-40">
                        <span className="mb-1.5 block text-sm font-medium">Channel</span>
                        <Select
                            value={filters.channel ?? 'all'}
                            onChange={(v) => go({ channel: v && v !== 'all' ? v : null })}
                            options={[{ value: 'all', label: 'All' }, ...Object.entries(CHANNEL).map(([value, label]) => ({ value, label }))]}
                            clearable={false}
                        />
                    </div>
                    <div className="w-full sm:w-40">
                        <span className="mb-1.5 block text-sm font-medium">Status</span>
                        <Select
                            value={filters.status ?? 'all'}
                            onChange={(v) => go({ status: v && v !== 'all' ? v : null })}
                            options={[{ value: 'all', label: 'All' }, ...Object.entries(STATUS).map(([value, [label]]) => ({ value, label }))]}
                            clearable={false}
                        />
                    </div>
                </div>

                <StatStrip className="lg:grid-cols-3">
                    <StatCard label="Sent" value={counts.sent} />
                    <StatCard label="Held (demo)" value={counts.held} />
                    <StatCard label="Failed" value={counts.failed} tone={counts.failed ? 'danger' : 'neutral'} />
                </StatStrip>

                <RegistryCard
                    title="Messages"
                    rows={rows}
                    exportName={`delivery-${filters.from}-${filters.to}`}
                    emptyText="Nothing sent in these dates"
                    searchText={(r) => `${r.to} ${r.what} ${r.by ?? ''} ${r.reason ?? ''}`}
                    columns={[
                        { key: 'at', header: 'Date', sort: (r) => r.at, exportValue: (r) => formatDate(r.at, 'dd/MM/yyyy HH:mm'), cell: (r) => <span className="whitespace-nowrap tabular">{formatDate(r.at, 'dd/MM/yyyy HH:mm')}</span> },
                        { key: 'to', header: 'To', sort: (r) => r.to, exportValue: (r) => r.to, cell: (r) => <span className="font-medium">{r.to}</span> },
                        { key: 'channel', header: 'Channel', sort: (r) => r.channel, exportValue: (r) => CHANNEL[r.channel] ?? r.channel, cell: (r) => CHANNEL[r.channel] ?? r.channel },
                        { key: 'what', header: 'Message', sort: (r) => r.what, exportValue: (r) => r.what, cell: (r) => <span className="text-fg-muted">{r.what}</span> },
                        {
                            key: 'status',
                            header: 'Status',
                            sort: (r) => r.status,
                            exportValue: (r) => STATUS[r.status][0],
                            cell: (r) => (
                                <div>
                                    <Badge tone={STATUS[r.status][1]}>{STATUS[r.status][0]}</Badge>
                                    {r.reason && <div className="mt-1 max-w-xs text-xs text-danger-fg">{r.reason}</div>}
                                </div>
                            ),
                        },
                        { key: 'by', header: 'Sent by', sort: (r) => r.by ?? '', exportValue: (r) => r.by ?? '', cell: (r) => r.by || <span className="text-fg-subtle">System</span> },
                    ]}
                />
            </div>
        </>
    );
}

Delivery.layout = withAppLayout;
