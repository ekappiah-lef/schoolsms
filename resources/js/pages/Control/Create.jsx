import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { School } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { Field, ModuleHeader, NativeSelect, SetupCard, fieldInput } from '@/components/app/module';
import { submitForm } from '@/lib/http';

const BLANK = { name: '', slug: '', short: '', phone: '', email: '', address: '', admin_name: '', admin_email: '', admin_phone: '', admin_username: '',
    features: [], scheme: 'standard', has_boarding: '0', fee_reminders: '0' };

/** New school: its own copy, database, web address and HTTPS, set up in a few minutes. */
export default function ControlCreate({ features, schemes, baseDomain, urls }) {
    const [d, setD] = useState(BLANK);
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);
    const set = (k, v) => {
        setD((x) => {
            const next = { ...x, [k]: v };
            // Suggest the web name and admin username from the school name until they are typed.
            if (k === 'name' && (!x.slug || x.slug === slugOf(x.name))) next.slug = slugOf(v);
            if ((k === 'name' || k === 'slug') && (!x.admin_username || x.admin_username === `${x.slug}admin`)) next.admin_username = `${k === 'slug' ? v : next.slug}admin`;
            return next;
        });
        setErrors((e) => ({ ...e, [k]: undefined }));
    };
    const toggle = (key) => set('features', d.features.includes(key) ? d.features.filter((f) => f !== key) : [...d.features, key]);

    const submit = async () => {
        setSaving(true);
        const r = await submitForm(urls.store, { ...d, features: JSON.stringify(d.features) }, {});
        setSaving(false);
        if (r.ok) {
            toast.success(r.message);
            router.visit(urls.index);
        } else {
            setErrors(r.errors ?? {});
            toast.error(r.message);
        }
    };

    return (
        <>
            <Head title="New school" />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6">
                <ModuleHeader crumbs={['Control Centre', 'New school']} title="New school" />
                <SetupCard title="School" icon={School} onSubmit={submit} onCancel={() => router.visit(urls.index)} submitLabel={saving ? 'Creating…' : 'Create school'} processing={saving}>
                    <Field label="School name" required error={errors.name} span={2}>
                        <input className={fieldInput} value={d.name} onChange={(e) => set('name', e.target.value)} placeholder="e.g. Desvy International School" autoFocus />
                    </Field>
                    <Field label="Web name" required error={errors.slug}>
                        <div className="flex items-center gap-1">
                            <input className={fieldInput} value={d.slug} onChange={(e) => set('slug', e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, ''))} placeholder="desvy" />
                        </div>
                        <div className="mt-1 text-xs text-fg-muted">https://{d.slug || 'name'}.{baseDomain}</div>
                    </Field>
                    <Field label="Short name" error={errors.short}>
                        <input className={fieldInput} value={d.short} onChange={(e) => set('short', e.target.value)} placeholder="e.g. DIS" maxLength={20} />
                    </Field>
                    <Field label="School phone" error={errors.phone}>
                        <input className={fieldInput} value={d.phone} onChange={(e) => set('phone', e.target.value)} inputMode="tel" />
                    </Field>
                    <Field label="School email" error={errors.email}>
                        <input className={fieldInput} value={d.email} onChange={(e) => set('email', e.target.value)} type="email" />
                    </Field>
                    <Field label="Address" error={errors.address} span={2}>
                        <input className={fieldInput} value={d.address} onChange={(e) => set('address', e.target.value)} />
                    </Field>

                    <div className="col-span-full mt-2 text-sm font-semibold">School admin login</div>
                    <Field label="Admin's name" required error={errors.admin_name}>
                        <input className={fieldInput} value={d.admin_name} onChange={(e) => set('admin_name', e.target.value)} />
                    </Field>
                    <Field label="Admin username" required error={errors.admin_username}>
                        <input className={fieldInput} value={d.admin_username} onChange={(e) => set('admin_username', e.target.value.toLowerCase().replace(/[^a-z0-9_-]/g, ''))} />
                    </Field>
                    <Field label="Admin email" error={errors.admin_email}>
                        <input className={fieldInput} value={d.admin_email} onChange={(e) => set('admin_email', e.target.value)} type="email" />
                    </Field>
                    <Field label="Admin phone" error={errors.admin_phone}>
                        <input className={fieldInput} value={d.admin_phone} onChange={(e) => set('admin_phone', e.target.value)} inputMode="tel" />
                    </Field>

                    <div className="col-span-full mt-2 text-sm font-semibold">How the school works</div>
                    <Field label="Assessment scheme">
                        <NativeSelect value={d.scheme} onChange={(v) => set('scheme', v)}>
                            {Object.entries(schemes).map(([k, label]) => (
                                <option key={k} value={k}>
                                    {k === 'standard' ? 'Standard (1st CA, 2nd CA, Exam)' : label}
                                </option>
                            ))}
                        </NativeSelect>
                    </Field>
                    <Field label="Boarding facility">
                        <NativeSelect value={d.has_boarding} onChange={(v) => set('has_boarding', v)}>
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </NativeSelect>
                    </Field>
                    <Field label="Fee reminders">
                        <NativeSelect value={d.fee_reminders} onChange={(v) => set('fee_reminders', v)}>
                            <option value="0">Off</option>
                            <option value="7">Every week</option>
                            <option value="14">Every 2 weeks</option>
                            <option value="30">Every month</option>
                        </NativeSelect>
                    </Field>
                    <div className="col-span-full">
                        <FeatureList features={features} value={d.features} onToggle={toggle} />
                    </div>
                </SetupCard>
            </div>
        </>
    );
}

ControlCreate.layout = withAppLayout;

function slugOf(name) {
    const words = (name || '').toLowerCase().replace(/[^a-z0-9 ]/g, '').split(/\s+/).filter((w) => w && !['the', 'school', 'international', 'academy', 'college', 'of'].includes(w));
    return (words[0] || '').slice(0, 30);
}

export function FeatureList({ features, value, onToggle }) {
    return (
        <div className="divide-y divide-border rounded-lg border border-border">
            {Object.entries(features).map(([key, text]) => (
                <label key={key} className="flex cursor-pointer items-start gap-3 px-4 py-2.5 hover:bg-muted/50">
                    <input type="checkbox" className="mt-1 size-4" checked={value.includes(key)} onChange={() => onToggle(key)} />
                    <span className="text-sm">{text}</span>
                </label>
            ))}
        </div>
    );
}
