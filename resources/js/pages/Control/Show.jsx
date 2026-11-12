import { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { DatabaseBackup, ExternalLink, KeyRound, RotateCw, Save } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { Field, ModuleHeader, NativeSelect, fieldInput } from '@/components/app/module';
import { Panel, StatCard, StatStrip } from '@/components/app/page';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Modal } from '@/components/ui/dialog';
import { submitForm } from '@/lib/http';
import { formatDate } from '@/lib/utils';
import { FeatureList } from './Create';
import { JobLog, StatusBadge } from './parts';

const SECRET = /PASSWORD|_KEY$|TOKEN|API_USER/;

/** Each service the school uses: its fields (key, label) and whether it is set up. */
const SERVICES = [
    {
        title: 'SMS',
        fields: [['SMS_SENDER_ID', 'Sender name'], ['SMSONLINEGH_API_KEY', 'SMSOnlineGH API key'], ['SMS_DRIVER', 'Provider']],
        ready: (k) => k.SMSONLINEGH_API_KEY?.set && k.SMS_DRIVER?.value !== 'log',
        summary: (k) => k.SMS_SENDER_ID?.value,
    },
    {
        title: 'Email',
        fields: [
            ['MAIL_FROM_ADDRESS', 'Sender email'], ['MAIL_FROM_NAME', 'Sender name'], ['MAIL_USERNAME', 'SMTP login'], ['MAIL_PASSWORD', 'SMTP password'],
            ['MAIL_HOST', 'SMTP server'], ['MAIL_PORT', 'Port'], ['MAIL_ENCRYPTION', 'Encryption'], ['MAIL_DRIVER', 'Mailer'],
        ],
        ready: (k) => k.MAIL_PASSWORD?.set && k.MAIL_HOST?.set,
        summary: (k) => k.MAIL_FROM_ADDRESS?.value,
    },
    {
        title: 'Messages to parents',
        fields: [['NOTICE_ALLOWLIST', 'Only these contacts (demo)']],
        ready: (k) => !k.NOTICE_ALLOWLIST?.set,
        on: 'Live',
        off: 'Demo',
        summary: (k) => k.NOTICE_ALLOWLIST?.value,
    },
    {
        title: 'ClearEnroll',
        fields: [['CLEARENROLL_API_URL', 'Address'], ['CLEARENROLL_API_KEY', 'API key']],
        ready: (k) => k.CLEARENROLL_API_KEY?.set,
        summary: (k) => k.CLEARENROLL_API_URL?.value,
    },
    {
        title: 'Paystack',
        fields: [['PAYSTACK_SECRET_KEY', 'Secret key'], ['PAYSTACK_PUBLIC_KEY', 'Public key'], ['PAYSTACK_SUBACCOUNT', 'Subaccount code']],
        ready: (k) => k.PAYSTACK_SECRET_KEY?.set,
        summary: (k) => k.PAYSTACK_SUBACCOUNT?.value,
    },
    {
        title: 'MTN MoMo',
        fields: [['MOMO_DRIVER', 'Mode'], ['MOMO_COLLECTION_SUBSCRIPTION_KEY', 'Subscription key'], ['MOMO_API_USER', 'API user'], ['MOMO_API_KEY', 'API key']],
        ready: (k) => ['sandbox', 'production'].includes(k.MOMO_DRIVER?.value) && k.MOMO_API_KEY?.set,
        summary: (k) => k.MOMO_DRIVER?.value,
    },
    {
        title: 'WhatsApp',
        fields: [
            ['WHATSAPP_ENABLED', 'On (true / false)'], ['WHATSAPP_PROVIDER', 'Provider'], ['WHATSAPP_API_KEY', '360dialog API key'], ['WHATSAPP_TEMPLATE', 'Template'],
            ['WHATSAPP_TEMPLATE_LANG', 'Template language'], ['WHATSAPP_TOKEN', 'Meta token'], ['WHATSAPP_PHONE_NUMBER_ID', 'Meta phone number ID'],
        ],
        ready: (k) => ['true', '1'].includes(String(k.WHATSAPP_ENABLED?.value).toLowerCase()),
        summary: (k) => k.WHATSAPP_PROVIDER?.value,
    },
    {
        title: 'Staff clock-in',
        fields: [['STAFF_CLOCK_DEVICES', 'Devices'], ['STAFF_CLOCK_TOKEN', 'Device token']],
        ready: (k) => k.STAFF_CLOCK_DEVICES?.set,
        summary: (k) => k.STAFF_CLOCK_DEVICES?.value,
    },
];

/** One school: what it is running, its switches, scheme and settings, its keys, and server actions. */
export default function ControlShow({ school, live, liveError, logins, features, schemes, payouts, jobs, urls }) {
    const busy = jobs.some((j) => j.status === 'pending') || ['creating', 'setting_up'].includes(school.status);
    useEffect(() => {
        if (!busy) return undefined;
        const t = setInterval(() => router.reload(), 5000);
        return () => clearInterval(t);
    }, [busy]);

    return (
        <>
            <Head title={school.name} />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6">
                <ModuleHeader
                    crumbs={['Control Centre', 'Schools', school.name]}
                    title={school.name}
                    aside={
                        school.url && (
                            <Button asChild>
                                <a href={school.url} target="_blank" rel="noreferrer">
                                    <ExternalLink />
                                    Open school
                                </a>
                            </Button>
                        )
                    }
                />

                <StatStrip>
                    <StatCard label="Status" value={<StatusBadge status={school.status} healthy={school.server?.healthy} />} context={school.url?.replace('https://', '')} />
                    <StatCard label="Version" value={<span className="text-base">{live?.version || school.server?.version || '—'}</span>} />
                    <StatCard label="Students" value={live?.counts?.students ?? '—'} context={live ? `${live.counts.staff} staff · ${live.counts.classes} classes` : null} />
                    <StatCard label="Last backup" value={<span className="text-base">{school.server?.last_backup ? formatDate(school.server.last_backup, 'd MMM HH:mm') : '—'}</span>} context={live ? (live.demo_mode ? 'Demo mode' : 'Live') : null} />
                </StatStrip>

                {liveError && <Panel title="School">{<p className="text-sm text-fg-muted">{liveError}</p>}</Panel>}
                {logins && <FirstLogins logins={logins} url={school.url} doneUrl={urls.logins_seen} />}
                {live && <Config live={live} features={features} schemes={schemes} url={urls.config} />}
                {live && <Payouts school={school} payouts={payouts} url={urls.payouts} />}
                {live && <Keys url={urls.keys} keys={live.keys ?? {}} />}
                {school.status !== 'creating' && <Actions urls={urls} />}
                {jobs.length > 0 && <JobLog jobs={jobs} />}
            </div>
        </>
    );
}

ControlShow.layout = withAppLayout;

function FirstLogins({ logins, url, doneUrl }) {
    const done = async () => {
        const r = await submitForm(doneUrl, {});
        r.ok ? toast.success(r.message) : toast.error(r.message);
        router.reload();
    };
    return (
        <Panel title="First logins" actions={<Button size="sm" onClick={done}>I have saved them</Button>}>
            <p className="mb-3 text-sm text-fg-muted">Sign in at {url}. Save these now; they are removed from here when you click the button.</p>
            <table className="text-sm">
                <tbody>
                    {logins.map((l) => (
                        <tr key={l.username}>
                            <td className="py-1 pr-6 text-fg-muted">{l.who}</td>
                            <td className="py-1 pr-6 font-mono">{l.username}</td>
                            <td className="py-1 font-mono">{l.password}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </Panel>
    );
}

function Config({ live, features, schemes, url }) {
    const custom = live.scheme === 'custom';
    const [f, setF] = useState(live.features ?? []);
    const [scheme, setScheme] = useState(live.scheme);
    const [json, setJson] = useState(custom ? JSON.stringify(live.scheme_detail, null, 2) : '');
    const [settings, setSettings] = useState(live.settings ?? {});
    const [saving, setSaving] = useState(false);
    const save = async () => {
        setSaving(true);
        const r = await submitForm(url, { features: JSON.stringify(f), scheme: scheme === 'custom' ? json : scheme, settings: JSON.stringify(settings) }, { method: 'put' });
        setSaving(false);
        r.ok ? toast.success(r.message) : toast.error(r.message);
        if (r.ok) router.reload();
    };
    const s = (k, v) => setSettings((x) => ({ ...x, [k]: v }));
    return (
        <Panel
            title="How the school works"
            actions={
                <Button variant="primary" onClick={save} loading={saving}>
                    <Save />
                    Save
                </Button>
            }
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Assessment scheme">
                    <NativeSelect value={scheme} onChange={(v) => { setScheme(v); if (v === 'custom' && !json) setJson(JSON.stringify(live.scheme_detail, null, 2)); }}>
                        {Object.entries(schemes).map(([k, label]) => (
                            <option key={k} value={k}>
                                {k === 'standard' ? 'Standard (1st CA, 2nd CA, Exam)' : label}
                            </option>
                        ))}
                        <option value="custom">Custom (edit below)</option>
                    </NativeSelect>
                </Field>
                <Field label="Boarding facility">
                    <NativeSelect value={String(settings.has_boarding ?? '0')} onChange={(v) => s('has_boarding', v)}>
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </NativeSelect>
                </Field>
                <Field label="Birthday messages">
                    <NativeSelect value={String(settings.birthday_messages ?? '0')} onChange={(v) => s('birthday_messages', v)}>
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </NativeSelect>
                </Field>
                <Field label="Fee reminders">
                    <NativeSelect value={String(settings.fee_reminders ?? '0')} onChange={(v) => s('fee_reminders', v)}>
                        <option value="0">Off</option>
                        <option value="7">Every week</option>
                        <option value="14">Every 2 weeks</option>
                        <option value="30">Every month</option>
                    </NativeSelect>
                </Field>
                <Field label="Lock exam results">
                    <NativeSelect value={String(settings.lock_exam ?? '0')} onChange={(v) => s('lock_exam', v)}>
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </NativeSelect>
                </Field>
                {scheme === 'custom' && (
                    <Field label="Custom scheme" span={2}>
                        <textarea className={`${fieldInput} h-64 font-mono text-xs`} value={json} onChange={(e) => setJson(e.target.value)} spellCheck={false} />
                    </Field>
                )}
                <div className="sm:col-span-2">
                    <FeatureList features={features} value={f} onToggle={(k) => setF((x) => (x.includes(k) ? x.filter((y) => y !== k) : [...x, k]))} />
                </div>
            </div>
        </Panel>
    );
}

function Keys({ url, keys }) {
    const [editing, setEditing] = useState(null);
    return (
        <Panel title="Services" flush>
            <ul>
                {SERVICES.map((svc) => {
                    const ok = svc.ready(keys);
                    const hint = svc.summary(keys);
                    return (
                        <li key={svc.title} className="flex items-center gap-4 border-b border-border px-5 py-3.5 last:border-0">
                            <div className="min-w-0 flex-1">
                                <div className="font-medium">{svc.title}</div>
                                {hint && <div className="truncate text-sm text-fg-muted">{hint}</div>}
                            </div>
                            <Badge tone={ok ? 'success' : 'neutral'}>{ok ? svc.on ?? 'Set up' : svc.off ?? 'Not set'}</Badge>
                            <Button size="sm" onClick={() => setEditing(svc)}>
                                Edit
                            </Button>
                        </li>
                    );
                })}
            </ul>
            {editing && <KeyEditor svc={editing} keys={keys} url={url} onClose={() => setEditing(null)} />}
        </Panel>
    );
}

function KeyEditor({ svc, keys, url, onClose }) {
    const start = Object.fromEntries(svc.fields.map(([k]) => [k, SECRET.test(k) ? '' : keys[k]?.value ?? '']));
    const [values, setValues] = useState(start);
    const [saving, setSaving] = useState(false);
    const changed = svc.fields.map(([k]) => k).filter((k) => values[k] !== start[k]);
    const save = async () => {
        setSaving(true);
        const send = Object.fromEntries(changed.map((k) => [k, values[k]]));
        const clear = changed.filter((k) => values[k] === '');
        const r = await submitForm(url, { values: JSON.stringify(send), ...Object.fromEntries(clear.map((k, i) => [`clear[${i}]`, k])) });
        setSaving(false);
        r.ok ? toast.success(r.message) : toast.error(r.message);
        if (r.ok) {
            onClose();
            router.reload();
        }
    };
    return (
        <Modal
            open
            onOpenChange={(o) => !o && !saving && onClose()}
            title={svc.title}
            footer={
                <>
                    <Button variant="secondary" onClick={onClose} disabled={saving}>
                        Cancel
                    </Button>
                    <Button variant="primary" onClick={save} loading={saving} disabled={!changed.length}>
                        Save
                    </Button>
                </>
            }
        >
            <div className="grid gap-4">
                {svc.fields.map(([k, label]) => (
                    <Field key={k} label={label}>
                        <input
                            className={fieldInput}
                            type={SECRET.test(k) ? 'password' : 'text'}
                            autoComplete="off"
                            placeholder={SECRET.test(k) && keys[k]?.set ? `•••• ${keys[k].hint}` : ''}
                            value={values[k]}
                            onChange={(e) => setValues((x) => ({ ...x, [k]: e.target.value }))}
                        />
                    </Field>
                ))}
            </div>
        </Modal>
    );
}

function Payouts({ school, payouts, url }) {
    const cur = payouts.current;
    const [d, setD] = useState({ business_name: cur?.business_name ?? school.name, bank: '', account_number: '' });
    const [editing, setEditing] = useState(!cur);
    const [saving, setSaving] = useState(false);
    const bankName = (code) => payouts.banks.find((b) => b.code === code)?.name ?? code;
    const save = async () => {
        setSaving(true);
        const r = await submitForm(url, { ...d, code: cur?.code ?? '' });
        setSaving(false);
        r.ok ? toast.success(r.message) : toast.error(r.message);
        if (r.ok) router.reload();
    };
    return (
        <Panel
            title="Payouts"
            actions={payouts.lef.ready && cur && !editing && <Button size="sm" onClick={() => setEditing(true)}>Change</Button>}
        >
            {!payouts.lef.ready ? (
                <p className="text-sm text-fg-muted">Add LEF's Paystack keys on the Schools page.</p>
            ) : (
                <div className="space-y-4">
                    {cur && (
                        <dl className="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                            <div><dt className="text-fg-muted">Paid to</dt><dd className="font-medium">{cur.business_name}</dd></div>
                            <div><dt className="text-fg-muted">Bank or network</dt><dd className="font-medium">{cur.bank}</dd></div>
                            <div><dt className="text-fg-muted">Account</dt><dd className="font-medium">{cur.account_number}</dd></div>
                            <div><dt className="text-fg-muted">LEF share</dt><dd className="font-medium">{cur.percentage_charge ?? 0}% · Paystack fee paid by the school</dd></div>
                            <div><dt className="text-fg-muted">Subaccount</dt><dd className="font-medium">{cur.code}</dd></div>
                            <div><dt className="text-fg-muted">Mode</dt><dd className="font-medium">{payouts.lef.test ? 'Test' : 'Live'}</dd></div>
                        </dl>
                    )}
                    {editing && (
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field label="Name on the account">
                                <input className={fieldInput} value={d.business_name} onChange={(e) => setD({ ...d, business_name: e.target.value })} />
                            </Field>
                            <Field label="Bank or network">
                                <NativeSelect value={d.bank} onChange={(v) => setD({ ...d, bank: v })}>
                                    <option value="">Choose…</option>
                                    {payouts.banks.map((b) => (
                                        <option key={b.code} value={b.code}>{b.name}</option>
                                    ))}
                                </NativeSelect>
                            </Field>
                            <Field label="Account or mobile money number">
                                <input className={fieldInput} value={d.account_number} onChange={(e) => setD({ ...d, account_number: e.target.value })} />
                            </Field>
                            <div className="flex gap-2 sm:col-span-3">
                                <Button variant="primary" onClick={save} loading={saving} disabled={!d.business_name || !d.bank || !d.account_number}>
                                    <Save />
                                    {cur ? 'Save changes' : 'Set up payouts'}
                                </Button>
                                {cur && <Button variant="secondary" onClick={() => setEditing(false)} disabled={saving}>Cancel</Button>}
                            </div>
                        </div>
                    )}
                </div>
            )}
        </Panel>
    );
}

function Actions({ urls }) {
    const [shown, setShown] = useState(null);
    const act = async (type) => {
        const r = await submitForm(urls.action, { type });
        r.ok ? toast.success(r.message) : toast.error(r.message);
        router.reload();
    };
    const reset = async (who) => {
        const r = await submitForm(urls.password, { who });
        if (r.ok) setShown({ username: r.data.username, password: r.data.password });
        else toast.error(r.message);
    };
    return (
        <Panel title="Server">
            <div className="flex flex-wrap gap-2">
                <Button onClick={() => act('restart')}>
                    <RotateCw />
                    Restart school
                </Button>
                <Button onClick={() => act('backup')}>
                    <DatabaseBackup />
                    Back up now
                </Button>
                <Button onClick={() => reset('admin')}>
                    <KeyRound />
                    New password: school admin
                </Button>
                <Button onClick={() => reset('lef')}>
                    <KeyRound />
                    New password: LEF login
                </Button>
            </div>
            {shown && (
                <p className="mt-4 rounded-md bg-muted px-4 py-3 text-sm">
                    <span className="font-mono">{shown.username}</span> now signs in with <span className="font-mono font-semibold">{shown.password}</span>. Save it; it is not shown again.
                </p>
            )}
        </Panel>
    );
}
