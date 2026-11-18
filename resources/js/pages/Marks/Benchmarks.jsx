import { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { Printer, Save } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { Field, ModuleHeader, fieldInput } from '@/components/app/module';
import { EmptyState, Panel } from '@/components/app/page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select } from '@/components/ui/select';
import { submitForm } from '@/lib/http';
import { cn } from '@/lib/utils';

/** Benchmarks report (creche, nursery): a remark for each statement, per pupil (BenchmarkController). */
export default function Benchmarks({ sections, exams, sectionId, examId, pupils, pupil, options, fields, canHead, urls }) {
    const blank = (p) => ({
        remarks: { ...(p?.remarks ?? {}) },
        conduct: p?.conduct ?? '',
        interests: p?.interests ?? '',
        t_comment: p?.t_comment ?? '',
        p_comment: p?.p_comment ?? '',
    });
    const [d, setD] = useState(() => blank(pupil));
    const [busy, setBusy] = useState(false);
    useEffect(() => setD(blank(pupil)), [pupil?.id, examId]);

    const go = (params) => router.get(urls.self, { section: sectionId, exam: examId, pupil: pupil?.id, ...params }, { preserveScroll: true });
    const setRemark = (text, v) => setD((x) => ({ ...x, remarks: { ...x.remarks, [text]: v } }));
    const idx = pupils.findIndex((p) => p.id === pupil?.id);

    const save = async (next) => {
        setBusy(true);
        const body = { remarks: JSON.stringify(d.remarks), t_comment: d.t_comment };
        Object.keys(fields).forEach((k) => (body[k] = d[k]));
        if (canHead) body.p_comment = d.p_comment;
        const r = await submitForm(urls.save, body, { method: 'put' });
        setBusy(false);
        if (!r.ok) return toast.error(r.message);
        toast.success(r.message);
        next ? go({ pupil: next.id }) : router.reload({ only: ['pupils', 'pupil'] });
    };

    return (
        <>
            <Head title="Benchmarks" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
                <ModuleHeader crumbs={['Academics', 'Examinations', 'Benchmarks']} title="Benchmarks" />

                {!sections.length ? (
                    <EmptyState title="No classes use the benchmarks report" />
                ) : (
                    <>
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="w-full sm:w-60">
                                <span className="mb-1.5 block text-sm font-medium">Class</span>
                                <Select value={String(sectionId ?? '')} onChange={(v) => go({ section: v, pupil: null })} options={sections.map((s) => ({ value: String(s.id), label: s.name }))} clearable={false} />
                            </div>
                            <div className="w-full sm:w-60">
                                <span className="mb-1.5 block text-sm font-medium">Exam</span>
                                <Select value={String(examId ?? '')} onChange={(v) => go({ exam: v })} options={exams.map((e) => ({ value: String(e.id), label: e.name }))} clearable={false} />
                            </div>
                        </div>

                        <div className="grid gap-6 lg:grid-cols-[260px_1fr]">
                            <Panel title={`Pupils (${pupils.length})`} flush>
                                <ul className="max-h-[70vh] overflow-y-auto">
                                    {pupils.map((p) => (
                                        <li key={p.id}>
                                            <button
                                                type="button"
                                                onClick={() => go({ pupil: p.id })}
                                                className={cn('flex w-full items-center justify-between gap-2 border-b border-border px-4 py-2.5 text-left text-sm hover:bg-muted/60', p.id === pupil?.id && 'bg-primary/10 font-semibold')}
                                            >
                                                <span className="truncate">{p.name}</span>
                                                <Badge tone={p.done >= p.of && p.of ? 'success' : p.done ? 'warning' : 'neutral'}>
                                                    {p.done}/{p.of}
                                                </Badge>
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            </Panel>

                            {pupil ? (
                                <Panel
                                    title={pupil.name}
                                    actions={
                                        pupil.print && (
                                            <Button size="sm" variant="outline" onClick={() => window.open(pupil.print, '_blank')}>
                                                <Printer />
                                                Report
                                            </Button>
                                        )
                                    }
                                >
                                    <datalist id="benchmark-options">
                                        {options.map((o) => (
                                            <option key={o} value={o} />
                                        ))}
                                    </datalist>
                                    <div className="flex flex-col gap-5">
                                        {pupil.areas.map((a) => (
                                            <div key={a.area}>
                                                <div className="mb-2 text-xs font-semibold uppercase tracking-wide text-fg-muted">{a.area}</div>
                                                <div className="grid gap-2">
                                                    {a.items.map((it) => (
                                                        <div key={it.no} className="grid items-center gap-2 sm:grid-cols-[1fr_220px]">
                                                            <span className="text-sm">
                                                                <span className="tabular mr-2 text-fg-subtle">{it.no}</span>
                                                                {it.text}
                                                            </span>
                                                            <input list="benchmark-options" className={cn(fieldInput, 'h-9')} value={d.remarks[it.text] ?? ''} maxLength={60} onChange={(e) => setRemark(it.text, e.target.value)} />
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        ))}

                                        <div className="grid gap-3 border-t border-border pt-4">
                                            {Object.entries(fields).map(([k, label]) => (
                                                <Field key={k} label={label.charAt(0) + label.slice(1).toLowerCase()}>
                                                    <input className={fieldInput} value={d[k]} maxLength={120} onChange={(e) => setD({ ...d, [k]: e.target.value })} />
                                                </Field>
                                            ))}
                                            <Field label="Teacher’s remarks">
                                                <textarea className={cn(fieldInput, 'h-16 py-2')} value={d.t_comment} onChange={(e) => setD({ ...d, t_comment: e.target.value })} />
                                            </Field>
                                            {canHead && (
                                                <Field label="Headteacher’s remarks">
                                                    <textarea className={cn(fieldInput, 'h-16 py-2')} value={d.p_comment} onChange={(e) => setD({ ...d, p_comment: e.target.value })} />
                                                </Field>
                                            )}
                                        </div>

                                        <div className="flex justify-end gap-2">
                                            <Button variant="outline" onClick={() => save()} disabled={busy}>
                                                <Save />
                                                Save
                                            </Button>
                                            {idx >= 0 && idx < pupils.length - 1 && (
                                                <Button variant="primary" onClick={() => save(pupils[idx + 1])} disabled={busy}>
                                                    Save and next pupil
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </Panel>
                            ) : (
                                <EmptyState title="No pupils in this class" />
                            )}
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

Benchmarks.layout = withAppLayout;
