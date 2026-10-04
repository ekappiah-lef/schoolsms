import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { KeyRound, Save } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { Field, ModuleHeader, fieldInput } from '@/components/app/module';
import { PhotoUploader } from '@/components/app/file-uploader';
import { Panel } from '@/components/app/page';
import { Button } from '@/components/ui/button';

/** My account: change password, and (staff and parents) contact details and photo. */
export default function Account({ me, canEditProfile, urls }) {
    const errors = usePage().props.errors ?? {};
    const [profile, setProfile] = useState({ username: '', email: me.email ?? '', phone: me.phone ?? '', address: me.address ?? '' });
    const [photo, setPhoto] = useState(null);
    const [photoError, setPhotoError] = useState(null);
    const [pass, setPass] = useState({ current_password: '', password: '', password_confirmation: '' });
    const [busy, setBusy] = useState(null);

    const send = (url, body, key, onSuccess) =>
        router.post(url, { ...body, _method: 'put' }, {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => setBusy(key),
            onFinish: () => setBusy(null),
            onSuccess,
        });

    const saveProfile = (e) => {
        e.preventDefault();
        const body = { email: profile.email, phone: profile.phone, address: profile.address };
        if (!me.username && profile.username) body.username = profile.username;
        if (photo) body.photo = photo;
        send(urls.update, body, 'profile', () => setPhoto(null));
    };
    const savePassword = (e) => {
        e.preventDefault();
        send(urls.password, pass, 'password', () => setPass({ current_password: '', password: '', password_confirmation: '' }));
    };
    const mismatch = pass.password_confirmation && pass.password !== pass.password_confirmation;

    return (
        <>
            <Head title="My account" />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6">
                <ModuleHeader crumbs={['Account', 'My account']} title="My account" description={`Signed in as ${me.name}${me.username ? ` (${me.username})` : ''}.`} />

                <div className="grid gap-6 lg:grid-cols-2">
                    {canEditProfile && (
                        <form onSubmit={saveProfile}>
                            <Panel
                                title="Profile"
                                description="Your contact details and photo."
                                footer={
                                    <div className="flex justify-end">
                                        <Button type="submit" variant="primary" disabled={busy === 'profile'}>
                                            <Save />
                                            {busy === 'profile' ? 'Saving…' : 'Save profile'}
                                        </Button>
                                    </div>
                                }
                            >
                                <div className="grid gap-4">
                                    <Field label="Name" hint="Ask an administrator to change your name.">
                                        <input className={fieldInput} value={me.name} disabled />
                                    </Field>
                                    {me.username ? (
                                        <Field label="Username">
                                            <input className={fieldInput} value={me.username} disabled />
                                        </Field>
                                    ) : (
                                        <Field label="Username" error={errors.username} hint="Optional. At least 8 characters; letters, numbers, - and _.">
                                            <input className={fieldInput} value={profile.username} onChange={(e) => setProfile({ ...profile, username: e.target.value })} />
                                        </Field>
                                    )}
                                    <Field label="Email" error={errors.email}>
                                        <input className={fieldInput} type="email" value={profile.email} onChange={(e) => setProfile({ ...profile, email: e.target.value })} />
                                    </Field>
                                    <Field label="Phone" error={errors.phone}>
                                        <input className={fieldInput} inputMode="tel" value={profile.phone} onChange={(e) => setProfile({ ...profile, phone: e.target.value })} />
                                    </Field>
                                    <Field label="Address" required error={errors.address}>
                                        <input className={fieldInput} value={profile.address} onChange={(e) => setProfile({ ...profile, address: e.target.value })} />
                                    </Field>
                                    <Field label="Photo" error={photoError || errors.photo}>
                                        <PhotoUploader value={photo} onChange={setPhoto} currentUrl={me.photo} name={me.name} onError={setPhotoError} />
                                    </Field>
                                </div>
                            </Panel>
                        </form>
                    )}

                    <form onSubmit={savePassword}>
                        <Panel
                            title="Change password"
                            description="Use at least 8 letters and numbers."
                            footer={
                                <div className="flex justify-end">
                                    <Button type="submit" variant="primary" disabled={busy === 'password' || !pass.current_password || !pass.password || mismatch}>
                                        <KeyRound />
                                        {busy === 'password' ? 'Saving…' : 'Change password'}
                                    </Button>
                                </div>
                            }
                        >
                            <div className="grid gap-4">
                                <Field label="Current password" required error={errors.current_password}>
                                    <input className={fieldInput} type="password" autoComplete="current-password" value={pass.current_password} onChange={(e) => setPass({ ...pass, current_password: e.target.value })} />
                                </Field>
                                <Field label="New password" required error={errors.password}>
                                    <input className={fieldInput} type="password" autoComplete="new-password" value={pass.password} onChange={(e) => setPass({ ...pass, password: e.target.value })} />
                                </Field>
                                <Field label="Confirm new password" required error={mismatch ? 'The passwords do not match.' : undefined}>
                                    <input className={fieldInput} type="password" autoComplete="new-password" value={pass.password_confirmation} onChange={(e) => setPass({ ...pass, password_confirmation: e.target.value })} />
                                </Field>
                            </div>
                        </Panel>
                    </form>
                </div>
            </div>
        </>
    );
}

Account.layout = withAppLayout;
