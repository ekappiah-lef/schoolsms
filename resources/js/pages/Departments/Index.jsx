import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { ModuleHeader, RegistryCard, Field, fieldInput } from '@/components/app/module';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { ConfirmDialog, Modal } from '@/components/ui/dialog';
import { Select } from '@/components/ui/select';
import { submitForm } from '@/lib/http';

const blank = { name: '', hod_id: '', subjects: [] };

/** Departments, their head (HOD) and their subjects; the HOD approves the marks of these subjects. */
export default function Departments({ departments, subjects, teachers, urls }) {
    const [editing, setEditing] = useState(null); // null: closed, {}: new, {id…}: edit
    const [form, setForm] = useState(blank);
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);
    const [removing, setRemoving] = useState(null);

    // A subject belongs to one department: show which one already has it.
    const owner = Object.fromEntries(departments.flatMap((d) => d.subjects.map((s) => [s, d])));

    const open = (d) => {
        setEditing(d ?? {});
        setForm(d ? { name: d.name, hod_id: d.hod_id, subjects: d.subjects } : blank);
        setErrors({});
    };
    const toggle = (s) => setForm((f) => ({ ...f, subjects: f.subjects.includes(s) ? f.subjects.filter((x) => x !== s) : [...f.subjects, s] }));

    const save = async () => {
        setBusy(true);
        const r = await submitForm(editing?.id ? urls.update.replace(':id', editing.id) : urls.store, { ...form, subjects: form.subjects.join(',') }, { method: editing?.id ? 'put' : 'post' });
        setBusy(false);
        if (!r.ok) {
            setErrors(r.errors ?? {});
            return toast.error(r.message);
        }
        toast.success(r.message);
        setEditing(null);
        router.reload({ preserveScroll: true });
        return undefined;
    };

    const remove = async () => {
        setBusy(true);
        const r = await submitForm(urls.destroy.replace(':id', removing.id), {}, { method: 'delete' });
        setBusy(false);
        setRemoving(null);
        r.ok ? toast.success(r.message) : toast.error(r.message);
        router.reload({ preserveScroll: true });
    };

    return (
        <>
            <Head title="Departments" />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6">
                <ModuleHeader
                    crumbs={['Administration', 'Departments']}
                    title="Departments"
                    aside={
                        <Button variant="primary" onClick={() => open(null)}>
                            <Plus />
                            New department
                        </Button>
                    }
                />

                <RegistryCard
                    title="Departments"
                    rows={departments}
                    searchText={(r) => `${r.name} ${r.hod ?? ''} ${r.subjects.join(' ')}`}
                    exportName="departments"
                    emptyText="No departments yet."
                    columns={[
                        { key: 'name', header: 'Department', sort: (r) => r.name, exportValue: (r) => r.name, cell: (r) => <span className="font-semibold">{r.name}</span> },
                        { key: 'hod', header: 'Head of department', sort: (r) => r.hod, exportValue: (r) => r.hod, cell: (r) => r.hod ?? <span className="text-warning-fg">Not chosen</span> },
                        { key: 'subjects', header: 'Subjects', exportValue: (r) => r.subjects.join(', '), cell: (r) => <span className="text-sm text-fg-muted">{r.subjects.join(', ') || '—'}</span> },
                        {
                            key: 'actions',
                            header: '',
                            headerClassName: 'w-24',
                            cell: (r) => (
                                <div className="flex justify-end gap-1">
                                    <Button size="icon-sm" variant="ghost" aria-label={`Edit ${r.name}`} onClick={() => open(r)}>
                                        <Pencil />
                                    </Button>
                                    <Button size="icon-sm" variant="ghost" aria-label={`Delete ${r.name}`} onClick={() => setRemoving(r)}>
                                        <Trash2 />
                                    </Button>
                                </div>
                            ),
                        },
                    ]}
                />
            </div>

            <Modal
                open={editing !== null}
                onOpenChange={(o) => !o && !busy && setEditing(null)}
                title={editing?.id ? `Edit ${editing.name}` : 'New department'}
                className="max-w-xl"
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setEditing(null)} disabled={busy}>
                            Cancel
                        </Button>
                        <Button variant="primary" onClick={save} loading={busy}>
                            Save
                        </Button>
                    </>
                }
            >
                <div className="flex flex-col gap-4">
                    <Field label="Name" required error={errors.name}>
                        <input className={fieldInput} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="e.g. Science" aria-invalid={errors.name ? true : undefined} />
                    </Field>
                    <Field label="Head of department" error={errors.hod_id}>
                        <Select value={form.hod_id} onChange={(v) => setForm({ ...form, hod_id: v })} options={teachers} placeholder="Choose a teacher" clearable clearLabel="No head yet" />
                    </Field>
                    <Field label="Subjects">
                        <div className="grid max-h-64 grid-cols-1 gap-1 overflow-y-auto rounded-lg border border-border p-2 sm:grid-cols-2">
                            {subjects.map((s) => {
                                const other = owner[s] && owner[s].id !== editing?.id ? owner[s] : null;
                                return (
                                    <label key={s} className="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-muted">
                                        <Checkbox checked={form.subjects.includes(s)} onCheckedChange={() => toggle(s)} />
                                        <span>{s}</span>
                                        {other && <span className="ml-auto text-xs text-fg-subtle">{other.name}</span>}
                                    </label>
                                );
                            })}
                            {!subjects.length && <p className="p-2 text-sm text-fg-muted">Add subjects first.</p>}
                        </div>
                    </Field>
                </div>
            </Modal>

            <ConfirmDialog
                open={!!removing}
                onOpenChange={(o) => !o && setRemoving(null)}
                title={`Delete ${removing?.name}?`}
                confirmLabel="Delete"
                loading={busy}
                onConfirm={remove}
            />
        </>
    );
}

Departments.layout = withAppLayout;
