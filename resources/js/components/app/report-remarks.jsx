import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Printer, Save } from 'lucide-react';
import { Field, fieldInput } from '@/components/app/module';
import { Button } from '@/components/ui/button';
import http, { submitForm } from '@/lib/http';
import { cn, ordinal } from '@/lib/utils';

const sentence = (s) => s.charAt(0) + s.slice(1).toLowerCase();

/**
 * A pupil's results and report remarks (ReportRemarksController@form), shown in a window from the Tabulation
 * sheet: the subject scores, then what the report design asks for — benchmark remarks, written assessments,
 * conduct, interests, the teacher's and the headteacher's remarks. Each person edits only their own part.
 */
export function ReportRemarks({ url, onSaved, next }) {
    const [f, setF] = useState(null);
    const [d, setD] = useState(null);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        setF(null);
        http.get(url).then(({ data }) => {
            setF(data);
            setD({
                remarks: { ...data.remarks },
                assessments: { ...data.assessments },
                conduct: data.conduct ?? '',
                interests: data.interests ?? '',
                t_comment: data.t_comment ?? '',
                p_comment: data.p_comment ?? '',
            });
        });
    }, [url]);

    if (!f || !d) return <div className="py-6 text-sm text-fg-muted">Loading…</div>;
    const teacher = f.canWrite;
    const head = f.canHead;

    const save = async (goNext) => {
        setBusy(true);
        const body = {};
        if (teacher) {
            Object.assign(body, { remarks: JSON.stringify(d.remarks), assessments: JSON.stringify(d.assessments), t_comment: d.t_comment });
            Object.keys(f.fields).forEach((k) => (body[k] = d[k]));
        }
        if (head) body.p_comment = d.p_comment;
        const r = await submitForm(f.save, body, { method: 'put' });
        setBusy(false);
        if (!r.ok) return toast.error(r.message);
        toast.success(r.message);
        onSaved?.();
        if (goNext) next?.();
    };

    return (
        <div className="flex max-h-[70vh] flex-col">
            <datalist id="benchmark-options">
                {f.options.map((o) => (
                    <option key={o} value={o} />
                ))}
            </datalist>
            <div className="-mx-5 min-h-0 flex-1 overflow-y-auto px-5">
                <div className="flex flex-col gap-5 pb-2">
                    {f.results.subjects.length > 0 && (
                        <div>
                            <div className="mb-2 text-xs font-semibold uppercase tracking-wide text-fg-muted">Results</div>
                            <div className="overflow-x-auto rounded-md border border-border">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                                            <th className="h-8 px-3 text-left">Subject</th>
                                            {f.results.columns.map((c) => (
                                                <th key={c} className="h-8 px-2 text-right">
                                                    {c}
                                                </th>
                                            ))}
                                            <th className="h-8 px-2 text-right">Total</th>
                                            <th className="h-8 px-2 text-center">Grade</th>
                                            {f.results.position && <th className="h-8 px-3 text-right">Position</th>}
                                        </tr>
                                    </thead>
                                    <tbody className="tabular">
                                        {f.results.subjects.map((s) => (
                                            <tr key={s.name} className="border-b border-border last:border-0">
                                                <td className="px-3 py-1.5">{s.name}</td>
                                                {s.scores.map((v, i) => (
                                                    <td key={i} className="px-2 py-1.5 text-right">
                                                        {v ?? '—'}
                                                    </td>
                                                ))}
                                                <td className="px-2 py-1.5 text-right font-semibold">{s.total ?? '—'}</td>
                                                <td className="px-2 py-1.5 text-center">{s.grade ?? '—'}</td>
                                                {f.results.position && <td className="px-3 py-1.5 text-right">{s.pos ? ordinal(s.pos) : '—'}</td>}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <div className="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-sm text-fg-muted">
                                <span>
                                    Total: <span className="font-semibold text-fg">{f.results.total ?? '—'}</span>
                                </span>
                                <span>
                                    Average: <span className="font-semibold text-fg">{f.results.ave ?? '—'}</span>
                                </span>
                            </div>
                        </div>
                    )}

                    <fieldset disabled={!teacher} className="flex flex-col gap-5">
                        {f.areas.map((a) => (
                            <div key={a.area}>
                                <div className="mb-2 text-xs font-semibold uppercase tracking-wide text-fg-muted">{a.area}</div>
                                <div className="grid gap-2">
                                    {a.items.map((it) => (
                                        <div key={it.no} className="grid items-center gap-2 sm:grid-cols-[1fr_200px]">
                                            <span className="text-sm">
                                                <span className="tabular mr-2 text-fg-subtle">{it.no}</span>
                                                {it.text}
                                            </span>
                                            <input
                                                list="benchmark-options"
                                                className={cn(fieldInput, 'h-9')}
                                                value={d.remarks[it.text] ?? ''}
                                                maxLength={60}
                                                onChange={(e) => setD((x) => ({ ...x, remarks: { ...x.remarks, [it.text]: e.target.value } }))}
                                            />
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))}
                        <div className="grid gap-3">
                            <div className="text-xs font-semibold uppercase tracking-wide text-fg-muted">Remarks</div>
                            {f.assessmentNames.map((n) => (
                                <Field key={n} label={sentence(n)}>
                                    <textarea
                                        className={cn(fieldInput, 'h-20 py-2')}
                                        value={d.assessments[n] ?? ''}
                                        maxLength={1000}
                                        onChange={(e) => setD((x) => ({ ...x, assessments: { ...x.assessments, [n]: e.target.value } }))}
                                    />
                                </Field>
                            ))}
                            {Object.entries(f.fields).map(([k, label]) => (
                                <Field key={k} label={sentence(label)}>
                                    <input className={fieldInput} value={d[k]} maxLength={120} onChange={(e) => setD({ ...d, [k]: e.target.value })} />
                                </Field>
                            ))}
                            <Field label="Teacher’s remarks">
                                <textarea className={cn(fieldInput, 'h-16 py-2')} value={d.t_comment} onChange={(e) => setD({ ...d, t_comment: e.target.value })} />
                            </Field>
                        </div>
                    </fieldset>
                    <Field label="Headteacher’s remarks">
                        <textarea className={cn(fieldInput, 'h-16 py-2')} value={d.p_comment} disabled={!head} onChange={(e) => setD({ ...d, p_comment: e.target.value })} />
                    </Field>
                </div>
            </div>
            <div className="-mx-5 mt-3 flex flex-wrap justify-end gap-2 border-t border-border px-5 pt-3">
                {f.print && (
                    <Button variant="ghost" type="button" onClick={() => window.open(f.print, '_blank')}>
                        <Printer />
                        View report
                    </Button>
                )}
                {(teacher || head) && (
                    <>
                        <Button variant="outline" type="button" onClick={() => save(false)} disabled={busy}>
                            <Save />
                            Save
                        </Button>
                        {next && (
                            <Button variant="primary" type="button" onClick={() => save(true)} disabled={busy}>
                                Save and next pupil
                            </Button>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}
