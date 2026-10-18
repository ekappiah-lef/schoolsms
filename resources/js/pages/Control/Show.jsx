import { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { DatabaseBackup, ExternalLink, KeyRound, RotateCw, Save } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { Field, ModuleHeader, NativeSelect, fieldInput } from '@/components/app/module';
import { Panel, StatCard, StatStrip } from '@/components/app/page';
import { Button } from '@/components/ui/button';
import { submitForm } from '@/lib/http';
import { formatDate } from '@/lib/utils';
import { FeatureList } from './Create';
import { JobLog, StatusBadge } from './parts';

const KEY_GROUPS = [
    ['Email', ['MAIL_DRIVER', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_ENCRYPTION', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME']],
    ['SMS', ['SMS_DRIVER', 'SMS_SENDER_ID', 'SMSONLINEGH_API_KEY']],
    ['Demo mode (only these contacts receive messages; clear it to go live)', ['NOTICE_ALLOWLIST']],
    ['MTN MoMo', ['MOMO_DRIVER', 'MOMO_COLLECTION_SUBSCRIPTION_KEY', 'MOMO_API_USER', 'MOMO_API_KEY']],
    ['ClearEnroll', ['CLEARENROLL_API_URL', 'CLEARENROLL_API_KEY']],
    ['WhatsApp', ['WHATSAPP_ENABLED', 'WHATSAPP_TOKEN', 'WHATSAPP_PHONE_NUMBER_ID', 'WHATSAPP_TEMPLATE', 'WHATSAPP_TEMPLATE_LANG']],
    ['Staff clock-in devices', ['STAFF_CLOCK_DEVICES', 'STAFF_CLOCK_TOKEN']],
];

/** One school: what it is running, its switches, scheme and settings, its keys, and server actions. */
export default function ControlShow({ school, live, liveError, logins, features, schemes, jobs, urls }) {
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
                    <StatCard label="Version" value={<span className="font-mono text-base">{live?.version || school.server?.version || '—'}</span>} />
                    <StatCard label="Students" value={live?.counts?.students ?? '—'} context={live ? `${live.counts.staff} staff · ${live.counts.classes} classes` : null} />
                    <StatCard label="Last backup" value={<span className="text-base">{school.server?.last_backup ? formatDate(school.server.last_backup, 'd MMM HH:mm') : '—'}</span>} context={live ? (live.demo_mode ? 'Demo mode' : 'Live') : null} />
                </StatStrip>

                {liveError && <Panel title="School">{<p className="text-sm text-fg-muted">{liveError}</p>}</Panel>}
                {logins && <FirstLogins logins={logins} url={school.url} doneUrl={urls.logins_seen} />}
                {live && <Config live={live} features={features} schemes={schemes} url={urls.config} />}
                {live && <Keys url={urls.keys} />}
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

function Keys({ url }) {
    const [values, setValues] = useState({});
    const [saving, setSaving] = useState(false);
    const save = async () => {
        setSaving(true);
        const r = await submitForm(url, { values: JSON.stringify(values) });
        setSaving(false);
        r.ok ? toast.success(r.message) : toast.error(r.message);
        if (r.ok) {
            setValues({});
            router.reload();
        }
    };
    return (
        <Panel
            title="Keys and messaging"
            actions={
                <Button variant="primary" onClick={save} loading={saving} disabled={!Object.values(values).some((v) => v !== '')}>
                    <Save />
                    Save keys
                </Button>
            }
        >
            <p className="mb-4 text-sm text-fg-muted">Only fill in what you want to change; empty boxes keep the current value. Saving restarts the school.</p>
            <div className="space-y-5">
                {KEY_GROUPS.map(([title, keys]) => (
                    <div key={title}>
                        <div className="mb-2 text-sm font-semibold">{title}</div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {keys.map((k) => (
                                <label key={k} className="block">
                                    <span className="mb-1 block font-mono text-2xs text-fg-muted">{k}</span>
                                    <input
                                        className={fieldInput}
                                        type={/PASSWORD|KEY|TOKEN/.test(k) ? 'password' : 'text'}
                                        autoComplete="off"
                                        value={values[k] ?? ''}
                                        onChange={(e) => setValues((x) => ({ ...x, [k]: e.target.value }))}
                                    />
                                </label>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
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
