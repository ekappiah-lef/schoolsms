import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { Field, ModuleHeader, RowIconButton, fieldInput } from '@/components/app/module';
import { Button } from '@/components/ui/button';
import { ConfirmDialog, Modal } from '@/components/ui/dialog';
import { submitForm } from '@/lib/http';

/** Grade remarks (Excellent, Very Good …): the list offered when setting up grades (GradeRemarkController). */
export default function GradeRemarksPage({ session, remarks, urls }) {
    const [editing, setEditing] = useState(null); // null closed, {} new, {id…} edit
    const [name, setName] = useState('');
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);
    const [removing, setRemoving] = useState(null);

    const open = (r) => {
        setEditing(r ?? {});
        setName(r?.name ?? '');
        setErrors({});
    };
    const save = async () => {
        setBusy(true);
        const r = await submitForm(editing?.id ? urls.update.replace(':id', editing.id) : urls.store, { name }, { method: editing?.id ? 'put' : 'post' });
        setBusy(false);
        if (!r.ok) {
            setErrors(r.errors ?? {});
            return toast.error(r.errors?.name ?? r.message);
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
            <Head title="Grade remarks" />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6">
                <ModuleHeader crumbs={['Administration', 'Classes', 'Grade remarks']} title="Grade remarks" session={session} />
                <div className="flex w-full flex-col overflow-hidden rounded-lg bg-surface shadow-card">
                    <div className="flex items-center justify-between px-6 py-3">
                        <h2 className="text-lg font-semibold tracking-tight">Grade remarks</h2>
                        <Button size="sm" onClick={() => open(null)}>
                            <Plus />
                            Add remark
                        </Button>
                    </div>
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="bg-canvas text-2xs font-semibold uppercase tracking-wider text-fg-muted">
                                <th className="px-6 py-2">Remark</th>
                                <th className="px-4 py-2">Grades</th>
                                <th className="px-6 py-2 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {remarks.map((r) => (
                                <tr key={r.id} className="hover:bg-muted/60">
                                    <td className="px-6 py-3 font-semibold">{r.name}</td>
                                    <td className="tabular px-4 py-3">{r.grades}</td>
                                    <td className="px-6 py-3">
                                        <div className="flex justify-end gap-0.5">
                                            <RowIconButton icon={Pencil} title="Edit" onClick={() => open(r)} />
                                            {!r.grades && <RowIconButton icon={Trash2} title="Delete" tone="danger" onClick={() => setRemoving(r)} />}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <Modal
                open={editing !== null}
                onOpenChange={(o) => !o && !busy && setEditing(null)}
                title={editing?.id ? `Edit ${editing.name}` : 'New remark'}
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
                <Field label="Remark" required error={errors.name}>
                    <input className={fieldInput} value={name} onChange={(e) => setName(e.target.value)} placeholder="e.g. Excellent" aria-invalid={errors.name ? true : undefined} autoFocus />
                </Field>
            </Modal>

            <ConfirmDialog open={!!removing} onOpenChange={(o) => !o && setRemoving(null)} title={`Delete ${removing?.name}?`} confirmLabel="Delete" loading={busy} onConfirm={remove} />
        </>
    );
}

GradeRemarksPage.layout = withAppLayout;
