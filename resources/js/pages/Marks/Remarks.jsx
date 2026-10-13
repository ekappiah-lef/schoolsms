import { useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { withAppLayout } from '@/layouts/AppLayout';
import { ModuleHeader, fieldInput } from '@/components/app/module';
import { EmptyState } from '@/components/app/page';
import { Avatar } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Select } from '@/components/ui/select';
import { submitForm } from '@/lib/http';
import { cn } from '@/lib/utils';

/** Report card remarks for a class: the class teacher's remark and the head's remark for every student. */
export default function ReportRemarks({ year, exams, sections, exam, section, rows, canTeacher, canHead, urls }) {
    const [data, setData] = useState(rows);
    const [busy, setBusy] = useState(false);
    const [dirty, setDirty] = useState(false);

    useEffect(() => {
        setData(rows);
        setDirty(false);
    }, [rows]);

    const go = (next) => router.get(urls.self, { exam, section, ...next }, { preserveScroll: true });
    const set = (id, key, value) => {
        setData((d) => d.map((r) => (r.id === id ? { ...r, [key]: value } : r)));
        setDirty(true);
    };
    const save = async () => {
        setBusy(true);
        const r = await submitForm(urls.save, { rows: JSON.stringify(data.map(({ id, t_comment, p_comment }) => ({ id, t_comment, p_comment }))) }, { method: 'put' });
        setBusy(false);
        if (r.ok) {
            toast.success(r.message);
            setDirty(false);
        } else {
            toast.error(r.message);
        }
    };

    const area = cn(fieldInput, 'h-16 min-w-[14rem] resize-y py-2 text-sm');

    return (
        <>
            <Head title="Report remarks" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6">
                <ModuleHeader
                    crumbs={['Examinations', 'Report remarks']}
                    title="Report remarks"
                    session={year}
                    aside={
                        <div className="flex flex-wrap gap-2">
                            <Select size="sm" className="w-56" value={exam ? String(exam) : ''} onChange={(v) => go({ exam: v })} options={exams.map((e) => ({ value: String(e.id), label: `${e.name} · Term ${e.term}` }))} placeholder="Exam" />
                            <Select size="sm" className="w-48" value={section ? String(section) : ''} onChange={(v) => go({ section: v })} options={sections.map((s) => ({ value: String(s.id), label: s.label }))} placeholder="Class" />
                        </div>
                    }
                />

                {!exams.length ? (
                    <EmptyState title="No exam this year yet" />
                ) : !sections.length ? (
                    <EmptyState title="You are not the class teacher of any class" />
                ) : !data.length ? (
                    <EmptyState title="No students in this class" />
                ) : (
                    <div className="overflow-hidden rounded-lg bg-surface shadow-card">
                        <div className="scrollbar-thin overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                                        <th className="h-10 w-10 px-5">#</th>
                                        <th className="h-10 px-3">Student</th>
                                        <th className="h-10 px-3 text-right">Average</th>
                                        <th className="h-10 px-3 text-right">Position</th>
                                        <th className="h-10 px-3">Class teacher’s remark</th>
                                        <th className="h-10 px-5">Head’s remark</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.map((r, i) => (
                                        <tr key={r.id} className="border-b border-border align-top last:border-0">
                                            <td className="tabular px-5 py-3 text-fg-muted">{i + 1}</td>
                                            <td className="px-3 py-3">
                                                <Link href={r.results_url} className="flex items-center gap-3 hover:text-primary">
                                                    <Avatar src={r.photo} name={r.student} size="md" />
                                                    <span>
                                                        <span className="block font-semibold">{r.student}</span>
                                                        <span className="block text-xs text-fg-muted">{r.adm_no}</span>
                                                    </span>
                                                </Link>
                                            </td>
                                            <td className="tabular px-3 py-3 text-right">{r.ave ?? '—'}</td>
                                            <td className="tabular px-3 py-3 text-right">{r.pos ?? '—'}</td>
                                            <td className="px-3 py-2">
                                                {canTeacher ? (
                                                    <textarea className={area} maxLength={300} value={r.t_comment} onChange={(e) => set(r.id, 't_comment', e.target.value)} aria-label={`Class teacher’s remark for ${r.student}`} />
                                                ) : (
                                                    r.t_comment || <span className="text-fg-subtle">—</span>
                                                )}
                                            </td>
                                            <td className="px-5 py-2">
                                                {canHead ? (
                                                    <textarea className={area} maxLength={300} value={r.p_comment} onChange={(e) => set(r.id, 'p_comment', e.target.value)} aria-label={`Head’s remark for ${r.student}`} />
                                                ) : (
                                                    r.p_comment || <span className="text-fg-subtle">—</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <div className="flex items-center justify-end gap-3 border-t border-border bg-canvas px-5 py-3">
                            <Button variant="primary" onClick={save} disabled={busy || !dirty}>
                                {busy ? 'Saving…' : 'Save remarks'}
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

ReportRemarks.layout = withAppLayout;
