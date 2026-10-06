import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { ChevronDown, Mail, Printer, Save } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { Field, ModuleHeader, NativeSelect, fieldInput } from '@/components/app/module';
import { EmptyState, Panel } from '@/components/app/page';
import { Avatar } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { submitForm } from '@/lib/http';
import { cn, ordinal } from '@/lib/utils';

const dash = (v) => (v === null || v === undefined || v === '' || v === 0 ? '—' : v);

/** A student's results for a year: each exam's scores, comments and skill ratings, with print links. */
export default function MarkSheet({ student, year, years, exams, skills, canComment, canHeadComment }) {
    return (
        <>
            <Head title={`Results · ${student.name}`} />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
                <ModuleHeader
                    crumbs={['Examinations', 'Results']}
                    title="Results"
                    description="Scores for every exam in the year. Print a report card from each exam."
                    aside={
                        years.length > 1 ? (
                            <NativeSelect value={year} onChange={(v) => router.visit(years.find((y) => y.value === v).url)} className="w-40">
                                {years.map((y) => (
                                    <option key={y.value} value={y.value}>
                                        {y.value}
                                    </option>
                                ))}
                            </NativeSelect>
                        ) : null
                    }
                />
                <div className="flex items-center gap-4 rounded-lg bg-surface p-5 shadow-card">
                    <Avatar src={student.photo} name={student.name} size="lg" />
                    <div className="min-w-0 flex-1">
                        {student.profile ? (
                            <Link href={student.profile} className="text-lg font-semibold hover:text-primary">
                                {student.name}
                            </Link>
                        ) : (
                            <div className="text-lg font-semibold">{student.name}</div>
                        )}
                        <div className="text-sm text-fg-muted">
                            {student.class} · {student.adm_no} · {year}
                        </div>
                    </div>
                </div>

                {exams.length ? (
                    exams.map((ex) => <ExamCard key={ex.id} ex={ex} skills={skills} canComment={canComment} canHeadComment={canHeadComment} />)
                ) : (
                    <EmptyState title="No results for this year" />
                )}
            </div>
        </>
    );
}

MarkSheet.layout = withAppLayout;

function ExamCard({ ex, skills, canComment, canHeadComment }) {
    const [open, setOpen] = useState(false);
    const hasSkills = skills.af.length > 0 || skills.ps.length > 0;
    return (
        <Panel
            title={ex.name}
            description={`Total ${dash(ex.total)} · Average ${dash(ex.ave)} · Class average ${dash(ex.class_ave)}${ex.pos ? ` · Position ${ordinal(ex.pos)}` : ''}`}
            flush
            actions={
                <div className="flex gap-2">
                    {ex.urls.email && <EmailReport url={ex.urls.email} />}
                    <Button size="sm" asChild>
                        <a href={ex.urls.print} target="_blank" rel="noreferrer">
                            <Printer />
                            Print report
                        </a>
                    </Button>
                </div>
            }
        >
            <div className="scrollbar-thin overflow-x-auto">
                <table className="w-full text-left text-sm">
                    <thead>
                        <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                            <th className="h-9 px-5">Subject</th>
                            <th className="h-9 px-3 text-right">CA 1 (20)</th>
                            <th className="h-9 px-3 text-right">CA 2 (20)</th>
                            <th className="h-9 px-3 text-right">Exam (60)</th>
                            <th className="h-9 px-3 text-right">Total (100)</th>
                            <th className="h-9 px-3">Grade</th>
                            <th className="h-9 px-3">Position</th>
                            <th className="h-9 px-5">Remark</th>
                        </tr>
                    </thead>
                    <tbody className="tabular">
                        {ex.subjects.map((s) => (
                            <tr key={s.name} className="border-b border-border last:border-0 hover:bg-muted/50">
                                <td className="px-5 py-2.5 font-medium">{s.name}</td>
                                <td className="px-3 py-2.5 text-right">{dash(s.t1)}</td>
                                <td className="px-3 py-2.5 text-right">{dash(s.t2)}</td>
                                <td className="px-3 py-2.5 text-right">{dash(s.exm)}</td>
                                <td className="px-3 py-2.5 text-right font-semibold">{dash(s.total)}</td>
                                <td className="px-3 py-2.5">{s.grade || '—'}</td>
                                <td className="px-3 py-2.5">{s.pos ? ordinal(s.pos) : '—'}</td>
                                <td className="px-5 py-2.5 text-fg-muted">{s.remark || '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {(canComment || ex.t_comment || ex.p_comment) && (
                <div className="border-t border-border">
                    <button type="button" onClick={() => setOpen((o) => !o)} className="flex w-full items-center justify-between px-5 py-3 text-sm font-medium hover:bg-muted/50">
                        {canComment ? `Comments${hasSkills ? ' and skill ratings' : ''}` : 'Comments'}
                        <ChevronDown className={cn('size-4 text-fg-subtle transition-transform', open && 'rotate-180')} />
                    </button>
                    {open && (
                        <div className="grid gap-6 px-5 pb-5 lg:grid-cols-2">
                            {canComment ? <Comments ex={ex} canHead={canHeadComment} /> : <ReadComments ex={ex} />}
                            {canComment && hasSkills && <Skills ex={ex} skills={skills} />}
                        </div>
                    )}
                </div>
            )}
        </Panel>
    );
}

function ReadComments({ ex }) {
    return (
        <dl className="grid gap-3 text-sm">
            <div>
                <dt className="text-fg-muted">Teacher’s comment</dt>
                <dd>{ex.t_comment || '—'}</dd>
            </div>
            <div>
                <dt className="text-fg-muted">Head teacher’s comment</dt>
                <dd>{ex.p_comment || '—'}</dd>
            </div>
        </dl>
    );
}

function Comments({ ex, canHead }) {
    const [d, setD] = useState({ t_comment: ex.t_comment ?? '', p_comment: ex.p_comment ?? '' });
    const [busy, setBusy] = useState(false);
    const save = async () => {
        setBusy(true);
        const r = await submitForm(ex.urls.comment, canHead ? d : { t_comment: d.t_comment }, { method: 'put' });
        setBusy(false);
        r.ok ? toast.success(r.message) : toast.error(r.message);
    };
    return (
        <div className="flex flex-col gap-3">
            <Field label="Teacher’s comment">
                <textarea className={cn(fieldInput, 'h-20 py-2')} value={d.t_comment} onChange={(e) => setD({ ...d, t_comment: e.target.value })} />
            </Field>
            {canHead && (
                <Field label="Head teacher’s comment">
                    <textarea className={cn(fieldInput, 'h-20 py-2')} value={d.p_comment} onChange={(e) => setD({ ...d, p_comment: e.target.value })} />
                </Field>
            )}
            <div className="flex justify-end">
                <Button size="sm" variant="primary" onClick={save} disabled={busy}>
                    <Save />
                    {busy ? 'Saving…' : 'Save comments'}
                </Button>
            </div>
        </div>
    );
}

function Skills({ ex, skills }) {
    return (
        <div className="flex flex-col gap-5">
            {skills.af.length > 0 && <SkillGroup title="Affective traits" names={skills.af} initial={ex.af} url={ex.urls.af} field="af" />}
            {skills.ps.length > 0 && <SkillGroup title="Psychomotor skills" names={skills.ps} initial={ex.ps} url={ex.urls.ps} field="ps" />}
        </div>
    );
}

function SkillGroup({ title, names, initial, url, field }) {
    const [v, setV] = useState(() => names.map((_, i) => initial?.[i] ?? ''));
    const [busy, setBusy] = useState(false);
    const save = async () => {
        setBusy(true);
        const r = await submitForm(url, Object.fromEntries(v.map((x, i) => [`${field}[${i}]`, x])), { method: 'put' });
        setBusy(false);
        r.ok ? toast.success(r.message) : toast.error(r.message);
    };
    return (
        <div>
            <div className="mb-2 flex items-center justify-between">
                <span className="text-sm font-semibold">{title}</span>
                <Button size="xs" onClick={save} disabled={busy}>
                    {busy ? 'Saving…' : 'Save'}
                </Button>
            </div>
            <div className="grid gap-x-4 gap-y-2 sm:grid-cols-2">
                {names.map((n, i) => (
                    <label key={n} className="flex items-center justify-between gap-3 text-sm">
                        <span className="truncate text-fg-muted">{n}</span>
                        <NativeSelect value={String(v[i] ?? '')} onChange={(x) => setV((a) => a.map((y, j) => (j === i ? x : y)))} className="w-20">
                            <option value="">—</option>
                            {[1, 2, 3, 4, 5].map((k) => (
                                <option key={k} value={k}>
                                    {k}
                                </option>
                            ))}
                        </NativeSelect>
                    </label>
                ))}
            </div>
        </div>
    );
}

/** Email this report sheet (PDF) to the parents. */
function EmailReport({ url }) {
    const [busy, setBusy] = useState(false);
    const send = async () => {
        setBusy(true);
        const r = await submitForm(url, {});
        setBusy(false);
        r.ok ? toast.success(r.message) : toast.error(r.message);
    };
    return (
        <Button size="sm" onClick={send} disabled={busy}>
            <Mail />
            {busy ? 'Sending…' : 'Email to parent'}
        </Button>
    );
}
