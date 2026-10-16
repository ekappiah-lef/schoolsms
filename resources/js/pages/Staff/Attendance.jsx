import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { Pencil } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { ModuleHeader, RegistryCard, RowIconButton, fieldInput } from '@/components/app/module';
import { StatCard, StatStrip } from '@/components/app/page';
import { Avatar } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
import { Modal } from '@/components/ui/dialog';
import { Segmented } from '@/components/ui/tabs';
import { submitForm } from '@/lib/http';
import { formatDate } from '@/lib/utils';

/** Staff clock in / clock out for a day, from the clock-in device; times can be corrected by hand. */
export default function StaffAttendance({ date, rows, summary, urls }) {
    const [tab, setTab] = useState('all');
    const [edit, setEdit] = useState(null);
    const [saving, setSaving] = useState(false);
    const list = tab === 'all' ? rows : rows.filter((r) => r.category === tab);
    const teaching = rows.filter((r) => r.category === 'teaching').length;

    const save = async () => {
        setSaving(true);
        const r = await submitForm(urls.save, { user_id: edit.id, date, in: edit.in ?? '', out: edit.out ?? '', note: edit.note ?? '' });
        setSaving(false);
        if (r.ok) {
            toast.success(r.message);
            setEdit(null);
            router.reload({ only: ['rows', 'summary'] });
        } else toast.error(r.errors ? Object.values(r.errors)[0] : r.message);
    };

    return (
        <>
            <Head title="Staff attendance" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
                <ModuleHeader crumbs={['People', 'Staff attendance']} title="Staff attendance" />

                <div className="flex flex-wrap items-end gap-3">
                    <div className="w-full sm:w-52">
                        <span className="mb-1.5 block text-sm font-medium">Date</span>
                        <DatePicker value={date} onChange={(v) => v && router.get(urls.self, { date: v }, { preserveScroll: true })} fromYear={2022} />
                    </div>
                </div>

                <StatStrip className="lg:grid-cols-3">
                    <StatCard label="Staff" value={summary.staff} />
                    <StatCard label="Clocked in" value={summary.in} />
                    <StatCard label="Not clocked in" value={summary.staff - summary.in} />
                </StatStrip>

                <Segmented
                    value={tab}
                    onChange={setTab}
                    options={[
                        { value: 'all', label: `All staff (${rows.length})` },
                        { value: 'teaching', label: `Teaching (${teaching})` },
                        { value: 'non_teaching', label: `Non-teaching (${rows.length - teaching})` },
                    ]}
                />

                <RegistryCard
                    key={tab}
                    title={formatDate(date, 'EEEE d MMMM yyyy')}
                    rows={list}
                    exportName={`staff-attendance-${date}`}
                    searchText={(r) => `${r.name} ${r.role} ${r.clock_id ?? ''}`}
                    columns={[
                        {
                            key: 'name',
                            header: 'Name',
                            sort: (r) => r.name,
                            exportValue: (r) => r.name,
                            cell: (r) => (
                                <div className="flex items-center gap-3">
                                    <Avatar src={r.photo} name={r.name} size="md" />
                                    <div className="min-w-0">
                                        <div className="truncate font-semibold">{r.name}</div>
                                        <div className="truncate text-xs text-fg-muted">{r.role}</div>
                                    </div>
                                </div>
                            ),
                        },
                        { key: 'clock_id', header: 'Clock-in ID', sort: (r) => r.clock_id ?? '', exportValue: (r) => r.clock_id ?? '', cell: (r) => r.clock_id || <span className="text-fg-subtle">—</span> },
                        { key: 'in', header: 'Clock in', sort: (r) => r.in ?? '99', exportValue: (r) => r.in ?? '', cell: (r) => <span className="tabular font-medium">{r.in || <span className="font-normal text-fg-subtle">—</span>}</span> },
                        { key: 'out', header: 'Clock out', sort: (r) => r.out ?? '', exportValue: (r) => r.out ?? '', cell: (r) => <span className="tabular font-medium">{r.out || <span className="font-normal text-fg-subtle">—</span>}</span> },
                        { key: 'hours', header: 'Hours', sort: (r) => r.hours ?? 0, exportValue: (r) => r.hours ?? '', className: 'tabular', cell: (r) => (r.hours !== null ? r.hours : '') },
                        { key: 'note', header: 'Note', exportValue: (r) => r.note ?? '', cell: (r) => <span className="text-xs text-fg-muted">{r.manual ? `Entered by hand${r.note ? ` · ${r.note}` : ''}` : ''}</span> },
                        {
                            key: 'action',
                            header: '',
                            className: 'text-right',
                            cell: (r) => <RowIconButton icon={Pencil} title="Set times" onClick={() => setEdit({ ...r })} />,
                        },
                    ]}
                />
            </div>

            <Modal
                open={!!edit}
                onOpenChange={(o) => !o && !saving && setEdit(null)}
                title={edit?.name}
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setEdit(null)} disabled={saving}>
                            Cancel
                        </Button>
                        <Button variant="primary" onClick={save} loading={saving}>
                            Save
                        </Button>
                    </>
                }
            >
                    {edit && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <label className="block">
                                <span className="mb-1.5 block text-sm font-medium">Clock in</span>
                                <input type="time" className={fieldInput} value={edit.in ?? ''} onChange={(e) => setEdit({ ...edit, in: e.target.value })} />
                            </label>
                            <label className="block">
                                <span className="mb-1.5 block text-sm font-medium">Clock out</span>
                                <input type="time" className={fieldInput} value={edit.out ?? ''} onChange={(e) => setEdit({ ...edit, out: e.target.value })} />
                            </label>
                            <label className="block sm:col-span-2">
                                <span className="mb-1.5 block text-sm font-medium">Note</span>
                                <input className={fieldInput} value={edit.note ?? ''} onChange={(e) => setEdit({ ...edit, note: e.target.value })} maxLength={191} />
                            </label>
                        </div>
                    )}
            </Modal>
        </>
    );
}

StaffAttendance.layout = withAppLayout;
