import { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { BellRing, Save } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { ModuleHeader } from '@/components/app/module';
import { EmptyState, Panel } from '@/components/app/page';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/dialog';
import { DatePicker } from '@/components/ui/date-picker';
import { Select } from '@/components/ui/select';
import { Segmented } from '@/components/ui/tabs';
import { submitForm } from '@/lib/http';
import { cn, formatDate } from '@/lib/utils';

const STATUS = [
    { value: 'present', label: 'Present' },
    { value: 'late', label: 'Late' },
    { value: 'absent', label: 'Absent' },
];

/**
 * Daily register for a class section. Everyone starts as present; mark who is late or absent,
 * save, then send the absence alert to the parents of the absent children.
 */
export default function Attendance({ sections, sectionId, date, saved, students, recent, urls }) {
    const [marks, setMarks] = useState(() => Object.fromEntries(students.map((s) => [s.id, { status: s.status, note: s.note }])));
    const [saving, setSaving] = useState(false);
    const [alertOpen, setAlertOpen] = useState(false);
    const [alerting, setAlerting] = useState(false);
    const go = (params) => router.get(urls.self, { section: sectionId, date, ...params }, { preserveScroll: true });
    const set = (id, k, v) => setMarks((m) => ({ ...m, [id]: { ...m[id], [k]: v } }));
    const counts = useMemo(() => STATUS.map((s) => ({ ...s, n: Object.values(marks).filter((m) => m.status === s.value).length })), [marks]);
    const dirty = students.some((s) => marks[s.id]?.status !== s.status || (marks[s.id]?.note ?? '') !== (s.note ?? ''));
    const toAlert = students.filter((s) => s.status === 'absent' && !s.alerted);
    const section = sections.find((s) => s.id === sectionId);

    const save = async () => {
        setSaving(true);
        const r = await submitForm(urls.save, { section_id: sectionId, date, marks: JSON.stringify(students.map((s) => ({ id: s.id, ...marks[s.id] }))) });
        setSaving(false);
        if (r.ok) {
            toast.success(r.message);
            router.reload({ only: ['students', 'saved', 'recent'] });
        } else toast.error(r.message);
    };
    const alert = async () => {
        setAlerting(true);
        const r = await submitForm(urls.alert, { section_id: sectionId, date });
        setAlerting(false);
        setAlertOpen(false);
        r.ok ? toast.success(r.message) : toast.error(r.message);
        router.reload({ only: ['students'] });
    };

    return (
        <>
            <Head title="Attendance" />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6">
                <ModuleHeader
                    crumbs={['Academics', 'Attendance']}
                    title="Attendance"
                />

                {sections.length ? (
                    <>
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="w-full sm:w-60">
                                <span className="mb-1.5 block text-sm font-medium">Class</span>
                                <Select value={String(sectionId ?? '')} onChange={(v) => go({ section: v })} options={sections.map((s) => ({ value: String(s.id), label: s.name }))} clearable={false} />
                            </div>
                            <div className="w-full sm:w-52">
                                <span className="mb-1.5 block text-sm font-medium">Date</span>
                                <DatePicker value={date} onChange={(v) => v && go({ date: v })} fromYear={2022} />
                            </div>
                            <div className="ml-auto flex flex-wrap gap-2">
                                {saved && toAlert.length > 0 && !dirty && (
                                    <Button onClick={() => setAlertOpen(true)}>
                                        <BellRing />
                                        Send absence alerts ({toAlert.length})
                                    </Button>
                                )}
                                <Button variant="primary" onClick={save} disabled={saving || !students.length || (saved && !dirty)}>
                                    <Save />
                                    {saving ? 'Saving…' : saved ? (dirty ? 'Save changes' : 'Saved') : 'Save register'}
                                </Button>
                            </div>
                        </div>

                        <Panel
                            title={`${section?.name ?? ''} · ${formatDate(date, 'EEEE d MMMM yyyy')}`}
                            description={counts.map((c) => `${c.n} ${c.label.toLowerCase()}`).join(' · ')}
                            actions={
                                students.length ? (
                                    <Button size="sm" variant="ghost" onClick={() => setMarks(Object.fromEntries(students.map((s) => [s.id, { ...marks[s.id], status: 'present' }])))}>
                                        Mark all present
                                    </Button>
                                ) : null
                            }
                            flush
                        >
                            {students.length ? (
                                <ul className="divide-y divide-border">
                                    {students.map((s, i) => {
                                        const m = marks[s.id];
                                        return (
                                            <li key={s.id} className={cn('flex flex-wrap items-center gap-3 px-5 py-2.5', m.status === 'absent' && 'bg-danger-soft/30', m.status === 'late' && 'bg-warning-soft/30')}>
                                                <span className="tabular w-6 text-sm text-fg-subtle">{i + 1}</span>
                                                <Avatar src={s.photo} name={s.name} size="md" />
                                                <div className="min-w-0 flex-1">
                                                    <div className="truncate font-medium">{s.name}</div>
                                                    <div className="text-xs text-fg-muted">{s.adm_no}</div>
                                                </div>
                                                {s.alerted && <Badge tone="info">Parent alerted</Badge>}
                                                {m.status !== 'present' && (
                                                    <input
                                                        className="h-8 w-44 rounded-md border border-border bg-surface px-2 text-sm"
                                                        placeholder="Note (optional)"
                                                        value={m.note ?? ''}
                                                        onChange={(e) => set(s.id, 'note', e.target.value)}
                                                    />
                                                )}
                                                <Segmented size="sm" value={m.status} onChange={(v) => set(s.id, 'status', v)} options={STATUS} />
                                            </li>
                                        );
                                    })}
                                </ul>
                            ) : (
                                <EmptyState compact title="No students in this class" />
                            )}
                        </Panel>

                        {recent.length > 0 && (
                            <Panel title="Recent registers" flush>
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-border bg-canvas text-left text-2xs font-semibold uppercase text-fg-muted">
                                            <th className="h-9 px-5">Date</th>
                                            <th className="h-9 px-3 text-right">Present</th>
                                            <th className="h-9 px-3 text-right">Late</th>
                                            <th className="h-9 px-5 text-right">Absent</th>
                                        </tr>
                                    </thead>
                                    <tbody className="tabular">
                                        {recent.map((r) => (
                                            <tr key={r.date} onClick={() => go({ date: r.date })} className={cn('cursor-pointer border-b border-border last:border-0 hover:bg-muted/50', r.date === date && 'bg-primary-soft/40')}>
                                                <td className="px-5 py-2">{formatDate(r.date, 'EEE dd/MM/yyyy')}</td>
                                                <td className="px-3 py-2 text-right text-success-fg">{r.present}</td>
                                                <td className="px-3 py-2 text-right">{r.late}</td>
                                                <td className={cn('px-5 py-2 text-right', r.absent > 0 && 'font-semibold text-danger-fg')}>{r.absent}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </Panel>
                        )}
                    </>
                ) : (
                    <EmptyState title="No class assigned" description="You can take the register once you are made class teacher of a section." />
                )}
            </div>
            <ConfirmDialog
                open={alertOpen}
                onOpenChange={(o) => !alerting && setAlertOpen(o)}
                title={`Send absence alerts for ${toAlert.length} ${toAlert.length === 1 ? 'child' : 'children'}?`}
                description="Their parents get an SMS (and email) saying the child was not in school, asking after them and hoping to see them soon. Each child is alerted once per day."
                confirmLabel="Send alerts"
                tone="primary"
                loading={alerting}
                onConfirm={alert}
            />
        </>
    );
}

Attendance.layout = withAppLayout;
