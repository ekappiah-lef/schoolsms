import { Head, router } from '@inertiajs/react';
import { BedDouble, Pencil, Trash2, Users } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { Field, InfoCallout, ModuleHeader, ModuleTabs, RegistryCard, RowIconButton, SetupCard, SummaryTile, fieldInput, useModuleTab } from '@/components/app/module';
import { useConfirmAction } from '@/components/app/confirm-action';
import { useModuleForm } from '@/lib/use-module-form';
import { cn } from '@/lib/utils';

/** Dormitories — Create dormitory / Show dormitories (DormController; JSON store & update). */
export default function DormsIndex({ session, canDelete, dorms, editing, urls }) {
    const [tab, setTab] = useModuleTab(editing ? 'create' : 'list');
    const [confirm, confirmDialog] = useConfirmAction();
    const form = useModuleForm({
        initial: { name: '', description: '' },
        editing,
        storeUrl: urls.store,
        indexUrl: urls.index,
        only: ['dorms'],
        onCreated: () => setTab('list'),
        validate: (d) => (!d.name.trim() ? { name: 'Enter the dormitory name.' } : {}),
    });
    const boarders = dorms.reduce((a, d) => a + d.students, 0);

    return (
        <>
            <Head title="Dormitories" />
            <div className={cn('mx-auto flex w-full flex-col gap-6', tab === 'create' ? 'max-w-4xl' : 'max-w-7xl')}>
                <ModuleHeader crumbs={['Administration', 'Boarding']} title="Dormitories" description="Boarding houses students can be assigned to during admission." session={session} />
                <ModuleTabs value={tab} onChange={(t) => (editing && t === 'list' ? router.visit(urls.index) : setTab(t))} createLabel="Dormitory" listLabel="Show Dormitories" count={dorms.length} editing={!!editing} />

                {tab === 'create' ? (
                    <div className="flex flex-col gap-3">
                        <SetupCard
                            title={editing ? `Edit ${editing.name}` : 'New Dormitory Setup'}
                            description="Dormitory names must be unique."
                            icon={BedDouble}
                            onSubmit={form.submit}
                            onReset={form.reset}
                            onCancel={() => (editing ? router.visit(urls.index) : setTab('list'))}
                            submitLabel={editing ? 'Save Dormitory' : 'Create Dormitory'}
                            processing={form.processing}
                        >
                            <Field label="Dormitory Name" required aside="Unique" error={form.errors.name} span={2}>
                                <input className={fieldInput} value={form.data.name} onChange={(e) => form.set('name', e.target.value)} placeholder="e.g., Faith House" aria-invalid={!!form.errors.name || undefined} autoFocus />
                            </Field>
                            <Field label="Description" error={form.errors.description} span={2}>
                                <input className={fieldInput} value={form.data.description ?? ''} onChange={(e) => form.set('description', e.target.value)} placeholder="e.g., Girls' boarding, block C" />
                            </Field>
                        </SetupCard>
                        <InfoCallout>Students are placed in a dormitory (with a room number) from the student admission or edit form.</InfoCallout>
                    </div>
                ) : (
                    <>
                        <RegistryCard
                            title="Manage Dormitories"
                            rows={dorms}
                            exportName="dormitories"
                            searchText={(r) => `${r.name} ${r.description ?? ''}`}
                            columns={[
                                { key: 'sn', header: 'S/N', headerClassName: 'w-16' },
                                {
                                    key: 'name',
                                    header: 'Name',
                                    sort: (r) => r.name,
                                    exportValue: (r) => r.name,
                                    cell: (r) => (
                                        <div className="flex items-center gap-3">
                                            <div className="flex size-9 items-center justify-center rounded-lg bg-primary-soft/70 text-primary-hover">
                                                <BedDouble className="size-[18px]" />
                                            </div>
                                            <span className="font-semibold">{r.name}</span>
                                        </div>
                                    ),
                                },
                                { key: 'description', header: 'Description', exportValue: (r) => r.description, cell: (r) => r.description || <span className="text-fg-subtle">—</span> },
                                { key: 'students', header: 'Boarders', sort: (r) => r.students, exportValue: (r) => r.students, cell: (r) => <span className="tabular">{r.students}</span> },
                                {
                                    key: 'action',
                                    header: 'Action',
                                    headerClassName: 'text-right',
                                    className: 'text-right',
                                    cell: (r) => (
                                        <div className="flex justify-end gap-0.5">
                                            <RowIconButton icon={Pencil} title="Edit" onClick={() => router.visit(r.urls.edit)} />
                                            {canDelete && (
                                                <RowIconButton
                                                    icon={Trash2}
                                                    title="Delete"
                                                    tone="danger"
                                                    onClick={() =>
                                                        confirm({ title: `Delete ${r.name}?`, description: 'This cannot be undone.', confirmLabel: 'Delete dormitory', method: 'delete', url: r.urls.destroy })
                                                    }
                                                />
                                            )}
                                        </div>
                                    ),
                                },
                            ]}
                        />
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <SummaryTile icon={BedDouble} label="Dormitories" value={dorms.length} />
                            <SummaryTile icon={Users} label="Boarding students" value={boarders} />
                        </div>
                    </>
                )}
            </div>
            {confirmDialog}
        </>
    );
}

DormsIndex.layout = withAppLayout;
