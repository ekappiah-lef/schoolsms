import { useEffect, useMemo, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { Eye, KeyRound, Pencil, Trash2, UserPlus } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { Field, InfoCallout, ModuleHeader, ModuleTabs, NativeSelect, RegistryCard, RowIconButton, SetupCard, fieldInput, useModuleTab } from '@/components/app/module';
import { useConfirmAction } from '@/components/app/confirm-action';
import { PhotoUploader } from '@/components/app/file-uploader';
import { Avatar } from '@/components/ui/avatar';
import { Combobox } from '@/components/ui/select';
import { DatePicker } from '@/components/ui/date-picker';
import { Segmented } from '@/components/ui/tabs';
import { useModuleForm } from '@/lib/use-module-form';
import http from '@/lib/http';
import { cn } from '@/lib/utils';

const BLANK = { user_type: '', name: '', address: '', email: '', username: '', phone: '', phone2: '', emp_date: '', password: '', gender: '', nal_id: '', state_id: '', lga_id: '', bg_id: '', photo: null };
// When editing, the type, username, password and photo are never sent unless changed
// (an empty photo or password would overwrite the saved one).
const EDIT_BLANK = { name: '', address: '', email: '', phone: '', phone2: '', emp_date: '', gender: '', nal_id: '', state_id: '', lga_id: '', bg_id: '' };

/** Users — Create user / Show users (UserController; JSON store & update). Students are managed under Students. */
export default function UsersIndex({ userTypes, users, options, editing, urls }) {
    const [tab, setTab] = useModuleTab(editing ? 'create' : 'list');
    const [type, setType] = useState('all');
    const [confirm, confirmDialog] = useConfirmAction();
    const [photoError, setPhotoError] = useState(null);

    const form = useModuleForm({
        initial: editing ? EDIT_BLANK : BLANK,
        editing,
        storeUrl: urls.store,
        indexUrl: urls.index,
        only: ['users'],
        onCreated: () => setTab('list'),
        validate: (d) => ({
            ...(!editing && !d.user_type ? { user_type: 'Choose the type of user.' } : {}),
            ...(d.name.trim().length < 6 ? { name: 'Enter the full name (at least 6 characters).' } : {}),
            ...(d.address.trim().length < 6 ? { address: 'Enter the address (at least 6 characters).' } : {}),
            ...(!d.gender ? { gender: 'Choose a gender.' } : {}),
            ...(!d.nal_id ? { nal_id: 'Choose a nationality.' } : {}),
            ...(!d.state_id ? { state_id: 'Choose a state.' } : {}),
            ...(!d.lga_id ? { lga_id: 'Choose an LGA.' } : {}),
            ...(d.email && !/^\S+@\S+\.\S+$/.test(d.email) ? { email: 'Enter a valid email address.' } : {}),
        }),
    });
    const lgas = useLgas(urls.lgas, form.data.state_id, editing?.lgas);
    const set = (k, v) => {
        form.set(k, v);
        if (k === 'state_id') form.set('lga_id', '');
    };

    const typeTitle = editing ? editing.type : userTypes.find((t) => t.id === form.data.user_type)?.title;
    const isAdminType = ['super_admin', 'admin'].includes(typeTitle);
    const counts = useMemo(() => Object.fromEntries(userTypes.map((t) => [t.title, users.filter((u) => u.type === t.title).length])), [userTypes, users]);
    const rows = type === 'all' ? users : users.filter((u) => u.type === type);
    const opts = (list) => list.map((o) => ({ value: o.id, label: o.name }));

    return (
        <>
            <Head title="Users" />
            <div className={cn('mx-auto flex w-full flex-col gap-6', tab === 'create' ? 'max-w-4xl' : 'max-w-7xl')}>
                <ModuleHeader crumbs={['People', 'Users']} title="Users" />
                <ModuleTabs value={tab} onChange={(t) => (editing && t === 'list' ? router.visit(urls.index) : setTab(t))} createLabel="User" listLabel="Show Users" count={users.length} editing={!!editing} />

                {tab === 'create' ? (
                    <div className="flex flex-col gap-3">
                        <SetupCard
                            title={editing ? `Edit ${editing.name}` : 'New User'}
                            description={editing ? `${editing.type_name} account. The type of user cannot be changed.` : 'Create a staff or parent account. Students are admitted under Students.'}
                            icon={UserPlus}
                            onSubmit={form.submit}
                            onReset={form.reset}
                            onCancel={() => (editing ? router.visit(urls.index) : setTab('list'))}
                            submitLabel={editing ? 'Save User' : 'Create User'}
                            processing={form.processing}
                        >
                            {!editing && (
                                <Field label="Type of user" required error={form.errors.user_type}>
                                    <NativeSelect value={form.data.user_type} onChange={(v) => form.set('user_type', v)} invalid={!!form.errors.user_type}>
                                        <option value="" disabled>
                                            Select type
                                        </option>
                                        {userTypes.map((t) => (
                                            <option key={t.id} value={t.id}>
                                                {t.name}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </Field>
                            )}
                            <Field label="Full name" required error={form.errors.name} span={editing ? 2 : 1}>
                                <input className={fieldInput} value={form.data.name} onChange={(e) => form.set('name', e.target.value)} placeholder="e.g., Mercy Asamoah" autoFocus />
                            </Field>
                            <Field label="Gender" required error={form.errors.gender}>
                                <NativeSelect value={form.data.gender} onChange={(v) => form.set('gender', v)} invalid={!!form.errors.gender}>
                                    <option value="" disabled>
                                        Select gender
                                    </option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </NativeSelect>
                            </Field>
                            <Field label="Date of employment" error={form.errors.emp_date} hint="Staff only.">
                                <DatePicker value={form.data.emp_date} onChange={(v) => form.set('emp_date', v)} fromYear={1980} />
                            </Field>
                            <Field label="Phone" error={form.errors.phone}>
                                <input className={fieldInput} value={form.data.phone} onChange={(e) => form.set('phone', e.target.value)} inputMode="tel" placeholder="e.g., 0241234567" />
                            </Field>
                            <Field label="Other phone" error={form.errors.phone2}>
                                <input className={fieldInput} value={form.data.phone2} onChange={(e) => form.set('phone2', e.target.value)} inputMode="tel" />
                            </Field>
                            <Field label="Email" error={form.errors.email}>
                                <input className={fieldInput} type="email" value={form.data.email} onChange={(e) => form.set('email', e.target.value)} placeholder="name@example.com" />
                            </Field>
                            {!editing && (
                                <Field label="Username" error={form.errors.username} hint={isAdminType ? 'At least 8 characters; letters, numbers, - and _.' : 'Staff get a staff ID as their username.'}>
                                    <input className={fieldInput} value={form.data.username} onChange={(e) => form.set('username', e.target.value)} disabled={!isAdminType && !!typeTitle} />
                                </Field>
                            )}
                            {!editing && (
                                <Field label="Password" error={form.errors.password} hint="Leave blank to use the type of user as the password.">
                                    <input className={fieldInput} type="password" autoComplete="new-password" value={form.data.password} onChange={(e) => form.set('password', e.target.value)} />
                                </Field>
                            )}
                            <Field label="Address" required error={form.errors.address} span={2}>
                                <input className={fieldInput} value={form.data.address} onChange={(e) => form.set('address', e.target.value)} placeholder="House number, street and town" />
                            </Field>
                            <Field label="Nationality" required error={form.errors.nal_id}>
                                <Combobox value={form.data.nal_id} onChange={(v) => form.set('nal_id', v)} options={opts(options.nationals)} placeholder="Choose nationality" creatable invalid={!!form.errors.nal_id} />
                            </Field>
                            <Field label="Blood group" error={form.errors.bg_id}>
                                <Combobox value={form.data.bg_id} onChange={(v) => form.set('bg_id', v)} options={opts(options.blood_groups)} placeholder="Choose blood group" />
                            </Field>
                            <Field label="State" required error={form.errors.state_id}>
                                <Combobox value={form.data.state_id} onChange={(v) => set('state_id', v)} options={opts(options.states)} placeholder="Choose state" creatable invalid={!!form.errors.state_id} />
                            </Field>
                            <Field label="LGA" required error={form.errors.lga_id} hint={!form.data.state_id ? 'Choose a state first.' : undefined}>
                                <Combobox value={form.data.lga_id} onChange={(v) => form.set('lga_id', v)} options={opts(lgas.list)} loading={lgas.loading} disabled={!form.data.state_id} placeholder="Choose LGA" creatable invalid={!!form.errors.lga_id} />
                            </Field>
                            <Field label="Passport photo" error={photoError || form.errors.photo} span={2}>
                                <PhotoUploader value={form.data.photo} onChange={(f) => form.set('photo', f)} currentUrl={editing?.photo} name={form.data.name} onError={setPhotoError} />
                            </Field>
                        </SetupCard>
                        <InfoCallout>New staff can sign in with their username (or email) and the password above. Super administrators can reset a password from the list.</InfoCallout>
                    </div>
                ) : (
                    <>
                        <Segmented
                            value={type}
                            onChange={setType}
                            options={[
                                { value: 'all', label: `All (${users.length})` },
                                ...userTypes.filter((t) => counts[t.title]).map((t) => ({ value: t.title, label: `${t.name}s (${counts[t.title]})` })),
                            ]}
                        />
                        <RegistryCard
                            key={type}
                            title="Manage Users"
                            rows={rows}
                            exportName="users"
                            searchText={(r) => `${r.name} ${r.username ?? ''} ${r.email ?? ''} ${r.phone ?? ''} ${r.type_name}`}
                            columns={[
                                { key: 'sn', header: 'S/N', headerClassName: 'w-16' },
                                {
                                    key: 'name',
                                    header: 'Name',
                                    sort: (r) => r.name,
                                    exportValue: (r) => r.name,
                                    cell: (r) => (
                                        <button type="button" onClick={() => router.visit(r.urls.show)} className="flex items-center gap-3 text-left">
                                            <Avatar src={r.photo} name={r.name} size="md" />
                                            <div className="min-w-0">
                                                <div className="truncate font-semibold hover:text-primary">{r.name}</div>
                                                <div className="truncate text-xs text-fg-muted">{r.username}</div>
                                            </div>
                                        </button>
                                    ),
                                },
                                { key: 'type', header: 'Type', sort: (r) => r.type_name, exportValue: (r) => r.type_name, cell: (r) => <span className="rounded bg-subtle px-2 py-0.5 text-xs font-semibold">{r.type_name}</span> },
                                { key: 'phone', header: 'Phone', sort: (r) => r.phone ?? '', exportValue: (r) => r.phone, cell: (r) => <span className="tabular">{r.phone || '—'}</span> },
                                { key: 'email', header: 'Email', sort: (r) => r.email ?? '', exportValue: (r) => r.email, cell: (r) => r.email || <span className="text-fg-subtle">—</span> },
                                {
                                    key: 'action',
                                    header: 'Action',
                                    headerClassName: 'text-right',
                                    className: 'text-right',
                                    cell: (r) => (
                                        <div className="flex justify-end gap-0.5">
                                            <RowIconButton icon={Eye} title="View profile" onClick={() => router.visit(r.urls.show)} />
                                            <RowIconButton icon={Pencil} title="Edit" onClick={() => router.visit(r.urls.edit)} />
                                            {r.urls.reset_pass && (
                                                <RowIconButton
                                                    icon={KeyRound}
                                                    title="Reset password"
                                                    onClick={() =>
                                                        confirm({ title: `Reset ${r.name}'s password?`, description: 'The password becomes “user”. Ask them to change it after signing in.', confirmLabel: 'Reset password', tone: 'primary', url: r.urls.reset_pass })
                                                    }
                                                />
                                            )}
                                            {r.urls.destroy && (
                                                <RowIconButton
                                                    icon={Trash2}
                                                    title="Delete"
                                                    tone="danger"
                                                    onClick={() => confirm({ title: `Delete ${r.name}?`, description: 'The account and its records will be removed. This cannot be undone.', confirmLabel: 'Delete user', method: 'delete', url: r.urls.destroy })}
                                                />
                                            )}
                                        </div>
                                    ),
                                },
                            ]}
                        />
                    </>
                )}
            </div>
            {confirmDialog}
        </>
    );
}

UsersIndex.layout = withAppLayout;

/** LGAs of the chosen state, loaded when the state changes. */
function useLgas(url, stateId, initial) {
    const [list, setList] = useState(initial ?? []);
    const [loading, setLoading] = useState(false);
    const first = useRef(true);
    useEffect(() => {
        if (first.current && initial) {
            first.current = false;
            return undefined;
        }
        first.current = false;
        if (!stateId || String(stateId).startsWith('new:')) {
            setList([]);
            return undefined;
        }
        let cancelled = false;
        setLoading(true);
        http.get(url.replace(':id', stateId))
            .then(({ data }) => !cancelled && setList(data))
            .catch(() => !cancelled && toast.error('Could not load the LGAs. Check your connection.'))
            .finally(() => !cancelled && setLoading(false));
        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [stateId]);
    return { list, loading };
}
