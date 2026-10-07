import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Building2, Save } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { Field, ModuleHeader, NativeSelect, fieldInput } from '@/components/app/module';
import { PhotoUploader } from '@/components/app/file-uploader';
import { Panel } from '@/components/app/page';
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';

// Term dates are stored as m/d/Y (read with strtotime on the report cards); the picker uses yyyy-MM-dd.
const toIso = (v) => {
    const m = String(v ?? '').match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
    return m ? `${m[3]}-${m[1].padStart(2, '0')}-${m[2].padStart(2, '0')}` : /^\d{4}-\d{2}-\d{2}$/.test(v ?? '') ? v : '';
};
const fromIso = (v) => {
    const m = String(v ?? '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
    return m ? `${m[2]}/${m[3]}/${m[1]}` : '';
};

/** School settings (super admin): name, year, contacts, term dates, exam lock and logo. */
export default function Settings({ settings, logo, years, urls }) {
    const errors = usePage().props.errors ?? {};
    const [data, setData] = useState({
        ...settings,
        term_ends: toIso(settings.term_ends),
        term_begins: toIso(settings.term_begins),
    });
    const [logoFile, setLogoFile] = useState(null);
    const [logoError, setLogoError] = useState(null);
    const [processing, setProcessing] = useState(false);
    const set = (k, v) => setData((d) => ({ ...d, [k]: v }));

    const submit = (e) => {
        e.preventDefault();
        const body = { ...data, term_ends: fromIso(data.term_ends), term_begins: fromIso(data.term_begins), _method: 'put' };
        if (logoFile) body.logo = logoFile;
        router.post(urls.update, body, {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => setLogoFile(null),
        });
    };

    return (
        <>
            <Head title="Settings" />
            <form onSubmit={submit} className="mx-auto flex w-full max-w-5xl flex-col gap-6">
                <ModuleHeader
                    crumbs={['Administration', 'Settings']}
                    title="School settings"
                    aside={
                        <Button type="submit" variant="primary" disabled={processing}>
                            <Save />
                            {processing ? 'Saving…' : 'Save settings'}
                        </Button>
                    }
                />

                <div className="grid gap-6 lg:grid-cols-5">
                    <Panel title="School" className="lg:col-span-3">
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field label="Name of school" required error={errors.system_name} span={2}>
                                <input className={fieldInput} value={data.system_name} onChange={(e) => set('system_name', e.target.value)} />
                            </Field>
                            <Field label="Acronym" error={errors.system_title}>
                                <input className={fieldInput} value={data.system_title} onChange={(e) => set('system_title', e.target.value)} />
                            </Field>
                            <Field label="Current year" required error={errors.current_session}>
                                <NativeSelect value={data.current_session} onChange={(v) => set('current_session', v)}>
                                    {years.map((y) => (
                                        <option key={y} value={y}>
                                            {y}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                            <Field label="Phone" error={errors.phone}>
                                <input className={fieldInput} value={data.phone} onChange={(e) => set('phone', e.target.value)} inputMode="tel" />
                            </Field>
                            <Field label="Email" error={errors.system_email}>
                                <input className={fieldInput} type="email" value={data.system_email} onChange={(e) => set('system_email', e.target.value)} />
                            </Field>
                            <Field label="Address" required error={errors.address} span={2}>
                                <input className={fieldInput} value={data.address} onChange={(e) => set('address', e.target.value)} />
                            </Field>
                            <Field label="This term ends" error={errors.term_ends}>
                                <DatePicker value={data.term_ends} onChange={(v) => set('term_ends', v)} fromYear={2020} toYear={new Date().getFullYear() + 2} />
                            </Field>
                            <Field label="Next term begins" error={errors.term_begins}>
                                <DatePicker value={data.term_begins} onChange={(v) => set('term_begins', v)} fromYear={2020} toYear={new Date().getFullYear() + 2} />
                            </Field>
                            <Field label="ClearEnroll portal address" error={errors.clearenroll_url} hint="The web address of the school's ClearEnroll portal, e.g. https://…" span={2}>
                                <input className={fieldInput} type="url" value={data.clearenroll_url ?? ''} onChange={(e) => set('clearenroll_url', e.target.value)} placeholder="https://" />
                            </Field>
                            <Field label="Lock exam results" hint="When locked, parents and students cannot see results until they are unlocked." span={2}>
                                <NativeSelect value={String(data.lock_exam)} onChange={(v) => set('lock_exam', v)}>
                                    <option value="0">No, results are visible</option>
                                    <option value="1">Yes, lock results</option>
                                </NativeSelect>
                            </Field>
                        </div>
                    </Panel>

                    <div className="flex flex-col gap-6 lg:col-span-2">
                        <Panel title="Logo">
                            <Field label="School logo" error={logoError || errors.logo}>
                                <PhotoUploader value={logoFile} onChange={setLogoFile} currentUrl={logo} name={data.system_name} onError={setLogoError} camera={false} />
                            </Field>
                        </Panel>
                    </div>
                </div>
               
            </form>
        </>
    );
}

Settings.layout = withAppLayout;
