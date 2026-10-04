import { useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { Bus, CircleAlert, CircleCheck, FileText, Music2, NotebookText, Pencil, Plus, Trash2, Upload, Utensils, X } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { ModuleHeader, RowIconButton } from '@/components/app/module';
import { EmptyState, Panel } from '@/components/app/page';
import { useConfirmAction } from '@/components/app/confirm-action';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input, Textarea } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { submitForm } from '@/lib/http';
import { cn, formatMoney } from '@/lib/utils';

const GROUP_ICONS = { feeding: Utensils, extracurricular: Music2, books: NotebookText };
const GROUP_HINTS = {
    feeding: 'e.g. Breakfast, Lunch, Fruits. Parents can pick any combination.',
    extracurricular: 'e.g. Dance, Music, Ballet.',
    books: 'Books & stationery at a fixed price.',
};

/**
 * Finance configuration: prices for optional services and bus routes, the
 * school-fee breakdowns, and what the admission notices contain.
 */
export default function FinanceConfig({ session, groups, options, routes, discounts, schoolFees, notices, urls }) {
    const [tab, setTab] = useState(() => new URLSearchParams(window.location.search).get('tab') || 'services');
    const [confirm, confirmDialog] = useConfirmAction();
    const reload = (only) => router.reload({ only, preserveScroll: true });

    const changeTab = (t) => {
        setTab(t);
        const url = new URL(window.location.href);
        url.searchParams.set('tab', t);
        window.history.replaceState(window.history.state, '', url);
    };

    return (
        <>
            <Head title="Finance configuration" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
                <ModuleHeader
                    crumbs={['Finance', 'Configuration']}
                    title="Finance configuration"
                    description="Prices for optional services and bus routes, school-fee breakdowns, and the notices parents receive at admission."
                    session={session}
                />

                <Tabs value={tab} onValueChange={changeTab}>
                    <TabsList className="mb-6">
                        <TabsTrigger value="services" count={options.filter((o) => o.active).length}>
                            Optional services
                        </TabsTrigger>
                        <TabsTrigger value="bus" count={routes.filter((r) => r.active).length}>
                            Bus routes
                        </TabsTrigger>
                        <TabsTrigger value="discounts" count={discounts.filter((d) => d.active).length}>
                            Discounts
                        </TabsTrigger>
                        <TabsTrigger value="fees" count={schoolFees.length}>
                            School fees
                        </TabsTrigger>
                        <TabsTrigger value="notices">Admission notices</TabsTrigger>
                    </TabsList>

                    <TabsContent value="services">
                        <div className="grid gap-6 lg:grid-cols-3">
                            {groups.map((g) => (
                                <OptionGroup
                                    key={g.key}
                                    group={g}
                                    rows={options.filter((o) => o.group === g.key)}
                                    urls={urls}
                                    confirm={confirm}
                                    onSaved={() => reload(['options'])}
                                />
                            ))}
                        </div>
                        <p className="mt-4 text-sm text-fg-muted">
                            Prices apply to new selections. Students already billed keep the price they were billed at; change a student’s services from their edit page to re-bill.
                        </p>
                    </TabsContent>

                    <TabsContent value="bus">
                        <BusRoutes rows={routes} urls={urls} confirm={confirm} onSaved={() => reload(['routes'])} />
                    </TabsContent>

                    <TabsContent value="discounts">
                        <Discounts rows={discounts} urls={urls} confirm={confirm} onSaved={() => reload(['discounts'])} />
                    </TabsContent>

                    <TabsContent value="fees">
                        <SchoolFees rows={schoolFees} session={session} urls={urls} />
                    </TabsContent>

                    <TabsContent value="notices">
                        <NoticeSettings notices={notices} url={urls.notices} onSaved={() => reload(['notices'])} />
                    </TabsContent>
                </Tabs>
            </div>
            {confirmDialog}
        </>
    );
}

FinanceConfig.layout = withAppLayout;

/* ------------------------------------------------------------------ */

function OptionGroup({ group, rows, urls, confirm, onSaved }) {
    const Icon = GROUP_ICONS[group.key];
    const [editing, setEditing] = useState(null);
    const [adding, setAdding] = useState({ name: '', amount: '' });
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);

    const save = async (row, values) => {
        const errs = {};
        if (!values.name.trim()) errs.name = 'Enter a name.';
        if (values.amount === '' || !(Number(values.amount) >= 0)) errs.amount = 'Enter a price.';
        setErrors(errs);
        if (Object.keys(errs).length) return false;
        setBusy(true);
        const payload = { group: group.key, name: values.name.trim(), amount: Number(values.amount), active: values.active === false ? 0 : 1 };
        const r = row ? await submitForm(urls.option.replace(':id', row.id), payload, { method: 'put' }) : await submitForm(urls.options, payload);
        setBusy(false);
        if (r.ok) {
            toast.success(row ? `${payload.name} updated` : `${payload.name} added`);
            onSaved();
            return true;
        }
        setErrors(r.errors ?? {});
        toast.error(r.errors ? Object.values(r.errors)[0] : r.message);
        return false;
    };

    return (
        <Panel
            title={
                <span className="flex items-center gap-2">
                    {Icon && <Icon className="size-4 text-fg-subtle" />}
                    {group.label}
                </span>
            }
            description={GROUP_HINTS[group.key]}
            flush
        >
            <ul className="divide-y divide-border">
                {rows.map((o) =>
                    editing?.id === o.id ? (
                        <li key={o.id} className="px-4 py-3">
                            <InlineFields values={editing} onChange={setEditing} errors={errors} />
                            <label className="mt-2 flex items-center gap-2 text-sm">
                                <Checkbox checked={editing.active} onCheckedChange={(v) => setEditing({ ...editing, active: v === true })} />
                                Available for new selections
                            </label>
                            <div className="mt-3 flex justify-end gap-2">
                                <Button size="sm" variant="ghost" onClick={() => setEditing(null)}>
                                    Cancel
                                </Button>
                                <Button size="sm" variant="primary" loading={busy} onClick={async () => (await save(o, editing)) && setEditing(null)}>
                                    Save
                                </Button>
                            </div>
                        </li>
                    ) : (
                        <li key={o.id} className={cn('flex items-center gap-3 px-4 py-2.5', !o.active && 'opacity-60')}>
                            <div className="min-w-0 flex-1">
                                <div className="truncate text-sm font-medium">{o.name}</div>
                                <div className="text-xs text-fg-muted">
                                    {o.students} {o.students === 1 ? 'student' : 'students'} this year{!o.active && ' · inactive'}
                                </div>
                            </div>
                            <span className="tabular text-sm font-semibold">{formatMoney(o.amount)}</span>
                            <RowIconButton icon={Pencil} title="Edit" onClick={() => setEditing({ id: o.id, name: o.name, amount: String(o.amount), active: o.active })} />
                            <RowIconButton
                                icon={Trash2}
                                title="Delete"
                                tone="danger"
                                onClick={() =>
                                    confirm({
                                        title: `Delete ${o.name}?`,
                                        description: o.students
                                            ? 'Students have been billed for this service, so it will be made inactive instead and kept on their invoices.'
                                            : 'It will no longer be offered at admission.',
                                        confirmLabel: o.students ? 'Make inactive' : 'Delete',
                                        method: 'delete',
                                        url: urls.option.replace(':id', o.id),
                                    })
                                }
                            />
                        </li>
                    ),
                )}
                {rows.length === 0 && <li className="px-4 py-4 text-sm text-fg-subtle">Nothing added yet.</li>}
            </ul>
            <form
                className="border-t border-border bg-muted/40 px-4 py-3"
                onSubmit={async (e) => {
                    e.preventDefault();
                    if (await save(null, adding)) setAdding({ name: '', amount: '' });
                }}
            >
                <InlineFields values={adding} onChange={setAdding} errors={editing ? {} : errors} placeholder={group.key === 'books' ? 'Books & stationery' : 'Name'} />
                <div className="mt-2 flex justify-end">
                    <Button type="submit" size="sm" loading={busy && !editing}>
                        <Plus />
                        Add
                    </Button>
                </div>
            </form>
        </Panel>
    );
}

function InlineFields({ values, onChange, errors, placeholder = 'Name' }) {
    return (
        <div className="grid grid-cols-[1fr_110px] gap-2">
            <div>
                <Input className="h-9" value={values.name} onChange={(e) => onChange({ ...values, name: e.target.value })} placeholder={placeholder} invalid={!!errors.name} aria-label="Name" />
                {errors.name && <p className="mt-1 text-xs text-danger-fg">{errors.name}</p>}
            </div>
            <div>
                <Input
                    className="tabular h-9 text-right"
                    type="number"
                    min={0}
                    inputMode="numeric"
                    value={values.amount}
                    onChange={(e) => onChange({ ...values, amount: e.target.value })}
                    placeholder="Price"
                    invalid={!!errors.amount}
                    aria-label="Price"
                />
                {errors.amount && <p className="mt-1 text-xs text-danger-fg">{errors.amount}</p>}
            </div>
        </div>
    );
}

/* ------------------------------------------------------------------ */

function BusRoutes({ rows, urls, confirm, onSaved }) {
    const empty = { name: '', amount_both: '', amount_one_way: '', active: true };
    const [form, setForm] = useState(empty);
    const [editingId, setEditingId] = useState(null);
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);
    const half = form.amount_both !== '' ? Math.round(Number(form.amount_both) / 2) : null;

    const startEdit = (r) => {
        setEditingId(r.id);
        setErrors({});
        setForm({ name: r.name, amount_both: String(r.amount_both), amount_one_way: String(r.amount_one_way), active: r.active });
    };
    const cancel = () => {
        setEditingId(null);
        setErrors({});
        setForm(empty);
    };

    const submit = async (e) => {
        e.preventDefault();
        const errs = {};
        if (!form.name.trim()) errs.name = 'Enter the location.';
        if (form.amount_both === '' || !(Number(form.amount_both) >= 0)) errs.amount_both = 'Enter the both-ways price.';
        setErrors(errs);
        if (Object.keys(errs).length) return;
        setBusy(true);
        const payload = { ...form, active: form.active ? 1 : 0 };
        const r = editingId ? await submitForm(urls.route.replace(':id', editingId), payload, { method: 'put' }) : await submitForm(urls.routes, payload);
        setBusy(false);
        if (r.ok) {
            toast.success(editingId ? `${form.name} updated` : `${form.name} added`);
            cancel();
            onSaved();
        } else {
            setErrors(r.errors ?? {});
            toast.error(r.errors ? Object.values(r.errors)[0] : r.message);
        }
    };

    return (
        <div className="grid gap-6 lg:grid-cols-[1fr_340px]">
            <Panel title="Bus routes" description="One price for both ways (in and out); one way is usually half." flush>
                {rows.length ? (
                    <table className="w-full text-left">
                        <thead>
                            <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                                <th className="h-9 px-4">Location</th>
                                <th className="h-9 px-3 text-right">Both ways</th>
                                <th className="h-9 px-3 text-right">In or out only</th>
                                <th className="hidden h-9 px-3 text-right sm:table-cell">Students</th>
                                <th className="h-9 w-24 px-4" />
                            </tr>
                        </thead>
                        <tbody className="tabular">
                            {rows.map((r) => (
                                <tr key={r.id} className={cn('border-b border-border last:border-0', editingId === r.id && 'bg-primary-soft/30', !r.active && 'opacity-60')}>
                                    <td className="px-4 py-2.5 font-medium">
                                        {r.name}
                                        {!r.active && (
                                            <Badge className="ml-2" shape="tag">
                                                Inactive
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="px-3 text-right">{formatMoney(r.amount_both)}</td>
                                    <td className="px-3 text-right">{formatMoney(r.amount_one_way)}</td>
                                    <td className="hidden px-3 text-right text-fg-muted sm:table-cell">{r.students}</td>
                                    <td className="px-4 text-right">
                                        <div className="flex justify-end gap-0.5">
                                            <RowIconButton icon={Pencil} title="Edit" onClick={() => startEdit(r)} />
                                            <RowIconButton
                                                icon={Trash2}
                                                title="Delete"
                                                tone="danger"
                                                onClick={() =>
                                                    confirm({
                                                        title: `Delete ${r.name}?`,
                                                        description: r.students
                                                            ? 'Students have been billed for this route, so it will be made inactive instead and kept on their invoices.'
                                                            : 'It will no longer be offered at admission.',
                                                        confirmLabel: r.students ? 'Make inactive' : 'Delete',
                                                        method: 'delete',
                                                        url: urls.route.replace(':id', r.id),
                                                    })
                                                }
                                            />
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                ) : (
                    <EmptyState compact icon={Bus} title="No bus routes yet" description="Add the locations the school bus serves and their prices." />
                )}
            </Panel>

            <form onSubmit={submit} className="panel h-fit p-5">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-base font-semibold">{editingId ? 'Edit route' : 'Add a route'}</h3>
                    {editingId && (
                        <Button size="icon-sm" variant="ghost" aria-label="Cancel editing" onClick={cancel}>
                            <X />
                        </Button>
                    )}
                </div>
                <div className="space-y-4">
                    <Labeled label="Location" error={errors.name}>
                        <Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="e.g. Adenta" invalid={!!errors.name} />
                    </Labeled>
                    <Labeled label="Both ways (in & out)" error={errors.amount_both}>
                        <Input
                            className="tabular"
                            type="number"
                            min={0}
                            inputMode="numeric"
                            value={form.amount_both}
                            onChange={(e) => setForm({ ...form, amount_both: e.target.value })}
                            placeholder="e.g. 4000"
                            invalid={!!errors.amount_both}
                        />
                    </Labeled>
                    <Labeled label="One way (in only or out only)" error={errors.amount_one_way} hint={half !== null ? `Leave blank to use half: ${formatMoney(half)}` : 'Leave blank to use half the both-ways price.'}>
                        <Input
                            className="tabular"
                            type="number"
                            min={0}
                            inputMode="numeric"
                            value={form.amount_one_way}
                            onChange={(e) => setForm({ ...form, amount_one_way: e.target.value })}
                            placeholder={half !== null ? String(half) : 'Half'}
                        />
                    </Labeled>
                    {editingId && (
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox checked={form.active} onCheckedChange={(v) => setForm({ ...form, active: v === true })} />
                            Available for new selections
                        </label>
                    )}
                </div>
                <Button type="submit" variant="primary" className="mt-5 w-full" loading={busy}>
                    {editingId ? 'Save route' : 'Add route'}
                </Button>
            </form>
        </div>
    );
}

function Discounts({ rows, urls, confirm, onSaved }) {
    const empty = { name: '', percent: '', active: true };
    const [form, setForm] = useState(empty);
    const [editingId, setEditingId] = useState(null);
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);

    const cancel = () => {
        setEditingId(null);
        setErrors({});
        setForm(empty);
    };

    const submit = async (e) => {
        e.preventDefault();
        const errs = {};
        if (!form.name.trim()) errs.name = 'Enter a name.';
        const pct = Number(form.percent);
        if (!(pct >= 1 && pct <= 100)) errs.percent = 'Enter a percentage from 1 to 100.';
        setErrors(errs);
        if (Object.keys(errs).length) return;
        setBusy(true);
        const payload = { name: form.name.trim(), percent: pct, active: form.active ? 1 : 0 };
        const r = editingId ? await submitForm(urls.discount.replace(':id', editingId), payload, { method: 'put' }) : await submitForm(urls.discounts, payload);
        setBusy(false);
        if (r.ok) {
            toast.success(editingId ? `${payload.name} updated` : `${payload.name} added`);
            cancel();
            onSaved();
        } else {
            setErrors(r.errors ?? {});
            toast.error(r.errors ? Object.values(r.errors)[0] : r.message);
        }
    };

    return (
        <div className="grid gap-6 lg:grid-cols-[1fr_340px]">
            <Panel title="Tuition discounts" description="Taken off the tuition part of school fees (or the whole fee when it is not itemised). Optional services are never discounted." flush>
                {rows.length ? (
                    <table className="w-full text-left">
                        <thead>
                            <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                                <th className="h-9 px-4">Discount</th>
                                <th className="h-9 px-3 text-right">Off tuition</th>
                                <th className="h-9 px-3 text-right">Students</th>
                                <th className="h-9 w-24 px-4" />
                            </tr>
                        </thead>
                        <tbody className="tabular">
                            {rows.map((d) => (
                                <tr key={d.id} className={cn('border-b border-border last:border-0', editingId === d.id && 'bg-primary-soft/30', !d.active && 'opacity-60')}>
                                    <td className="px-4 py-2.5 font-medium">
                                        {d.name}
                                        {!d.active && (
                                            <Badge className="ml-2" shape="tag">
                                                Inactive
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="px-3 text-right font-semibold">{d.percent}%</td>
                                    <td className="px-3 text-right text-fg-muted">{d.students}</td>
                                    <td className="px-4 text-right">
                                        <div className="flex justify-end gap-0.5">
                                            <RowIconButton
                                                icon={Pencil}
                                                title="Edit"
                                                onClick={() => {
                                                    setEditingId(d.id);
                                                    setErrors({});
                                                    setForm({ name: d.name, percent: String(d.percent), active: d.active });
                                                }}
                                            />
                                            <RowIconButton
                                                icon={Trash2}
                                                title="Delete"
                                                tone="danger"
                                                onClick={() =>
                                                    confirm({
                                                        title: `Delete ${d.name}?`,
                                                        description: d.students
                                                            ? `${d.students} students have this discount, so it will be made inactive instead. They keep it until their record is changed.`
                                                            : 'It will no longer be offered.',
                                                        confirmLabel: d.students ? 'Make inactive' : 'Delete',
                                                        method: 'delete',
                                                        url: urls.discount.replace(':id', d.id),
                                                    })
                                                }
                                            />
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                ) : (
                    <EmptyState compact title="No discounts yet" />
                )}
            </Panel>

            <form onSubmit={submit} className="panel h-fit p-5">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-base font-semibold">{editingId ? 'Edit discount' : 'Add a discount'}</h3>
                    {editingId && (
                        <Button size="icon-sm" variant="ghost" aria-label="Cancel editing" onClick={cancel}>
                            <X />
                        </Button>
                    )}
                </div>
                <div className="space-y-4">
                    <Labeled label="Name" error={errors.name}>
                        <Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="e.g. Staff child" invalid={!!errors.name} />
                    </Labeled>
                    <Labeled label="Percentage of tuition" error={errors.percent} hint="100 means tuition is free.">
                        <Input
                            className="tabular"
                            type="number"
                            min={1}
                            max={100}
                            inputMode="numeric"
                            value={form.percent}
                            onChange={(e) => setForm({ ...form, percent: e.target.value })}
                            placeholder="e.g. 50"
                            invalid={!!errors.percent}
                        />
                    </Labeled>
                    {editingId && (
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox checked={form.active} onCheckedChange={(v) => setForm({ ...form, active: v === true })} />
                            Available for new students
                        </label>
                    )}
                </div>
                <Button type="submit" variant="primary" className="mt-5 w-full" loading={busy}>
                    {editingId ? 'Save discount' : 'Add discount'}
                </Button>
                <p className="mt-3 text-xs text-fg-muted">Changing a percentage recalculates what students on this discount owe this year.</p>
            </form>
        </div>
    );
}

function Labeled({ label, error, hint, children }) {
    return (
        <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium">{label}</label>
            {children}
            {error ? <p className="text-xs text-danger-fg">{error}</p> : hint ? <p className="text-xs text-fg-muted">{hint}</p> : null}
        </div>
    );
}

/* ------------------------------------------------------------------ */

function SchoolFees({ rows, session, urls }) {
    return (
        <Panel
            title={`School fees · ${session}`}
            description="Each fee can be itemised (Tuition, Medicals, Maintenance, P.T.A …) and limited to new or continuing students. Parents see the breakdown on their invoice."
            flush
            actions={
                <>
                    <Button size="sm" asChild>
                        <Link href={urls.fee_setup}>Fee setup</Link>
                    </Button>
                    <Button size="sm" variant="primary" asChild>
                        <Link href={urls.fee_create}>
                            <Plus />
                            New fee
                        </Link>
                    </Button>
                </>
            }
        >
            {rows.length ? (
                <table className="w-full text-left">
                    <thead>
                        <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                            <th className="h-9 px-4">Fee</th>
                            <th className="h-9 px-3">Class</th>
                            <th className="h-9 px-3">Students</th>
                            <th className="h-9 px-3">Breakdown</th>
                            <th className="h-9 px-3 text-right">Amount</th>
                            <th className="h-9 w-16 px-4" />
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((f, i) => (
                            <tr key={i} className="border-b border-border last:border-0">
                                <td className="px-4 py-2.5 font-medium">{f.title}</td>
                                <td className="px-3 text-sm">{f.class}</td>
                                <td className="px-3 text-sm text-fg-muted">{f.category}</td>
                                <td className="px-3 text-sm">{f.items ? `${f.items} items` : <span className="text-warning-fg">Not itemised</span>}</td>
                                <td className="tabular px-3 text-right font-semibold">{formatMoney(f.amount)}</td>
                                <td className="px-4 text-right">
                                    <Button size="xs" variant="ghost" asChild>
                                        <Link href={f.edit_url}>Edit</Link>
                                    </Button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            ) : (
                <EmptyState compact title="No school fees for this year" description="Create one fee for new students and one for continuing students, then add their items." />
            )}
        </Panel>
    );
}

/* ------------------------------------------------------------------ */

function NoticeSettings({ notices, url, onSaved }) {
    const [instructions, setInstructions] = useState(notices.payment_instructions ?? '');
    const [file, setFile] = useState(null);
    const [remove, setRemove] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);
    const fileRef = useRef(null);

    const save = async () => {
        setBusy(true);
        setError(null);
        const payload = { payment_instructions: instructions, remove_policy: remove ? 1 : 0 };
        if (file) payload.policy = file;
        const r = await submitForm(url, payload);
        setBusy(false);
        if (r.ok) {
            toast.success('Admission notice settings saved');
            setFile(null);
            setRemove(false);
            onSaved();
        } else {
            setError(r.errors?.policy ?? r.errors?.payment_instructions ?? r.message);
            toast.error(r.message);
        }
    };

    return (
        <div className="grid gap-6 lg:grid-cols-[1fr_340px]">
            <div className="space-y-6">
                <Panel title="School policy" description="Attached to the admission email sent to parents.">
                    <div className="flex flex-wrap items-center gap-3">
                        <FileText className="size-5 text-fg-subtle" />
                        {file ? (
                            <span className="text-sm font-medium">{file.name} (new)</span>
                        ) : notices.policy_url && !remove ? (
                            <a href={notices.policy_url} target="_blank" rel="noreferrer" className="text-sm font-medium text-primary hover:underline">
                                {notices.policy_name}
                            </a>
                        ) : (
                            <span className="text-sm text-fg-subtle">No policy uploaded</span>
                        )}
                        <div className="ml-auto flex gap-2">
                            <input
                                ref={fileRef}
                                type="file"
                                accept=".pdf,.doc,.docx"
                                className="hidden"
                                onChange={(e) => {
                                    setFile(e.target.files?.[0] ?? null);
                                    setRemove(false);
                                    e.target.value = '';
                                }}
                            />
                            <Button size="sm" onClick={() => fileRef.current?.click()}>
                                <Upload />
                                {notices.policy_url || file ? 'Replace' : 'Upload'}
                            </Button>
                            {(notices.policy_url || file) && !remove && (
                                <Button
                                    size="sm"
                                    variant="danger-ghost"
                                    onClick={() => {
                                        setFile(null);
                                        setRemove(!!notices.policy_url);
                                    }}
                                >
                                    Remove
                                </Button>
                            )}
                        </div>
                    </div>
                    <p className="mt-3 text-xs text-fg-muted">PDF or Word, up to 10 MB.</p>
                </Panel>

                <Panel title="How to pay" description="Shown on the fees email and the parent’s fees statement.">
                    <Textarea
                        rows={6}
                        value={instructions}
                        onChange={(e) => setInstructions(e.target.value)}
                        placeholder={'e.g.\nBank: … Account name: … Account no.: …\nMobile money: 055 000 0000 (Desvy International School)\nUse the student’s admission number as the reference.'}
                    />
                </Panel>

                {error && (
                    <p className="flex items-center gap-2 text-sm text-danger-fg">
                        <CircleAlert className="size-4" />
                        {error}
                    </p>
                )}
                <div className="flex justify-end">
                    <Button variant="primary" loading={busy} onClick={save}>
                        Save notice settings
                    </Button>
                </div>
            </div>

            <Panel title="Delivery" description="Set on the server in the .env file.">
                <ul className="space-y-4 text-sm">
                    <DeliveryRow
                        ok={notices.mail_ready}
                        title="Email"
                        okText="Mail server configured."
                        badText="Not configured. Set MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD and MAIL_FROM_ADDRESS. Until then emails are recorded as not sent."
                    />
                    <DeliveryRow
                        ok={notices.sms_ready}
                        title="SMS"
                        okText={`Sending with ${notices.sms_driver}.`}
                        badText="Not configured. Set SMS_DRIVER to arkesel or hubtel with its API keys and SMS_SENDER_ID. Until then SMS are written to the log only."
                    />
                </ul>
            </Panel>
        </div>
    );
}

function DeliveryRow({ ok, title, okText, badText }) {
    return (
        <li className="flex gap-3">
            {ok ? <CircleCheck className="mt-0.5 size-4 shrink-0 text-success" /> : <CircleAlert className="mt-0.5 size-4 shrink-0 text-warning" />}
            <div>
                <div className="font-medium">{title}</div>
                <p className="text-fg-muted">{ok ? okText : badText}</p>
            </div>
        </li>
    );
}
