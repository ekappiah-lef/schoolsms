import { useEffect, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { Save } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { Field, InfoCallout, ModuleHeader, fieldInput } from '@/components/app/module';
import { EmptyState, Panel } from '@/components/app/page';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { ConfirmDialog } from '@/components/ui/dialog';
import http, { submitForm } from '@/lib/http';
import { cn } from '@/lib/utils';

const toLines = (s) => s.split('\n').map((x) => x.trim()).filter(Boolean);

/**
 * Report-card design per class type (Creche, Nursery, KG, Primary, JHS …): title, subject-table
 * columns, rating tables and their items, attendance, comments, next-term lines, signatures.
 * Live preview on the right. The academic admin and admins edit; class teachers can only view.
 */
export default function ReportDesign({ types, type, template, standard, canEdit, urls }) {
    const [t, setT] = useState(template);
    const [busy, setBusy] = useState(false);
    const [confirmReset, setConfirmReset] = useState(false);
    const [html, setHtml] = useState('');
    const dirty = JSON.stringify(t) !== JSON.stringify(template);
    const timer = useRef();

    useEffect(() => setT(template), [template]);

    // Live preview: the real report sheet with a sample student and the design as it is now.
    useEffect(() => {
        if (!urls) return;
        clearTimeout(timer.current);
        timer.current = setTimeout(() => {
            const body = new FormData();
            body.append('template', JSON.stringify(t));
            http.post(urls.preview, body, { responseType: 'text' })
                .then(({ data }) => setHtml(data))
                .catch(() => setHtml('<p style="font-family:sans-serif;padding:16px">Preview not available.</p>'));
        }, 400);
        return () => clearTimeout(timer.current);
    }, [t, urls]);

    if (!type) {
        return (
            <>
                <Head title="Report cards" />
                <EmptyState title="No report card to show" description="You are not the class teacher of any class." />
            </>
        );
    }

    const set = (k, v) => setT((x) => ({ ...x, [k]: v }));
    const setCol = (key, k, v) => set('columns', t.columns.map((c) => (c.key === key ? { ...c, [k]: v } : c)));
    const setGroup = (key, k, v) => set('groups', t.groups.map((g) => (g.key === key ? { ...g, [k]: v } : g)));
    const setComment = (k, v) => set('comments', { ...t.comments, [k]: v });
    const ro = !canEdit;

    const save = async () => {
        setBusy(true);
        const r = await submitForm(urls.save, { template: JSON.stringify(t) });
        setBusy(false);
        if (r.ok) {
            toast.success(r.message);
            router.reload({ only: ['template'] });
        } else toast.error(r.message);
    };

    return (
        <>
            <Head title="Report cards" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6">
                <ModuleHeader
                    crumbs={['Examinations', 'Report cards']}
                    title="Report cards"
                    aside={
                        canEdit && (
                            <div className="flex gap-2">
                                <Button onClick={() => setConfirmReset(true)} disabled={busy}>
                                    Use the standard design
                                </Button>
                                <Button variant="primary" onClick={save} disabled={busy || !dirty}>
                                    <Save />
                                    {busy ? 'Saving…' : 'Save design'}
                                </Button>
                            </div>
                        )
                    }
                />

                <div className="flex flex-wrap gap-2">
                    {types.map((x) => (
                        <Link
                            key={x.id}
                            href={x.url}
                            preserveScroll
                            className={cn('rounded-md px-3 py-1.5 text-sm font-medium', x.id === type.id ? 'bg-primary text-white' : 'bg-surface shadow-field hover:bg-muted')}
                        >
                            {x.name}
                        </Link>
                    ))}
                </div>

                {ro && <InfoCallout>You can view the report card of your class. The academic admin changes the design.</InfoCallout>}

                <div className="grid gap-6 xl:grid-cols-[minmax(0,440px)_1fr]">
                    <fieldset disabled={ro} className="flex min-w-0 flex-col gap-6">
                        <Panel title="Title and footer">
                            <div className="grid gap-4">
                                <Field label="Report title">
                                    <input className={fieldInput} value={t.title} onChange={(e) => set('title', e.target.value)} maxLength={80} />
                                </Field>
                                <Field label="Footer note" hint="Printed at the bottom, e.g. a motto or school rules.">
                                    <input className={fieldInput} value={t.footer} onChange={(e) => set('footer', e.target.value)} maxLength={200} />
                                </Field>
                            </div>
                        </Panel>

                        <Panel title="Subject table" description="Tick the columns to show and rename them.">
                            <div className="grid gap-2">
                                {t.columns.map((c) => (
                                    <div key={c.key} className="flex items-center gap-3">
                                        <Checkbox checked={c.show} onCheckedChange={(v) => setCol(c.key, 'show', v === true)} />
                                        <input className={cn(fieldInput, 'h-9 flex-1')} value={c.label} onChange={(e) => setCol(c.key, 'label', e.target.value)} maxLength={40} />
                                    </div>
                                ))}
                                <label className="mt-2 flex items-center gap-3 text-sm">
                                    <Checkbox checked={t.summary} onCheckedChange={(v) => set('summary', v === true)} />
                                    Totals row (total scores, average, class average)
                                </label>
                            </div>
                        </Panel>

                        {t.groups.map((g) => (
                            <Panel key={g.key} title={g.key === 'af' ? 'Behaviour ratings' : 'Skills ratings'}>
                                <div className="grid gap-3">
                                    <label className="flex items-center gap-3 text-sm">
                                        <Checkbox checked={g.show} onCheckedChange={(v) => setGroup(g.key, 'show', v === true)} />
                                        Show this table
                                    </label>
                                    {g.show && (
                                        <>
                                            <Field label="Table heading">
                                                <input className={fieldInput} value={g.title} onChange={(e) => setGroup(g.key, 'title', e.target.value)} maxLength={40} />
                                            </Field>
                                            <Field label="Items rated (one per line)" hint="Teachers rate these on each student's results page, in this order. Set the list before teachers start rating; changing it later moves their ratings to other items.">
                                                <textarea
                                                    className={cn(fieldInput, 'h-40 py-2')}
                                                    value={g.items.join('\n')}
                                                    onChange={(e) => setGroup(g.key, 'items', e.target.value.split('\n'))}
                                                    onBlur={(e) => setGroup(g.key, 'items', toLines(e.target.value))}
                                                />
                                            </Field>
                                        </>
                                    )}
                                </div>
                            </Panel>
                        ))}

                        <Panel title="Rating key">
                            <div className="grid gap-3">
                                <label className="flex items-center gap-3 text-sm">
                                    <Checkbox checked={t.key.show} onCheckedChange={(v) => set('key', { ...t.key, show: v === true })} />
                                    Show the key
                                </label>
                                {t.key.show && (
                                    <textarea
                                        className={cn(fieldInput, 'h-28 py-2')}
                                        value={t.key.scale.join('\n')}
                                        onChange={(e) => set('key', { ...t.key, scale: e.target.value.split('\n') })}
                                        onBlur={(e) => set('key', { ...t.key, scale: toLines(e.target.value) })}
                                    />
                                )}
                            </div>
                        </Panel>

                        <Panel title="Other sections">
                            <div className="grid gap-3 text-sm">
                                <label className="flex items-center gap-3">
                                    <Checkbox checked={t.attendance} onCheckedChange={(v) => set('attendance', v === true)} />
                                    Attendance for the term (from the register)
                                </label>
                                <div className="flex items-center gap-3">
                                    <Checkbox checked={t.comments.teacher} onCheckedChange={(v) => setComment('teacher', v === true)} />
                                    <input className={cn(fieldInput, 'h-9 flex-1')} value={t.comments.teacher_label} onChange={(e) => setComment('teacher_label', e.target.value)} maxLength={60} />
                                </div>
                                <div className="flex items-center gap-3">
                                    <Checkbox checked={t.comments.head} onCheckedChange={(v) => setComment('head', v === true)} />
                                    <input className={cn(fieldInput, 'h-9 flex-1')} value={t.comments.head_label} onChange={(e) => setComment('head_label', e.target.value)} maxLength={60} />
                                </div>
                                <label className="flex items-center gap-3">
                                    <Checkbox checked={t.comments.next_term_begins} onCheckedChange={(v) => setComment('next_term_begins', v === true)} />
                                    Next term begins
                                </label>
                                <label className="flex items-center gap-3">
                                    <Checkbox checked={t.comments.next_term_fees} onCheckedChange={(v) => setComment('next_term_fees', v === true)} />
                                    Next term fees
                                </label>
                            </div>
                        </Panel>

                        <Panel title="Signature lines">
                            <Field label="One per line (up to 4)" hint="e.g. Class teacher, Head teacher, Parent">
                                <textarea
                                    className={cn(fieldInput, 'h-24 py-2')}
                                    value={t.signatures.join('\n')}
                                    onChange={(e) => set('signatures', e.target.value.split('\n'))}
                                    onBlur={(e) => set('signatures', toLines(e.target.value).slice(0, 4))}
                                />
                            </Field>
                        </Panel>
                    </fieldset>

                    <div className="min-w-0 xl:sticky xl:top-4 xl:self-start">
                        <div className="overflow-hidden rounded-lg bg-white shadow-card">
                            <div className="border-b border-border px-4 py-2 text-xs font-medium text-fg-muted">
                                Preview · {type.name} · sample student{dirty && canEdit ? ' · not saved yet' : ''}
                            </div>
                            <iframe title="Report card preview" srcDoc={html} className="h-[80vh] w-full border-0 bg-white" />
                        </div>
                    </div>
                </div>
            </div>
            <ConfirmDialog
                open={confirmReset}
                onOpenChange={setConfirmReset}
                title={`Use the standard design for ${type.name}?`}
                description="The form is filled with the standard report card. Nothing is saved until you click Save design."
                confirmLabel="Use standard"
                tone="primary"
                onConfirm={() => {
                    setT(standard);
                    setConfirmReset(false);
                }}
            />
        </>
    );
}

ReportDesign.layout = withAppLayout;
