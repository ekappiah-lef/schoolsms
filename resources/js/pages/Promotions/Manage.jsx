import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { RotateCcw } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { ModuleHeader, RegistryCard, RowIconButton } from '@/components/app/module';
import { useConfirmAction } from '@/components/app/confirm-action';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/dialog';
import { submitForm } from '@/lib/http';

const TONE = { P: 'success', D: 'warning', G: 'info' };

/** Promotions made from this year to next year; each can be undone, or all at once. */
export default function PromotionsManage({ oldYear, newYear, promotions, urls }) {
    const [confirm, confirmDialog] = useConfirmAction();
    const [resetAll, setResetAll] = useState(false);
    const [busy, setBusy] = useState(false);

    const doResetAll = async () => {
        setBusy(true);
        const r = await submitForm(urls.resetAll, {}, { method: 'delete' });
        setBusy(false);
        setResetAll(false);
        if (r.ok) {
            toast.success(r.message);
            router.reload({ only: ['promotions'] });
        } else toast.error(r.message);
    };

    return (
        <>
            <Head title="Manage promotions" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
                <ModuleHeader
                    crumbs={['Students', 'Promotion', 'Manage']}
                    title="Manage promotions"
                    description={`Students moved from ${oldYear} to ${newYear}. Resetting puts a student back in their old class.`}
                    aside={
                        <div className="flex gap-2">
                            <Button asChild>
                                <Link href={urls.promote}>Promote students</Link>
                            </Button>
                            {promotions.length > 0 && (
                                <Button variant="danger" onClick={() => setResetAll(true)}>
                                    <RotateCcw />
                                    Reset all
                                </Button>
                            )}
                        </div>
                    }
                />
                <RegistryCard
                    title="Promotions"
                    rows={promotions}
                    exportName="promotions"
                    emptyText={`No students have been promoted from ${oldYear} yet.`}
                    searchText={(r) => `${r.name} ${r.from} ${r.to} ${r.status_label}`}
                    columns={[
                        { key: 'sn', header: 'S/N', headerClassName: 'w-16' },
                        {
                            key: 'name',
                            header: 'Student',
                            sort: (r) => r.name,
                            exportValue: (r) => r.name,
                            cell: (r) => (
                                <div className="flex items-center gap-3">
                                    <Avatar src={r.photo} name={r.name} size="md" />
                                    <span className="font-medium">{r.name}</span>
                                </div>
                            ),
                        },
                        { key: 'from', header: 'From', sort: (r) => r.from, exportValue: (r) => r.from, cell: (r) => r.from },
                        { key: 'to', header: 'To', sort: (r) => r.to, exportValue: (r) => r.to, cell: (r) => r.to },
                        { key: 'status', header: 'Status', sort: (r) => r.status_label, exportValue: (r) => r.status_label, cell: (r) => <Badge tone={TONE[r.status]}>{r.status_label}</Badge> },
                        {
                            key: 'action',
                            header: 'Action',
                            headerClassName: 'text-right',
                            className: 'text-right',
                            cell: (r) => (
                                <RowIconButton
                                    icon={RotateCcw}
                                    title="Reset"
                                    tone="danger"
                                    onClick={() =>
                                        confirm({ title: `Reset ${r.name}?`, description: `${r.name} goes back to ${r.from} for ${oldYear}.`, confirmLabel: 'Reset promotion', method: 'delete', url: urls.reset.replace(':id', r.id) })
                                    }
                                />
                            ),
                        },
                    ]}
                />
            </div>
            {confirmDialog}
            <ConfirmDialog
                open={resetAll}
                onOpenChange={(o) => !busy && setResetAll(o)}
                title={`Reset all ${promotions.length} promotions?`}
                description={`Every student goes back to their ${oldYear} class, and marks already entered for ${newYear} are deleted.`}
                confirmLabel="Reset all"
                tone="danger"
                loading={busy}
                onConfirm={doResetAll}
            />
        </>
    );
}

PromotionsManage.layout = withAppLayout;
