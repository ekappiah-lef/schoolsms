import { Head, Link, router } from '@inertiajs/react';
import { Building2, Layers, Pencil, School, Trash2, Users } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { Field, InfoCallout, ModuleHeader, ModuleTabs, NativeSelect, RegistryCard, RowIconButton, SetupCard, SummaryTile, fieldInput, useModuleTab } from '@/components/app/module';
import { useConfirmAction } from '@/components/app/confirm-action';
import { useModuleForm } from '@/lib/use-module-form';
import { cn } from '@/lib/utils';

/** Classes — Create class / Show classes (MyClassController; store & update return JSON). */
export default function ClassesIndex({ session, canDelete, types, classes, editing, urls }) {
    const [tab, setTab] = useModuleTab(editing ? 'create' : 'list');
    const [confirm, confirmDialog] = useConfirmAction();
    const form = useModuleForm({
        initial: { name: '', class_type_id: '' },
        editing,
        storeUrl: urls.store,
        indexUrl: urls.index,
        only: ['classes'],
        onCreated: () => setTab('list'),
        validate: (d) => ({
            ...(d.name.trim().length < 3 ? { name: 'Enter a class name of at least 3 characters.' } : {}),
            ...(!d.class_type_id ? { class_type_id: 'Choose the class type.' } : {}),
        }),
    });
    const students = classes.reduce((a, c) => a + c.students, 0);

    return (
        <>
            <Head title="Classes" />
            <div className={cn('mx-auto flex w-full flex-col gap-6', tab === 'create' ? 'max-w-4xl' : 'max-w-7xl')}>
                <ModuleHeader crumbs={['Administration', 'Classes']} title="Classes" session={session} />
                <ModuleTabs value={tab} onChange={(t) => (editing && t === 'list' ? router.visit(urls.index) : setTab(t))} createLabel="Class" listLabel="Show Classes" count={classes.length} editing={!!editing} />

                {tab === 'create' ? (
                    <div className="flex flex-col gap-3">
                        <SetupCard
                            title={editing ? `Edit ${editing.name}` : 'New Class Setup'}
                           
                            icon={School}
                            onSubmit={form.submit}
                            onReset={form.reset}
                            onCancel={() => (editing ? router.visit(urls.index) : setTab('list'))}
                            submitLabel={editing ? 'Save Class' : 'Create Class'}
                            processing={form.processing}
                        >
                            <Field label="Class Name" required aside="e.g. JHS 1, Primary 4" error={form.errors.name} span={2}>
                                <input className={fieldInput} value={form.data.name} onChange={(e) => form.set('name', e.target.value)} placeholder="e.g., JHS 1" aria-invalid={!!form.errors.name || undefined} autoFocus />
                            </Field>
                            <Field label="Class Type" required error={form.errors.class_type_id} span={2}>
                                <NativeSelect value={form.data.class_type_id} onChange={(v) => form.set('class_type_id', v)} invalid={!!form.errors.class_type_id}>
                                    <option value="" disabled>
                                        Select class type
                                    </option>
                                    {types.map((t) => (
                                        <option key={t.id} value={t.id}>
                                            {t.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                        </SetupCard>
                    </div>
                ) : (
                    <>
                        <RegistryCard
                            title="Manage Classes"
                            rows={classes}
                            exportName="classes"
                            searchText={(r) => `${r.name} ${r.type}`}
                            columns={[
                                {
                                    key: 'name',
                                    header: 'Name',
                                    sort: (r) => r.name,
                                    exportValue: (r) => r.name,
                                    cell: (r) => <span className="font-semibold">{r.name}</span>,
                                },
                                { key: 'type', header: 'Class Type', sort: (r) => r.type, exportValue: (r) => r.type, cell: (r) => <span className="rounded bg-subtle px-2 py-0.5 text-xs font-semibold">{r.type}</span> },
                                { key: 'sections', header: 'Sections', sort: (r) => r.sections, exportValue: (r) => r.sections, cell: (r) => <span className="tabular">{r.sections}</span> },
                                { key: 'subjects', header: 'Subjects', sort: (r) => r.subjects, exportValue: (r) => r.subjects, cell: (r) => <span className="tabular">{r.subjects}</span> },
                                {
                                    key: 'students',
                                    header: 'Students',
                                    sort: (r) => r.students,
                                    exportValue: (r) => r.students,
                                    cell: (r) => (
                                        <Link href={r.urls.students} className="tabular font-medium text-primary hover:underline">
                                            {r.students}
                                        </Link>
                                    ),
                                },
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
                                                        confirm({
                                                            title: `Delete ${r.name}?`,
                                                            description: r.students ? `${r.students} students are in this class. Deleting it may remove their class records. This cannot be undone.` : 'This removes the class and its sections. This cannot be undone.',
                                                            confirmLabel: 'Delete class',
                                                            method: 'delete',
                                                            url: r.urls.destroy,
                                                        })
                                                    }
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

ClassesIndex.layout = withAppLayout;

