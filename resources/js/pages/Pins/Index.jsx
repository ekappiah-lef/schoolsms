import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { KeyRound, Trash2 } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { Field, InfoCallout, ModuleHeader, RegistryCard, SetupCard, fieldInput } from '@/components/app/module';
import { useConfirmAction } from '@/components/app/confirm-action';
import { Button } from '@/components/ui/button';
import { Segmented } from '@/components/ui/tabs';
import { formatDate } from '@/lib/utils';

/** Exam pins: unused pins to hand out, pins already used, and generating new ones. */
export default function PinsIndex({ tab: initialTab, valid, used, max, urls }) {
    const [tab, setTab] = useState(initialTab);
    const [count, setCount] = useState('');
    const [processing, setProcessing] = useState(false);
    const [confirm, confirmDialog] = useConfirmAction();
    const errors = usePage().props.errors ?? {};
    const room = Math.max(max - valid.length, 0);
    const n = Number(count);
    const invalid = count !== '' && !(Number.isInteger(n) && n >= 10 && n <= 500);

    const generate = () => {
        if (invalid || !count) return;
        router.post(urls.store, { pin_count: n }, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => {
                setCount('');
                setTab('valid');
            },
        });
    };

    return (
        <>
            <Head title="Exam pins" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
                <ModuleHeader crumbs={['Examinations', 'Exam pins']} title="Exam pins" description="Parents and students enter a pin to view results. Each pin can be used up to six times for one student." />
                <Segmented
                    value={tab}
                    onChange={setTab}
                    options={[
                        { value: 'valid', label: `Unused pins (${valid.length})` },
                        { value: 'used', label: `Used pins (${used.length})` },
                        { value: 'create', label: 'Generate pins' },
                    ]}
                />

                {tab === 'valid' && (
                    <RegistryCard
                        title="Unused pins"
                        rows={valid.map((code) => ({ code }))}
                        exportName="exam-pins"
                        emptyText="No unused pins. Generate some to hand out."
                        searchText={(r) => r.code}
                        columns={[
                            { key: 'sn', header: 'S/N', headerClassName: 'w-16' },
                            { key: 'code', header: 'Pin', sort: (r) => r.code, exportValue: (r) => r.code, cell: (r) => <span className="font-mono text-sm tracking-wide">{r.code}</span> },
                        ]}
                    />
                )}

                {tab === 'used' && (
                    <RegistryCard
                        title="Used pins"
                        rows={used}
                        exportName="used-exam-pins"
                        emptyText="No pins have been used yet."
                        searchText={(r) => `${r.code} ${r.by ?? ''} ${r.student ?? ''}`}
                        actions={
                            used.length ? (
                                <Button
                                    size="sm"
                                    variant="danger"
                                    onClick={() =>
                                        confirm({ title: `Delete all ${used.length} used pins?`, description: 'Used pins are removed for good. Unused pins are not affected.', confirmLabel: 'Delete used pins', method: 'delete', url: urls.destroy })
                                    }
                                >
                                    <Trash2 />
                                    Delete used pins
                                </Button>
                            ) : null
                        }
                        columns={[
                            { key: 'sn', header: 'S/N', headerClassName: 'w-16' },
                            { key: 'code', header: 'Pin', sort: (r) => r.code, exportValue: (r) => r.code, cell: (r) => <span className="font-mono text-sm">{r.code}</span> },
                            { key: 'by', header: 'Used by', sort: (r) => r.by ?? '', exportValue: (r) => r.by, cell: (r) => (r.by_url ? <Link href={r.by_url} className="font-medium hover:text-primary">{r.by}</Link> : r.by || '—') },
                            { key: 'by_type', header: 'Type', sort: (r) => r.by_type ?? '', exportValue: (r) => r.by_type, cell: (r) => r.by_type || '—' },
                            { key: 'student', header: 'For student', sort: (r) => r.student ?? '', exportValue: (r) => r.student, cell: (r) => (r.student_url ? <Link href={r.student_url} className="hover:text-primary">{r.student}</Link> : r.student || '—') },
                            { key: 'times', header: 'Times used', sort: (r) => r.times, exportValue: (r) => r.times, cell: (r) => <span className="tabular">{r.times} of 6</span> },
                            { key: 'date', header: 'Last used', sort: (r) => r.date ?? '', exportValue: (r) => r.date, cell: (r) => (r.date ? <span className="tabular">{formatDate(r.date, 'dd/MM/yyyy HH:mm')}</span> : '—') },
                        ]}
                    />
                )}

                {tab === 'create' && (
                    <div className="mx-auto flex w-full max-w-2xl flex-col gap-3">
                        <SetupCard
                            title="Generate pins"
                            description={`Create between 10 and 500 pins at a time. ${valid.length} unused pins exist; up to ${max} can be kept.`}
                            icon={KeyRound}
                            onSubmit={generate}
                            onReset={() => setCount('')}
                            onCancel={() => setTab('valid')}
                            submitLabel="Generate pins"
                            processing={processing}
                        >
                            <Field label="Number of pins" required error={invalid ? 'Enter a whole number from 10 to 500.' : errors.pin_count} span={2}>
                                <input className={`${fieldInput} tabular`} inputMode="numeric" value={count} onChange={(e) => setCount(e.target.value.replace(/\D/g, ''))} placeholder="e.g., 100" autoFocus />
                            </Field>
                        </SetupCard>
                        {room <= 0 && <InfoCallout>There are already {valid.length} unused pins. Use or delete some before generating more.</InfoCallout>}
                    </div>
                )}
            </div>
            {confirmDialog}
        </>
    );
}

PinsIndex.layout = withAppLayout;
