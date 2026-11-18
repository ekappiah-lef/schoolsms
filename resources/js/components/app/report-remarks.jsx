import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Printer, Save } from 'lucide-react';
import { Field, fieldInput } from '@/components/app/module';
import { Button } from '@/components/ui/button';
import http, { submitForm } from '@/lib/http';
import { cn } from '@/lib/utils';

const sentence = (s) => s.charAt(0) + s.slice(1).toLowerCase();

/**
 * A pupil's report remarks (ReportRemarksController@form): benchmark remarks, written assessments, conduct,
 * interests and remarks — whatever the report design of the pupil's class asks for. Read-only for an HOD.
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

    if (!f || !d) return <div className="p-5 text-sm text-fg-muted">Loading…</div>;
    const ro = !f.canWrite;

    const save = async (goNext) => {
        setBusy(true);
        const body = { remarks: JSON.stringify(d.remarks), assessments: JSON.stringify(d.assessments), t_comment: d.t_comment };
        Object.keys(f.fields).forEach((k) => (body[k] = d[k]));
        if (f.canHead) body.p_comment = d.p_comment;
        const r = await submitForm(f.save, body, { method: 'put' });
        setBusy(false);
        if (!r.ok) return toast.error(r.message);
        toast.success(r.message);
        onSaved?.();
        if (goNext) next?.();
    };

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            <datalist id="benchmark-options">
                {f.options.map((o) => (
                    <option key={o} value={o} />
                ))}
            </datalist>
            <fieldset disabled={ro} className="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                <div className="flex flex-col gap-5">
                    {f.areas.map((a) => (
                        <div key={a.area}>
                            <div className="mb-2 text-xs font-semibold uppercase tracking-wide text-fg-muted">{a.area}</div>
                            <div className="grid gap-2">
                                {a.items.map((it) => (
                                    <div key={it.no} className="grid items-center gap-2 sm:grid-cols-[1fr_180px]">
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
                    <div className={cn('grid gap-3', f.areas.length > 0 && 'border-t border-border pt-4')}>
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
                        {(f.canHead || d.p_comment) && (
                            <Field label="Headteacher’s remarks">
                                <textarea className={cn(fieldInput, 'h-16 py-2')} value={d.p_comment} disabled={!f.canHead} onChange={(e) => setD({ ...d, p_comment: e.target.value })} />
                            </Field>
                        )}
                    </div>
                </div>
            </fieldset>
            <div className="flex flex-wrap justify-end gap-2 border-t border-border px-5 py-3">
                {f.print && (
                    <Button variant="ghost" type="button" onClick={() => window.open(f.print, '_blank')}>
                        <Printer />
                        Report
                    </Button>
                )}
                {!ro && (
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
