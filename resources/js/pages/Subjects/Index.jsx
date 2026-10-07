import { Head, router } from '@inertiajs/react';
import { BookOpen, Pencil, School, Trash2, UserRound } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { Field, InfoCallout, ModuleHeader, ModuleTabs, NativeSelect, RegistryCard, RowIconButton, SetupCard, SummaryTile, fieldInput, useModuleTab } from '@/components/app/module';
import { useConfirmAction } from '@/components/app/confirm-action';
import { useModuleForm } from '@/lib/use-module-form';
import { cn } from '@/lib/utils';

/** Subjects — Create subject / Show subjects (SubjectController; JSON store & update, teacher ids are hashed). */
export default function SubjectsIndex({ session, canDelete, classes, teachers, subjects, editing, urls }) {
    const [tab, setTab] = useModuleTab(editing ? 'create' : 'list');
    const [confirm, confirmDialog] = useConfirmAction();
    const form = useModuleForm({
        initial: { name: '', slug: '', my_class_id: '', teacher_id: '' },
        editing,
        storeUrl: urls.store,
        indexUrl: urls.index,
        only: ['subjects'],
        onCreated: () => setTab('list'),
        validate: (d) => ({
            ...(d.name.trim().length < 3 ? { name: 'Enter a subject name of at least 3 characters.' } : {}),
            ...(d.slug && d.slug.trim().length < 3 ? { slug: 'The short name must be at least 3 characters.' } : {}),
            ...(!d.my_class_id ? { my_class_id: 'Choose a class.' } : {}),
            ...(!editing && !d.teacher_id ? { teacher_id: 'Choose the subject teacher.' } : {}),
        }),
    });

    return (
        <>
            <Head title="Subjects" />
            <div className={cn('mx-auto flex w-full flex-col gap-6', tab === 'create' ? 'max-w-4xl' : 'max-w-7xl')}>
                <ModuleHeader crumbs={['Academics', 'Curriculum']} title="Subjects"  session={session} />
                <ModuleTabs value={tab} onChange={(t) => (editing && t === 'list' ? router.visit(urls.index) : setTab(t))} createLabel="Subject" listLabel="Show Subjects" count={subjects.length} editing={!!editing} />

                {tab === 'create' ? (
                    <div className="flex flex-col gap-3">
                        <SetupCard
                            title={editing ? `Edit ${editing.name}` : 'New Subject Setup'}
                            description="A subject belongs to one class. Create it again for each class that takes it."
                            icon={BookOpen}
                            onSubmit={form.submit}
                            onReset={form.reset}
                            onCancel={() => (editing ? router.visit(urls.index) : setTab('list'))}
                            submitLabel={editing ? 'Save Subject' : 'Create Subject'}
                            processing={form.processing}
                        >
                            <Field label="Subject Name" required aside="As printed on report sheets" error={form.errors.name}>
                                <input className={fieldInput} value={form.data.name} onChange={(e) => form.set('name', e.target.value)} placeholder="e.g., Integrated Science" aria-invalid={!!form.errors.name || undefined} autoFocus />
                            </Field>
                            <Field label="Short Name" aside="Optional" error={form.errors.slug}>
                                <input className={fieldInput} value={form.data.slug ?? ''} onChange={(e) => form.set('slug', e.target.value)} placeholder="e.g., INT SCI" aria-invalid={!!form.errors.slug || undefined} />
                            </Field>
                            <Field label="Class / Grade" required error={form.errors.my_class_id}>
                                <NativeSelect value={form.data.my_class_id} onChange={(v) => form.set('my_class_id', v)} invalid={!!form.errors.my_class_id}>
                                    <option value="" disabled>
                                        Select class
                                    </option>
                                    {classes.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                            <Field label="Subject Teacher" required={!editing} error={form.errors.teacher_id}>
                                <NativeSelect value={form.data.teacher_id} onChange={(v) => form.set('teacher_id', v)} invalid={!!form.errors.teacher_id}>
                                    <option value="" disabled={!editing}>
                                        {editing ? 'No teacher' : 'Select teacher'}
                                    </option>
                                    {teachers.map((t) => (
                                        <option key={t.id} value={t.id}>
                                            {t.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                        </SetupCard>
                        <InfoCallout>The subject teacher sees this subject under Marks entry. A class cannot have two subjects with the same name.</InfoCallout>
                    </div>
                ) : (
                    <>
                        <RegistryCard
                            title="Manage Subjects"
                            rows={subjects}
                            exportName="subjects"
                            searchText={(r) => `${r.name} ${r.slug ?? ''} ${r.class} ${r.teacher ?? ''}`}
                            columns={[
                                { key: 'sn', header: 'S/N', headerClassName: 'w-16' },
                                {
                                    key: 'name',
                                    header: 'Subject',
                                    sort: (r) => r.name,
                                    exportValue: (r) => r.name,
                                    cell: (r) => (
                                        <div className="flex items-center gap-3">
                                            <div>
                                                <div className="font-semibold">{r.name}</div>
                                                {r.slug && <div className="font-mono text-[11px] text-fg-muted">{r.slug}</div>}
                                            </div>
                                        </div>
                                    ),
                                },
                                { key: 'class', header: 'Class', sort: (r) => r.class, exportValue: (r) => r.class, cell: (r) => <span className="rounded bg-subtle px-2 py-0.5 text-xs font-semibold">{r.class}</span> },
                                {
                                    key: 'teacher',
                                    header: 'Teacher',
                                    sort: (r) => r.teacher ?? '',
                                    exportValue: (r) => r.teacher,
                                    cell: (r) =>
                                        r.teacher ? (
                                            <span className="flex items-center gap-1.5">
                                                <UserRound className="size-4 text-fg-subtle" />
                                                {r.teacher}
                                            </span>
                                        ) : (
                                            <span className="text-fg-subtle">Not assigned</span>
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
                                                            title: `Delete ${r.name} (${r.class})?`,
                                                            description: 'Marks already entered for this subject may be removed. This cannot be undone.',
                                                            confirmLabel: 'Delete subject',
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
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <SummaryTile icon={BookOpen} label="Subjects" value={subjects.length} />
                            <SummaryTile icon={School} label="Classes with subjects" value={new Set(subjects.map((s) => s.class)).size} />
                            <SummaryTile icon={UserRound} label="Teachers assigned" value={new Set(subjects.map((s) => s.teacher).filter(Boolean)).size} />
                        </div>
                    </>
                )}
            </div>
            {confirmDialog}
        </>
    );
}

SubjectsIndex.layout = withAppLayout;
