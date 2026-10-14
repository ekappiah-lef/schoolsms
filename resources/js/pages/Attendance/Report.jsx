import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { FileText, Mail } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { ModuleHeader, RegistryCard, RowIconButton } from '@/components/app/module';
import { EmptyState, Panel, StatCard, StatStrip } from '@/components/app/page';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/dialog';
import { DatePicker } from '@/components/ui/date-picker';
import { Select } from '@/components/ui/select';
import { submitForm } from '@/lib/http';
import { cn, formatDate } from '@/lib/utils';

/**
 * Who was absent, on which day, for a date range and a class or section.
 * Each child's attendance for the range can be opened as a PDF or emailed to the parents.
 */
export default function AttendanceReport({ filters, classes, summary, days, exceptions, students, urls }) {
    const [sendAll, setSendAll] = useState(false);
    const [sending, setSending] = useState(false);
    const go = (change) => router.get(urls.self, { ...filters, ...change }, { preserveScroll: true, preserveState: true });
    const cls = classes.find((c) => c.id === filters.class);
    const scope = cls ? `${cls.name}${filters.section ? ` ${cls.sections.find((s) => s.id === filters.section)?.name ?? ''}` : ''}` : 'All classes';
    const period = filters.from === filters.to ? formatDate(filters.from, 'EEE d MMM yyyy') : `${formatDate(filters.from, 'd MMM')} – ${formatDate(filters.to, 'd MMM yyyy')}`;

    const send = async (url, name) => {
        const r = await submitForm(url, {});
        r.ok ? toast.success(r.message) : toast.error(`${name}: ${r.message}`);
    };
    const doSendAll = async () => {
        setSending(true);
        const r = await submitForm(urls.sendAll, { from: filters.from, to: filters.to, class: filters.class ?? '', section: filters.section ?? '' });
        setSending(false);
        setSendAll(false);
        r.ok ? toast.success(r.message) : toast.error(r.message);
    };

    return (
        <>
            <Head title="Attendance report" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
                <ModuleHeader crumbs={['Academics', 'Attendance']} title="Attendance report" />

                <div className="flex flex-wrap items-end gap-3">
                    <div className="w-full sm:w-44">
                        <span className="mb-1.5 block text-sm font-medium">From</span>
                        <DatePicker value={filters.from} onChange={(v) => v && go({ from: v })} fromYear={2022} />
                    </div>
                    <div className="w-full sm:w-44">
                        <span className="mb-1.5 block text-sm font-medium">To</span>
                        <DatePicker value={filters.to} onChange={(v) => v && go({ to: v })} fromYear={2022} />
                    </div>
                    <div className="w-full sm:w-56">
                        <span className="mb-1.5 block text-sm font-medium">Class</span>
                        <Select
                            value={String(filters.class ?? 'all')}
                            onChange={(v) => go({ class: v && v !== 'all' ? v : null, section: null })}
                            options={[{ value: 'all', label: 'All classes' }, ...classes.map((c) => ({ value: String(c.id), label: c.name }))]}
                            clearable={false}
                        />
                    </div>
                    {cls && cls.sections.length > 1 && (
                        <div className="w-full sm:w-44">
                            <span className="mb-1.5 block text-sm font-medium">Section</span>
                            <Select
                                value={String(filters.section ?? 'all')}
                                onChange={(v) => go({ section: v && v !== 'all' ? v : null })}
                                options={[{ value: 'all', label: 'All sections' }, ...cls.sections.map((s) => ({ value: String(s.id), label: s.name }))]}
                                clearable={false}
                            />
                        </div>
                    )}
                    {students.length > 0 && (
                        <Button className="ml-auto" onClick={() => setSendAll(true)}>
                            <Mail />
                            Email to parents ({students.length})
                        </Button>
                    )}
                </div>

                {summary.marks ? (
                    <>
                        <StatStrip>
                            <StatCard label="School days" value={summary.days} />
                            <StatCard label="Students" value={summary.students} />
                            <StatCard label="Present" value={summary.present} />
                            <StatCard label="Absent" value={summary.absent} />
                        </StatStrip>

                        <Panel
                            title={`Absent · ${scope} · ${period}`}
                            flush
                        >
                            {exceptions.length ? (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b border-border bg-canvas text-left text-2xs font-semibold uppercase text-fg-muted">
                                                <th className="h-9 px-5">Date</th>
                                                <th className="h-9 px-3">Student</th>
                                                <th className="h-9 px-3">Class</th>
                                                <th className="h-9 px-5">Note</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {exceptions.map((e) => (
                                                <tr key={e.id} className="border-b border-border last:border-0">
                                                    <td className="whitespace-nowrap px-5 py-2">{formatDate(e.date, 'EEE dd/MM/yyyy')}</td>
                                                    <td className="px-3 py-2 font-medium">{e.student}</td>
                                                    <td className="px-3 py-2 text-fg-muted">{e.class}</td>
                                                    <td className="px-5 py-2 text-fg-muted">{e.note || ''}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <EmptyState compact title="No one absent" />
                            )}
                        </Panel>

                        <RegistryCard
                            title="By student"
                            rows={students}
                            exportName={`attendance-${filters.from}-${filters.to}`}
                            searchText={(r) => `${r.name} ${r.class}`}
                            columns={[
                                { key: 'sn', header: 'S/N', headerClassName: 'w-16' },
                                {
                                    key: 'name',
                                    header: 'Student',
                                    sort: (r) => r.name,
                                    exportValue: (r) => r.name,
                                    cell: (r) => (r.urls.student ? <Link href={r.urls.student} className="font-semibold hover:text-primary">{r.name}</Link> : r.name),
                                },
                                { key: 'class', header: 'Class', sort: (r) => r.class, exportValue: (r) => r.class, cell: (r) => <span className="text-fg-muted">{r.class}</span> },
                                { key: 'days', header: 'Days', sort: (r) => r.days, exportValue: (r) => r.days, className: 'tabular text-right', headerClassName: 'text-right', cell: (r) => r.days },
                                { key: 'present', header: 'Present', sort: (r) => r.present, exportValue: (r) => r.present, className: 'tabular text-right', headerClassName: 'text-right', cell: (r) => r.present },
                                {
                                    key: 'absent',
                                    header: 'Absent',
                                    sort: (r) => r.absent,
                                    exportValue: (r) => r.absent,
                                    className: 'tabular text-right',
                                    headerClassName: 'text-right',
                                    cell: (r) => <span className={cn(r.absent > 0 && 'font-semibold text-danger-fg')}>{r.absent || ''}</span>,
                                },
                                {
                                    key: 'action',
                                    header: 'Report',
                                    headerClassName: 'text-right',
                                    className: 'text-right',
                                    cell: (r) => (
                                        <div className="flex justify-end gap-0.5">
                                            <RowIconButton icon={FileText} title="Open PDF" onClick={() => window.open(r.urls.pdf, '_blank')} />
                                            <RowIconButton icon={Mail} title="Email to parent" onClick={() => send(r.urls.send, r.name)} />
                                        </div>
                                    ),
                                },
                            ]}
                        />

                        {days.length > 1 && (
                            <Panel title="By day" flush>
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-border bg-canvas text-left text-2xs font-semibold uppercase text-fg-muted">
                                            <th className="h-9 px-5">Date</th>
                                            <th className="h-9 px-3 text-right">Registers</th>
                                            <th className="h-9 px-3 text-right">Present</th>
                                            <th className="h-9 px-5 text-right">Absent</th>
                                        </tr>
                                    </thead>
                                    <tbody className="tabular">
                                        {days.map((d) => (
                                            <tr key={d.date} onClick={() => go({ from: d.date, to: d.date })} className="cursor-pointer border-b border-border last:border-0 hover:bg-muted/50">
                                                <td className="px-5 py-2">{formatDate(d.date, 'EEE dd/MM/yyyy')}</td>
                                                <td className="px-3 py-2 text-right">{d.registers}</td>
                                                <td className="px-3 py-2 text-right text-success-fg">{d.present}</td>
                                                <td className={cn('px-5 py-2 text-right', d.absent > 0 && 'font-semibold text-danger-fg')}>{d.absent}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </Panel>
                        )}
                    </>
                ) : (
                    <EmptyState title="No registers taken" action={<Button onClick={() => router.visit(urls.register)}>Open register</Button>} />
                )}
            </div>
            <ConfirmDialog
                open={sendAll}
                onOpenChange={(o) => !sending && setSendAll(o)}
                title={`Email attendance to ${students.length} ${students.length === 1 ? 'family' : 'families'}?`}
                description={`Each parent gets a PDF of their child's attendance for ${period}.`}
                confirmLabel="Send"
                tone="primary"
                loading={sending}
                onConfirm={doSendAll}
            />
        </>
    );
}

AttendanceReport.layout = withAppLayout;
