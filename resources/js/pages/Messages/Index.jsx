import { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { Send } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { Field, InfoCallout, ModuleHeader, NativeSelect, fieldInput } from '@/components/app/module';
import { EmptyState, Panel } from '@/components/app/page';
import { usePaged } from '@/components/app/data-table';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { ConfirmDialog } from '@/components/ui/dialog';
import { Segmented } from '@/components/ui/tabs';
import http, { submitForm } from '@/lib/http';
import { cn, formatDate } from '@/lib/utils';

/** Send a message by SMS and/or email to parents (all or one class) or staff (all, teaching, non-teaching). */
export default function Messages({ audiences, counts, classes, history, demo, urls }) {
    const [d, setD] = useState({ audience: 'parents', class_id: '', section_id: '', subject: '', body: '', sms: true, email: true });
    const [reach, setReach] = useState(null);
    const [confirm, setConfirm] = useState(false);
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState({});
    const set = (k, v) => setD((x) => ({ ...x, [k]: v, ...(k === 'class_id' ? { section_id: '' } : {}) }));
    const cls = classes.find((c) => String(c.id) === String(d.class_id));
    const { shown, pager } = usePaged(history, 10, 'messages');

    useEffect(() => {
        if (d.audience === 'class' && !d.class_id) return setReach(null);
        let off = false;
        http.get(urls.count, { params: { audience: d.audience, class_id: d.class_id || undefined, section_id: d.section_id || undefined } })
            .then(({ data }) => !off && setReach(data))
            .catch(() => !off && setReach(null));
        return () => {
            off = true;
        };
    }, [d.audience, d.class_id, d.section_id, urls.count]);

    const sms = d.body.length;
    const parts = Math.max(1, Math.ceil(sms / 160));
    const ready = d.body.trim().length >= 5 && (d.sms || d.email) && (d.audience !== 'class' || d.class_id) && reach?.people > 0;

    const send = async () => {
        setBusy(true);
        const r = await submitForm(urls.send, { ...d, sms: d.sms ? 1 : 0, email: d.email ? 1 : 0 });
        setBusy(false);
        setConfirm(false);
        if (r.ok) {
            toast.success(r.message);
            setD((x) => ({ ...x, subject: '', body: '' }));
            setErrors({});
            router.reload({ only: ['history'] });
        } else {
            setErrors(r.errors ?? {});
            toast.error(r.message);
        }
    };

    return (
        <>
            <Head title="Messages" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
                <ModuleHeader crumbs={['Communication', 'Messages']} title="Messages" description="Send an SMS and email to parents or staff." />

                <div className="grid gap-6 lg:grid-cols-5">
                    <Panel
                        title="New message"
                        className="lg:col-span-3"
                        footer={
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <span className="text-sm text-fg-muted">
                                    {reach ? `${reach.people} ${d.audience.includes('parents') || d.audience === 'class' ? 'families' : 'people'} · ${reach.phones} phones · ${reach.emails} emails` : d.audience === 'class' ? 'Choose a class' : '…'}
                                </span>
                                <Button variant="primary" onClick={() => setConfirm(true)} disabled={!ready || busy}>
                                    <Send />
                                    Send message
                                </Button>
                            </div>
                        }
                    >
                        <div className="grid gap-4">
                            <Field label="Send to" required>
                                <NativeSelect value={d.audience} onChange={(v) => set('audience', v)}>
                                    {audiences.map((a) => (
                                        <option key={a.value} value={a.value}>
                                            {a.label}
                                            {counts[a.value] !== undefined ? ` (${counts[a.value]})` : ''}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                            {d.audience === 'class' && (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field label="Class" required error={errors.class_id}>
                                        <NativeSelect value={String(d.class_id)} onChange={(v) => set('class_id', v)}>
                                            <option value="">Choose class</option>
                                            {classes.map((c) => (
                                                <option key={c.id} value={c.id}>
                                                    {c.name}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </Field>
                                    <Field label="Section">
                                        <NativeSelect value={String(d.section_id)} onChange={(v) => set('section_id', v)} disabled={!cls}>
                                            <option value="">All sections</option>
                                            {(cls?.sections ?? []).map((s) => (
                                                <option key={s.id} value={s.id}>
                                                    {s.name}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </Field>
                                </div>
                            )}
                            <Field label="Send by" error={errors.sms}>
                                <div className="flex gap-5 pt-1 text-sm">
                                    <label className="flex items-center gap-2">
                                        <Checkbox checked={d.sms} onCheckedChange={(v) => set('sms', v === true)} />
                                        SMS
                                    </label>
                                    <label className="flex items-center gap-2">
                                        <Checkbox checked={d.email} onCheckedChange={(v) => set('email', v === true)} />
                                        Email
                                    </label>
                                </div>
                            </Field>
                            {d.email && (
                                <Field label="Email subject" error={errors.subject}>
                                    <input className={fieldInput} value={d.subject} onChange={(e) => set('subject', e.target.value)} placeholder="e.g., PTA meeting on Saturday" maxLength={150} />
                                </Field>
                            )}
                            <Field label="Message" required error={errors.body} hint={d.sms ? `${sms}/1000 characters · ${parts} SMS ${parts === 1 ? 'part' : 'parts'} per phone` : `${sms}/1000 characters`}>
                                <textarea className={cn(fieldInput, 'h-36 py-2')} value={d.body} onChange={(e) => set('body', e.target.value)} maxLength={1000} placeholder="Write your message…" />
                            </Field>
                        </div>
                    </Panel>

                    <div className="flex flex-col gap-4 lg:col-span-2">
                        {demo && <InfoCallout>Demo mode is on: only the contacts in NOTICE_ALLOWLIST actually receive messages; everyone else is counted as held back.</InfoCallout>}
                        <InfoCallout>Parents are reached on every phone and email recorded for the family (parent account, father, mother and guardian), once per family.</InfoCallout>
                    </div>
                </div>

                <Panel title="Sent messages" flush>
                    {history.length ? (
                        <>
                            <ul className="divide-y divide-border">
                                {shown.map((m) => (
                                    <li key={m.id} className="flex flex-col gap-1 px-5 py-3 text-sm">
                                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                                            <span className="font-medium">{m.audience}</span>
                                            <span className="text-xs text-fg-muted">{m.channels.join(' + ')}</span>
                                            <span className="tabular ml-auto text-xs text-fg-muted">
                                                {formatDate(m.date, 'dd/MM/yyyy HH:mm')}
                                                {m.by ? ` · ${m.by}` : ''}
                                            </span>
                                        </div>
                                        {m.subject && <div className="font-medium">{m.subject}</div>}
                                        <p className="line-clamp-2 text-fg-muted">{m.body}</p>
                                        <div className="tabular text-xs text-fg-muted">
                                            {m.recipients} recipients · {m.sent} delivered{m.failed ? ` · ${m.failed} failed` : ''}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                            {pager}
                        </>
                    ) : (
                        <EmptyState compact title="No messages sent yet" />
                    )}
                </Panel>
            </div>
            <ConfirmDialog
                open={confirm}
                onOpenChange={(o) => !busy && setConfirm(o)}
                title={`Send to ${reach?.label ?? ''}?`}
                description={`${reach?.people ?? 0} recipients by ${[d.sms && 'SMS', d.email && 'email'].filter(Boolean).join(' and ')}. This cannot be undone.`}
                confirmLabel="Send message"
                tone="primary"
                loading={busy}
                onConfirm={send}
            />
        </>
    );
}

Messages.layout = withAppLayout;
