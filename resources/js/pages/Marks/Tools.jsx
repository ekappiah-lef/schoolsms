import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { Mail, Printer, Search, Wrench } from 'lucide-react';
import { ConfirmDialog } from '@/components/ui/dialog';
import { withAppLayout } from '@/layouts/AppLayout';
import { Field, InfoCallout, ModuleHeader, NativeSelect } from '@/components/app/module';
import { EmptyState, Panel } from '@/components/app/page';
import { usePaged } from '@/components/app/data-table';
import { Avatar } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Segmented } from '@/components/ui/tabs';
import { submitForm } from '@/lib/http';
import { ordinal } from '@/lib/utils';

const TOOLS = [
    { value: 'tabulation', label: 'Tabulation sheet' },
    { value: 'sheets', label: 'Results by class' },
    { value: 'fix', label: 'Recalculate totals' },
];

/**
 * Marks tools for the current year: the tabulation sheet of a class, opening each
 * student's results, and recalculating grades, totals and positions.
 */
export default function MarkTools({ tool, year, exams, classes, sections, selected, sheet, students, urls }) {
    const needsExam = tool !== 'sheets';
    const secs = (c) => sections.filter((x) => String(x.class_id) === String(c));
    const [exam, setExam] = useState(String(selected?.exam ?? exams[exams.length - 1]?.id ?? ''));
    const [cls, setCls] = useState(String(selected?.class ?? ''));
    const [sec, setSec] = useState(String(selected?.section ?? ''));
    const [busy, setBusy] = useState(false);
    const ready = cls && sec && (!needsExam || exam);

    const pickClass = (v) => {
        setCls(v);
        setSec(secs(v)[0] ? String(secs(v)[0].id) : '');
    };
    const go = async () => {
        if (tool === 'tabulation') return router.get(`${urls.tabulation}/${exam}/${cls}/${sec}`);
        if (tool === 'sheets') return router.get(`${urls.sheets}/${cls}/${sec}`);
        setBusy(true);
        const r = await submitForm(urls.fixUpdate, { exam_id: exam, my_class_id: cls, section_id: sec }, { method: 'put' });
        setBusy(false);
        r.ok ? toast.success('Grades, totals, averages and positions recalculated.') : toast.error(r.message);
    };

    return (
        <>
            <Head title={TOOLS.find((t) => t.value === tool).label} />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6">
                <ModuleHeader
                    crumbs={['Examinations', TOOLS.find((t) => t.value === tool).label]}
                    title="Results tools"
                    description={`Year ${year}. Marks are entered under Marks entry.`}
                    aside={
                        <Button asChild>
                            <Link href={urls.entry}>Marks entry</Link>
                        </Button>
                    }
                />
                <Segmented value={tool} onChange={(v) => router.visit(urls[v])} options={TOOLS} />

                <Panel>
                    <div className={`grid items-end gap-4 ${needsExam ? 'md:grid-cols-[1fr_1fr_1fr_auto]' : 'md:grid-cols-[1fr_1fr_auto]'}`}>
                        {needsExam && (
                            <Field label="Exam">
                                <NativeSelect value={exam} onChange={setExam}>
                                    <option value="" disabled>
                                        Select exam
                                    </option>
                                    {exams.map((e) => (
                                        <option key={e.id} value={e.id}>
                                            {e.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                        )}
                        <Field label="Class">
                            <NativeSelect value={cls} onChange={pickClass}>
                                <option value="" disabled>
                                    Select class
                                </option>
                                {classes.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                        <Field label="Section">
                            <NativeSelect value={sec} onChange={setSec} disabled={!cls}>
                                {secs(cls).map((x) => (
                                    <option key={x.id} value={x.id}>
                                        {x.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                        <Button variant="primary" onClick={go} disabled={!ready || busy}>
                            {tool === 'fix' ? <Wrench /> : <Search />}
                            {tool === 'fix' ? (busy ? 'Working…' : 'Recalculate') : 'Show'}
                        </Button>
                    </div>
                </Panel>

                {tool === 'fix' && <InfoCallout>Recalculates each subject grade, then every student’s total, average, class average and position for the exam. Use it after changing grades or marks.</InfoCallout>}
                {tool === 'tabulation' && sheet && <Tabulation sheet={sheet} />}
                {tool === 'sheets' && selected && <Students students={students} />}
            </div>
        </>
    );
}

MarkTools.layout = withAppLayout;

function Tabulation({ sheet }) {
    const { shown, pager, offset } = usePaged(sheet.rows, 10, 'students');
    return (
        <Panel
            title={sheet.title}
            flush
            actions={
                <div className="flex gap-2">
                    <EmailClass url={sheet.email} count={sheet.rows.length} />
                    <Button size="sm" asChild>
                        <a href={sheet.print} target="_blank" rel="noreferrer">
                            <Printer />
                            Print
                        </a>
                    </Button>
                </div>
            }
        >
            <div className="scrollbar-thin overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                            <th className="h-9 w-10 px-4 text-left">#</th>
                            <th className="h-9 px-3 text-left">Student</th>
                            {sheet.subjects.map((s) => (
                                <th key={s.id} title={s.name} className="h-9 px-2 text-right">
                                    {s.short.slice(0, 8)}
                                </th>
                            ))}
                            <th className="h-9 px-3 text-right">Total</th>
                            <th className="h-9 px-3 text-right">Average</th>
                            <th className="h-9 px-4 text-right">Position</th>
                        </tr>
                    </thead>
                    <tbody className="tabular">
                        {shown.map((r, i) => (
                            <tr key={r.url} className="border-b border-border last:border-0 hover:bg-muted/50">
                                <td className="px-4 py-2.5 text-fg-subtle">{offset + i + 1}</td>
                                <td className="whitespace-nowrap px-3 py-2.5">
                                    <a href={r.url} className="font-medium hover:text-primary">
                                        {r.name}
                                    </a>
                                </td>
                                {r.scores.map((v, j) => (
                                    <td key={j} className="px-2 py-2.5 text-right">
                                        {v ?? '—'}
                                    </td>
                                ))}
                                <td className="px-3 py-2.5 text-right font-semibold">{r.total ?? '—'}</td>
                                <td className="px-3 py-2.5 text-right">{r.ave ?? '—'}</td>
                                <td className="px-4 py-2.5 text-right">{r.pos ? ordinal(r.pos) : '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {pager}
        </Panel>
    );
}

function Students({ students }) {
    const { shown, pager } = usePaged(students, 10, 'students');
    return (
        <Panel title={`Students (${students.length})`} flush>
            {students.length ? (
                <ul className="divide-y divide-border">
                    {shown.map((s) => (
                        <li key={s.url} className="flex items-center gap-3 px-5 py-2.5">
                            <Avatar src={s.photo} name={s.name} size="md" />
                            <div className="min-w-0 flex-1">
                                <div className="truncate font-medium">{s.name}</div>
                                <div className="text-xs text-fg-muted">{s.adm_no}</div>
                            </div>
                            <Button size="xs" asChild>
                                <a href={s.url}>View results</a>
                            </Button>
                        </li>
                    ))}
                </ul>
            ) : (
                <EmptyState compact title="No students in this class" />
            )}
            {pager}
        </Panel>
    );
}

/** Email every student's report sheet in this class to their parents. */
function EmailClass({ url, count }) {
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const send = async () => {
        setBusy(true);
        const r = await submitForm(url, {});
        setBusy(false);
        setOpen(false);
        r.ok ? toast.success(r.message) : toast.error(r.message);
    };
    return (
        <>
            <Button size="sm" onClick={() => setOpen(true)}>
                <Mail />
                Email reports to class
            </Button>
            <ConfirmDialog
                open={open}
                onOpenChange={(o) => !busy && setOpen(o)}
                title={`Email ${count} report sheets?`}
                description="Each student's report sheet is emailed to their parents as a PDF, with an SMS to say it has been sent."
                confirmLabel="Send reports"
                tone="primary"
                loading={busy}
                onConfirm={send}
            />
        </>
    );
}
