import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Building2, Save } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { Field, ModuleHeader, NativeSelect, fieldInput } from '@/components/app/module';
import { PhotoUploader } from '@/components/app/file-uploader';
import { Panel } from '@/components/app/page';
import { Button } from '@/components/ui/button';

/** School settings (super admin): name, year, contacts, exam lock and logo (term dates: School calendar). */
export default function Settings({ settings, logo, years, urls }) {
    const errors = usePage().props.errors ?? {};
    const [data, setData] = useState(settings);
    const [logoFile, setLogoFile] = useState(null);
    const [logoError, setLogoError] = useState(null);
    const [processing, setProcessing] = useState(false);
    const set = (k, v) => setData((d) => ({ ...d, [k]: v }));

    const submit = (e) => {
        e.preventDefault();
        const { term_ends, term_begins, ...rest } = data;
        const body = { ...rest, _method: 'put' };
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
                            <Field label="Boarding facility">
                                <NativeSelect value={String(data.has_boarding || '0')} onChange={(v) => set('has_boarding', v)}>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </NativeSelect>
                            </Field>
                            <Field label="Birthday messages">
                                <NativeSelect value={String(data.birthday_messages || '0')} onChange={(v) => set('birthday_messages', v)}>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </NativeSelect>
                            </Field>
                            <Field label="Fee reminders">
                                <NativeSelect value={String(data.fee_reminders || '0')} onChange={(v) => set('fee_reminders', v)}>
                                    <option value="0">Off</option>
                                    <option value="7">Every week</option>
                                    <option value="14">Every 2 weeks</option>
                                    <option value="30">Every month</option>
                                </NativeSelect>
                            </Field>
                            <Field label="Lock exam results">
                                <NativeSelect value={String(data.lock_exam)} onChange={(v) => set('lock_exam', v)}>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
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
