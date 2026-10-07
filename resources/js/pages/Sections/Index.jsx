import { Head, Link, router } from '@inertiajs/react';
import { Layers, Pencil, Trash2, UserRound, Users } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { Field, InfoCallout, ModuleHeader, ModuleTabs, NativeSelect, RegistryCard, RowIconButton, SetupCard, SummaryTile, fieldInput, useModuleTab } from '@/components/app/module';
import { useConfirmAction } from '@/components/app/confirm-action';
import { useModuleForm } from '@/lib/use-module-form';
import { cn } from '@/lib/utils';

/** Sections — Create section / Show sections (SectionController; JSON store & update, teacher ids are hashed). */
export default function SectionsIndex({ session, canDelete, classes, teachers, sections, editing, urls }) {
    const [tab, setTab] = useModuleTab(editing ? 'create' : 'list');
    const [confirm, confirmDialog] = useConfirmAction();
    const form = useModuleForm({
        initial: editing ? { name: '', teacher_id: '', capacity: '' } : { name: '', my_class_id: '', teacher_id: '', capacity: '' },
        editing,
        storeUrl: urls.store,
        indexUrl: urls.index,
        only: ['sections'],
        onCreated: () => setTab('list'),
        validate: (d) => ({
            ...(!d.name.trim() ? { name: 'Enter the section name.' } : {}),
            ...(!editing && !d.my_class_id ? { my_class_id: 'Choose a class.' } : {}),
            ...(d.capacity !== '' && !(Number(d.capacity) >= 1 && Number(d.capacity) <= 500) ? { capacity: 'Enter a number from 1 to 500, or leave blank.' } : {}),
        }),
    });
    const unassigned = sections.filter((s) => !s.teacher).length;

    return (
        <>
            <Head title="Sections" />
            <div className={cn('mx-auto flex w-full flex-col gap-6', tab === 'create' ? 'max-w-4xl' : 'max-w-7xl')}>
                <ModuleHeader crumbs={['Administration', 'Sections']} title="Sections"  session={session} />
                <ModuleTabs value={tab} onChange={(t) => (editing && t === 'list' ? router.visit(urls.index) : setTab(t))} createLabel="Section" listLabel="Show Sections" count={sections.length} editing={!!editing} />

                {tab === 'create' ? (
                    <div className="flex flex-col gap-3">
                        <SetupCard
                            title={editing ? `Edit ${editing.class} ${editing.name}` : 'New Section Setup'}
                            description={editing ? 'Rename the section or change its class teacher.' : 'Add a section to a class and optionally assign its class teacher.'}
                            icon={Layers}
                            onSubmit={form.submit}
                            onReset={form.reset}
                            onCancel={() => (editing ? router.visit(urls.index) : setTab('list'))}
                            submitLabel={editing ? 'Save Section' : 'Create Section'}
                            processing={form.processing}
                        >
                            <Field label="Section Name" required aside="e.g. A, B, Gold" error={form.errors.name}>
                                <input className={fieldInput} value={form.data.name} onChange={(e) => form.set('name', e.target.value)} placeholder="e.g., B" aria-invalid={!!form.errors.name || undefined} autoFocus />
                            </Field>
                            <Field label="Class" required={!editing} error={form.errors.my_class_id} hint={editing ? 'Fixed after creation.' : undefined}>
                                {editing ? (
                                    <NativeSelect value="x" onChange={() => {}} disabled>
                                        <option value="x">{editing.class}</option>
                                    </NativeSelect>
                                ) : (
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
                                )}
                            </Field>
                            <Field label="Capacity" error={form.errors.capacity} hint="Maximum number of students. Shown when admitting, e.g. Gold (18/24).">
                                <input
                                    className={`${fieldInput} tabular`}
                                    type="number"
                                    min={1}
                                    max={500}
                                    inputMode="numeric"
                                    value={form.data.capacity ?? ''}
                                    onChange={(e) => form.set('capacity', e.target.value)}
                                    placeholder="e.g., 24"
                                    aria-invalid={!!form.errors.capacity || undefined}
                                />
                            </Field>
                            <Field label="Class Teacher" error={form.errors.teacher_id} hint="Optional — can be assigned later.">
                                <NativeSelect value={form.data.teacher_id} onChange={(v) => form.set('teacher_id', v)}>
                                    <option value="">No class teacher</option>
                                    {teachers.map((t) => (
                                        <option key={t.id} value={t.id}>
                                            {t.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                        </SetupCard>
                        <InfoCallout>Class teachers can manage marks and timetables for their sections. The default section of a class cannot be deleted.</InfoCallout>
                    </div>
                ) : (
                    <>
                        <RegistryCard
                            title="Manage Sections"
                            rows={sections}
                            exportName="sections"
                            searchText={(r) => `${r.class} ${r.name} ${r.teacher ?? ''}`}
                            columns={[
                                { key: 'sn', header: 'S/N', headerClassName: 'w-16' },
                                {
                                    key: 'name',
                                    header: 'Section',
                                    sort: (r) => `${r.class} ${r.name}`,
                                    exportValue: (r) => r.name,
                                    cell: (r) => (
                                        <div className="flex items-center gap-3">
                                            <div>
                                                <div className="font-semibold">
                                                    {r.class} {r.name}
                                                </div>
                                                {r.default && <div className="text-xs text-fg-muted">Default section</div>}
                                            </div>
                                        </div>
                                    ),
                                },
                                { key: 'class', header: 'Class', sort: (r) => r.class, exportValue: (r) => r.class, cell: (r) => <span className="rounded bg-subtle px-2 py-0.5 text-xs font-semibold">{r.class}</span> },
                                {
                                    key: 'teacher',
                                    header: 'Class Teacher',
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
                                    key: 'students',
                                    header: 'Students / capacity',
                                    sort: (r) => (r.capacity ? r.students / r.capacity : r.students / 1000),
                                    exportValue: (r) => (r.capacity ? `${r.students}/${r.capacity}` : r.students),
                                    cell: (r) => {
                                        const full = r.capacity && r.students >= r.capacity;
                                        return (
                                            <div className="flex items-center gap-2">
                                                <Link href={r.urls.students} className={cn('tabular font-medium hover:underline', full ? 'text-danger-fg' : 'text-primary')}>
                                                    {r.students}
                                                    {r.capacity ? <span className="text-fg-muted">/{r.capacity}</span> : null}
                                                </Link>
                                                {full ? <span className="rounded bg-danger-soft px-1.5 py-px text-2xs font-semibold uppercase text-danger-fg">Full</span> : null}
                                                {!r.capacity && <span className="text-xs text-fg-subtle">no limit</span>}
                                            </div>
                                        );
                                    },
                                },
                                {
                                    key: 'action',
                                    header: 'Action',
                                    headerClassName: 'text-right',
                                    className: 'text-right',
                                    cell: (r) => (
                                        <div className="flex justify-end gap-0.5">
                                            <RowIconButton icon={Pencil} title="Edit" onClick={() => router.visit(r.urls.edit)} />
                                            {canDelete && !r.default && (
                                                <RowIconButton
                                                    icon={Trash2}
                                                    title="Delete"
                                                    tone="danger"
                                                    onClick={() =>
                                                        confirm({
                                                            title: `Delete ${r.class} ${r.name}?`,
                                                            description: r.students ? `${r.students} students are in this section. This cannot be undone.` : 'This cannot be undone.',
                                                            confirmLabel: 'Delete section',
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
                            <SummaryTile icon={Layers} label="Sections" value={sections.length} />
                            <SummaryTile icon={UserRound} label="Without class teacher" value={unassigned} />
                            <SummaryTile icon={Users} label="Students placed" value={sections.reduce((a, s) => a + s.students, 0)} />
                        </div>
                    </>
                )}
            </div>
            {confirmDialog}
        </>
    );
}

SectionsIndex.layout = withAppLayout;
