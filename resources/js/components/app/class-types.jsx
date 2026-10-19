import { useState } from 'react';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { Field, RowIconButton, fieldInput } from '@/components/app/module';
import { Button } from '@/components/ui/button';
import { ConfirmDialog, Modal } from '@/components/ui/dialog';
import { submitForm } from '@/lib/http';

/**
 * The school's class types (Preschool, Lower Grade, Junior High …): add, rename, delete when no class uses it.
 */
export function ClassTypes({ types, urls, canDelete }) {
    const [editing, setEditing] = useState(null); // null closed, {} new, {id…} edit
    const [form, setForm] = useState({ name: '' });
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);
    const [removing, setRemoving] = useState(null);

    const open = (t) => {
        setEditing(t ?? {});
        setForm({ name: t?.name ?? '' });
        setErrors({});
    };

    const save = async () => {
        setBusy(true);
        const r = await submitForm(editing?.id ? urls.typeUpdate.replace(':id', editing.id) : urls.typeStore, form, { method: editing?.id ? 'put' : 'post' });
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
        const r = await submitForm(urls.typeDestroy.replace(':id', removing.id), {}, { method: 'delete' });
        setBusy(false);
        setRemoving(null);
        r.ok ? toast.success(r.message) : toast.error(r.message);
        router.reload({ preserveScroll: true });
    };

    return (
        <div className="flex w-full flex-col overflow-hidden rounded-lg bg-surface shadow-card">
            <div className="flex items-center justify-between px-6 py-3">
                <h2 className="text-lg font-semibold tracking-tight">Class types</h2>
                <Button size="sm" onClick={() => open(null)}>
                    <Plus />
                    Add class type
                </Button>
            </div>
            <table className="w-full text-left text-sm">
                <thead>
                    <tr className="bg-canvas text-2xs font-semibold uppercase tracking-wider text-fg-muted">
                        <th className="px-6 py-2">Name</th>
                        <th className="px-4 py-2">Classes</th>
                        <th className="px-6 py-2 text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    {types.map((t) => (
                        <tr key={t.id} className="hover:bg-muted/60">
                            <td className="px-6 py-3 font-semibold">{t.name}</td>
                            <td className="tabular px-4 py-3">{t.classes}</td>
                            <td className="px-6 py-3">
                                <div className="flex justify-end gap-0.5">
                                    <RowIconButton icon={Pencil} title="Edit" onClick={() => open(t)} />
                                    {canDelete && !t.classes && <RowIconButton icon={Trash2} title="Delete" tone="danger" onClick={() => setRemoving(t)} />}
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>

            <Modal
                open={editing !== null}
                onOpenChange={(o) => !o && !busy && setEditing(null)}
                title={editing?.id ? `Edit ${editing.name}` : 'New class type'}
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
                        <input className={fieldInput} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="e.g. Lower Grade" aria-invalid={errors.name ? true : undefined} autoFocus />
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
        </div>
    );
}
