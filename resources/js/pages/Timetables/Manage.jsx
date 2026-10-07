import { useMemo, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { Copy, Eye, Pencil, Plus, Printer, Trash2 } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { Field, ModuleHeader, NativeSelect, RowIconButton, fieldInput } from '@/components/app/module';
import { EmptyState, Panel } from '@/components/app/page';
import { useConfirmAction } from '@/components/app/confirm-action';
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
import { Modal } from '@/components/ui/dialog';
import { submitForm } from '@/lib/http';
import { cn, formatDate } from '@/lib/utils';

const HOURS = Array.from({ length: 12 }, (_, i) => String(i + 1));
const MINUTES = ['00', '05', '10', '15', '20', '25', '30', '35', '40', '45', '50', '55'];
const WEEK = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
const blankSlot = { hour_from: '', min_from: '', meridian_from: 'AM', hour_to: '', min_to: '', meridian_to: 'AM', label: '' };
const BREAK_NAMES = ['Break', 'First break', 'Second break', 'Lunch', 'Registration', 'Assembly', 'Worship'];

/**
 * Timetable builder: set the time slots, then click a cell of the grid to put a
 * subject in that slot for that day (or exam date).
 */
export default function TimetableManage({ record, isExam, days, slots, entries, subjects, others, canDelete, editSlot, urls }) {
    const [confirm, confirmDialog] = useConfirmAction();
    const [slotDialog, setSlotDialog] = useState(() => (editSlot ? slots.find((s) => s.id === editSlot) ?? null : null));
    const [cell, setCell] = useState(null); // { entry?, ts_id, day/exam_date }
    const [copyFrom, setCopyFrom] = useState('');

    // Class timetables: Monday–Friday, plus a weekend day if it is used. Exam timetables: the exam dates.
    const columns = useMemo(() => {
        if (isExam) return days;
        const used = new Set(entries.map((e) => e.day));
        return [...WEEK, ...['Saturday', 'Sunday'].filter((d) => used.has(d))];
    }, [isExam, days, entries]);
    const at = (tsId, col) => entries.find((e) => e.ts_id === tsId && (isExam ? e.exam_date === col : e.day === col));
    const reload = () => router.reload({ only: ['slots', 'entries', 'days'], preserveScroll: true });

    return (
        <>
            <Head title={record.name} />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6">
                <ModuleHeader
                    crumbs={['Academics', 'Timetables', 'Build']}
                    title={record.name}
                    description={`${record.class}${record.exam ? ` · ${record.exam}` : ''} · ${record.year}`}
                    aside={
                        <div className="flex flex-wrap gap-2">
                            <Button asChild>
                                <Link href={urls.edit}>
                                    <Pencil />
                                    Details
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href={urls.show}>
                                    <Eye />
                                    View
                                </Link>
                            </Button>
                            <Button asChild>
                                <a href={urls.print} target="_blank" rel="noreferrer">
                                    <Printer />
                                    Print
                                </a>
                            </Button>
                        </div>
                    }
                />

                <div className="grid gap-6 xl:grid-cols-[320px_1fr]">
                    <Panel
                        title={`Time slots (${slots.length})`}
                        flush
                        actions={
                            <Button size="sm" variant="primary" onClick={() => setSlotDialog({})}>
                                <Plus />
                                Add
                            </Button>
                        }
                        footer={
                            others.length ? (
                                <div className="flex items-center gap-2">
                                    <NativeSelect value={copyFrom} onChange={setCopyFrom} className="flex-1">
                                        <option value="">Copy slots from…</option>
                                        {others.map((o) => (
                                            <option key={o.id} value={o.id}>
                                                {o.name}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                    <Button
                                        size="sm"
                                        disabled={!copyFrom}
                                        onClick={() =>
                                            confirm({
                                                title: 'Replace the time slots?',
                                                description: 'The current time slots are removed and replaced with the chosen timetable’s slots.',
                                                confirmLabel: 'Copy slots',
                                                tone: 'primary',
                                                method: 'post',
                                                url: `${urls.useSlots}?ttr_id=${copyFrom}`,
                                            })
                                        }
                                    >
                                        <Copy />
                                        Copy
                                    </Button>
                                </div>
                            ) : null
                        }
                    >
                        {slots.length ? (
                            <ul className="divide-y divide-border">
                                {slots.map((s) => (
                                    <li key={s.id} className="flex items-center gap-2 px-4 py-2 text-sm">
                                        <span className="tabular flex-1">
                                            {s.full}
                                            {s.label && <span className="ml-2 rounded bg-muted px-1.5 py-0.5 text-2xs font-semibold uppercase text-fg-muted">{s.label}</span>}
                                        </span>
                                        <RowIconButton icon={Pencil} title="Edit" onClick={() => setSlotDialog(s)} />
                                        {canDelete && (
                                            <RowIconButton
                                                icon={Trash2}
                                                title="Delete"
                                                tone="danger"
                                                onClick={() => confirm({ title: `Delete ${s.full}?`, description: 'Subjects in this slot are removed from the timetable.', confirmLabel: 'Delete slot', method: 'delete', url: s.urls.destroy })}
                                            />
                                        )}
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <EmptyState compact title="No time slots yet" description="Add the periods of the day first." />
                        )}
                    </Panel>

                    <Panel
                        title="Timetable"
                        description={slots.length ? 'Click a cell to set or change its subject.' : undefined}
                        flush
                        actions={
                            isExam && slots.length ? (
                                <Button size="sm" variant="primary" onClick={() => setCell({ ts_id: (slots.find((x) => !x.label) ?? slots[0]).id, exam_date: '' })}>
                                    <Plus />
                                    Add exam
                                </Button>
                            ) : null
                        }
                    >
                        {slots.length && columns.length ? (
                            <div className="scrollbar-thin overflow-x-auto">
                                <table className="w-full min-w-[640px] table-fixed text-sm">
                                    <thead>
                                        <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                                            <th className="h-9 w-36 px-4 text-left">Time</th>
                                            {columns.map((c) => (
                                                <th key={c} className="h-9 px-2 text-left">
                                                    {isExam ? formatDate(c, 'EEE dd/MM') : c.slice(0, 3)}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {slots.map((s) => (
                                            <tr key={s.id} className="border-b border-border last:border-0">
                                                <td className="tabular px-4 py-1.5 text-xs text-fg-muted">{s.full}</td>
                                                {s.label ? (
                                                    <td colSpan={columns.length} className="bg-muted/50 px-2 py-2.5 text-center text-xs font-semibold uppercase tracking-[0.3em] text-fg-muted">
                                                        {s.label}
                                                    </td>
                                                ) : columns.map((c) => {
                                                    const e = at(s.id, c);
                                                    return (
                                                        <td key={c} className="p-1">
                                                            <button
                                                                type="button"
                                                                onClick={() => setCell({ entry: e, ts_id: s.id, ...(isExam ? { exam_date: c } : { day: c }) })}
                                                                className={cn(
                                                                    'flex h-10 w-full items-center rounded-md px-2 text-left text-xs transition-colors',
                                                                    e ? 'bg-primary-soft/60 font-medium text-primary-hover hover:bg-primary-soft' : 'text-fg-subtle hover:bg-muted',
                                                                )}
                                                            >
                                                                <span className="line-clamp-2">{e ? e.subject : '+'}</span>
                                                            </button>
                                                        </td>
                                                    );
                                                })}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <EmptyState compact title={slots.length ? 'No exam dates yet' : 'Add time slots first'} description={isExam && slots.length ? 'Use “Add exam” to schedule the first paper.' : undefined} />
                        )}
                    </Panel>
                </div>
            </div>

            {slotDialog && <SlotDialog slot={slotDialog} ttrId={record.id} url={urls.storeSlot} onClose={() => setSlotDialog(null)} onSaved={reload} />}
            {cell && <CellDialog cell={cell} isExam={isExam} slots={slots} subjects={subjects} ttrId={record.id} url={urls.storeEntry} canDelete={canDelete} onClose={() => setCell(null)} onSaved={reload} confirm={confirm} />}
            {confirmDialog}
        </>
    );
}

TimetableManage.layout = withAppLayout;

function SlotDialog({ slot, ttrId, url, onClose, onSaved }) {
    const editing = !!slot.id;
    const [d, setD] = useState(() =>
        editing
            ? { hour_from: slot.from.hour, min_from: slot.from.min, meridian_from: slot.from.meridian || 'AM', hour_to: slot.to.hour, min_to: slot.to.min, meridian_to: slot.to.meridian || 'AM', label: slot.label ?? '' }
            : blankSlot,
    );
    const [busy, setBusy] = useState(false);
    const [isBreak, setIsBreak] = useState(!!slot.label);
    const ok = d.hour_from && d.min_from && d.hour_to && d.min_to && (!isBreak || d.label.trim());
    const save = async () => {
        if (`${d.hour_from}:${d.min_from} ${d.meridian_from}` === `${d.hour_to}:${d.min_to} ${d.meridian_to}`) return toast.error('The start and end times are the same.');
        if (editing) {
            // Updating redirects back to the builder with a message.
            router.put(slot.urls.update, { ...d, label: isBreak ? d.label.trim() : '', ttr_id: ttrId }, { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false), onSuccess: onClose });
            return;
        }
        setBusy(true);
        const r = await submitForm(url, { ...d, label: isBreak ? d.label.trim() : '', ttr_id: ttrId });
        setBusy(false);
        if (r.ok && r.data?.ok !== false) {
            toast.success(r.message);
            onClose();
            onSaved();
        } else toast.error(r.data?.msg || r.message);
    };
    const time = (k) => (
        <div className="grid grid-cols-3 gap-2">
            <NativeSelect value={d[`hour_${k}`]} onChange={(v) => setD({ ...d, [`hour_${k}`]: v })}>
                <option value="">Hour</option>
                {HOURS.map((h) => (
                    <option key={h} value={h}>
                        {h}
                    </option>
                ))}
            </NativeSelect>
            <NativeSelect value={d[`min_${k}`]} onChange={(v) => setD({ ...d, [`min_${k}`]: v })}>
                <option value="">Min</option>
                {MINUTES.map((m) => (
                    <option key={m} value={m}>
                        {m}
                    </option>
                ))}
            </NativeSelect>
            <NativeSelect value={d[`meridian_${k}`]} onChange={(v) => setD({ ...d, [`meridian_${k}`]: v })}>
                <option value="AM">AM</option>
                <option value="PM">PM</option>
            </NativeSelect>
        </div>
    );
    return (
        <Modal
            open
            onOpenChange={(o) => !o && onClose()}
            title={editing ? `Edit ${slot.full}` : 'New time slot'}
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button variant="primary" onClick={save} disabled={!ok || busy}>
                        {busy ? 'Saving…' : 'Save slot'}
                    </Button>
                </>
            }
        >
            <div className="grid gap-4">
                <Field label="Starts">{time('from')}</Field>
                <Field label="Ends">{time('to')}</Field>
                <Field label="This slot is">
                    <NativeSelect value={isBreak ? 'break' : 'lesson'} onChange={(v) => setIsBreak(v === 'break')}>
                        <option value="lesson">A lesson period</option>
                        <option value="break">A break (no subject; shown across every day)</option>
                    </NativeSelect>
                </Field>
                {isBreak && (
                    <Field label="Name of the break" required hint="e.g. Break, Registration, Assembly">
                        <input list="break-names" className={fieldInput} value={d.label} onChange={(e) => setD({ ...d, label: e.target.value })} maxLength={40} />
                        <datalist id="break-names">
                            {BREAK_NAMES.map((b) => (
                                <option key={b} value={b} />
                            ))}
                        </datalist>
                    </Field>
                )}
            </div>
        </Modal>
    );
}

function CellDialog({ cell, isExam, slots, subjects, ttrId, url, canDelete, onClose, onSaved, confirm }) {
    const e = cell.entry;
    const [d, setD] = useState({
        subject_id: e ? String(e.subject_id) : '',
        ts_id: String(cell.ts_id),
        ...(isExam ? { exam_date: e?.exam_date ?? cell.exam_date ?? '' } : { day: e?.day ?? cell.day }),
    });
    const [busy, setBusy] = useState(false);
    const ok = d.subject_id && d.ts_id && (isExam ? d.exam_date : d.day);
    const save = async () => {
        const body = { ...d, ttr_id: ttrId };
        if (e) {
            router.put(e.urls.update, body, { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false), onSuccess: onClose });
            return;
        }
        setBusy(true);
        const r = await submitForm(url, body);
        setBusy(false);
        if (r.ok) {
            toast.success(r.message);
            onClose();
            onSaved();
        } else toast.error(r.errors ? Object.values(r.errors)[0] : r.message);
    };
    return (
        <Modal
            open
            onOpenChange={(o) => !o && onClose()}
            title={e ? `Change ${e.subject}` : 'Add subject'}
            footer={
                <>
                    {e && canDelete && (
                        <Button
                            variant="danger-ghost"
                            className="mr-auto"
                            onClick={() => {
                                onClose();
                                confirm({ title: `Remove ${e.subject}?`, description: 'It is taken off the timetable.', confirmLabel: 'Remove', method: 'delete', url: e.urls.destroy });
                            }}
                        >
                            <Trash2 />
                            Remove
                        </Button>
                    )}
                    <Button variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button variant="primary" onClick={save} disabled={!ok || busy}>
                        {busy ? 'Saving…' : 'Save'}
                    </Button>
                </>
            }
        >
            <div className="grid gap-4">
                <Field label="Subject" required>
                    <NativeSelect value={d.subject_id} onChange={(v) => setD({ ...d, subject_id: v })}>
                        <option value="">Choose subject</option>
                        {subjects.map((s) => (
                            <option key={s.id} value={s.id}>
                                {s.name}
                            </option>
                        ))}
                    </NativeSelect>
                </Field>
                {isExam ? (
                    <Field label="Exam date" required>
                        <DatePicker value={d.exam_date} onChange={(v) => setD({ ...d, exam_date: v })} fromYear={2020} toYear={new Date().getFullYear() + 2} />
                    </Field>
                ) : (
                    <Field label="Day" required>
                        <NativeSelect value={d.day} onChange={(v) => setD({ ...d, day: v })}>
                            {[...WEEK, 'Saturday', 'Sunday'].map((x) => (
                                <option key={x} value={x}>
                                    {x}
                                </option>
                            ))}
                        </NativeSelect>
                    </Field>
                )}
                <Field label="Time slot" required>
                    <NativeSelect value={d.ts_id} onChange={(v) => setD({ ...d, ts_id: v })}>
                        {slots
                            .filter((s) => !s.label)
                            .map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.full}
                                </option>
                            ))}
                    </NativeSelect>
                </Field>
            </div>
        </Modal>
    );
}
