import { useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { withAppLayout } from '@/layouts/AppLayout';
import { ModuleHeader } from '@/components/app/module';
import { Panel } from '@/components/app/page';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Combobox, Select } from '@/components/ui/select';
import { cn, formatMoney, moneyInput } from '@/lib/utils';
import { Checkbox } from '@/components/ui/checkbox';
import { FormField } from '@/components/app/form';
import { ServicesPicker, EMPTY_SERVICES } from '@/components/students/services-picker';
import { submitForm } from '@/lib/http';

/** Pick a student and give them feeding, bus or extra-curricular (AssignServicesController). */
const TERMS = { 1: '1st term', 2: '2nd term', 3: '3rd term' };

export default function AssignServices({ session, students, student, catalogue, discounts, direct, urls }) {
    const [billYear, setBillYear] = useState(direct.year);
    const [billTerm, setBillTerm] = useState(String(direct.term));
    const [label, setLabel] = useState('');
    const [amount, setAmount] = useState('');
    const [confirmBill, setConfirmBill] = useState(false);
    const [directBusy, setDirectBusy] = useState(false);
    const finish = (r) => {
        setDirectBusy(false);
        setConfirmBill(false);
        if (r.ok) {
            toast.success(r.message);
            setLabel('');
            setAmount('');
        } else {
            toast.error(Object.values(r.errors ?? {})[0] ?? r.message);
        }
    };
    const billDirect = async () => {
        setDirectBusy(true);
        finish(await submitForm(urls.direct, { student: student.id, label, amount, year: billYear, term: billTerm }));
    };
    const [term, setTerm] = useState('next');
    const servicesFor = (t) => ({ ...EMPTY_SERVICES, ...((t === 'next' ? student?.next_services : student?.services) ?? {}) });
    const [services, setServices] = useState(servicesFor('next'));
    const [discount, setDiscount] = useState(student?.discount ?? '');
    const [staffChild, setStaffChild] = useState(!!student?.staff_child);
    const [busy, setBusy] = useState(false);
    useEffect(() => {
        setTerm('next');
        setServices(servicesFor('next'));
        setDiscount(student?.discount ?? '');
        setStaffChild(!!student?.staff_child);
    }, [student?.id]);
    const chooseTerm = (t) => {
        setTerm(t);
        setServices(servicesFor(t));
    };
    const option = (d) => ({ value: String(d.id), label: `${d.name} (${d.label})` });

    const choose = (id) => router.get(urls.self, id ? { student: id } : {}, { preserveScroll: true });
    const save = async () => {
        if (services.bus_direction && !services.bus_route_id) {
            toast.error('Choose the bus location, or set the bus to “No bus”.');
            return;
        }
        setBusy(true);
        const r = await submitForm(urls.save, { student: student.id, term, services: JSON.stringify(services), discount, staff_child: staffChild ? 1 : 0 });
        setBusy(false);
        if (r.ok) {
            toast.success(r.message || 'Saved.');
            router.reload({ only: ['student'], preserveScroll: true });
        } else {
            toast.error(r.message);
        }
    };

    return (
        <>
            <Head title="Assign services" />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6">
                <ModuleHeader crumbs={['Finance', 'Services', 'Assign services']} title="Assign services" session={session} />

                <div className="w-full sm:max-w-md">
                    <Combobox value={student?.id ?? ''} onChange={choose} options={students} placeholder="Choose a student" searchPlaceholder="Search name or admission no." />
                </div>

                {student && (
                    <Panel
                        title={
                            <span>
                                {student.name}
                                <span className="ml-2 text-sm font-normal text-fg-muted">{student.class}</span>
                            </span>
                        }
                        actions={
                            <Button size="sm" variant="ghost" asChild>
                                <Link href={student.invoice}>Invoice</Link>
                            </Button>
                        }
                        footer={
                            <div className="flex justify-end">
                                {term === 'direct' ? (
                                    <Button variant="primary" disabled={!label.trim() || !(Number(amount) > 0)} onClick={() => setConfirmBill(true)}>
                                        Bill
                                    </Button>
                                ) : (
                                    <Button variant="primary" loading={busy} onClick={save}>
                                        {term === 'next' ? 'Save for next term' : 'Bill this term'}
                                    </Button>
                                )}
                            </div>
                        }
                    >
                        <div className={cn('mb-6 grid gap-4 sm:grid-cols-2', term === 'direct' && 'hidden')}>
                            <FormField label="Discount">
                                <Select value={discount} onChange={(v) => setDiscount(v ?? '')} options={(discounts ?? []).map(option)} clearable clearLabel="No discount" placeholder="No discount" />
                            </FormField>
                            <label className="flex items-center gap-2 text-sm font-medium">
                                <Checkbox checked={staffChild} onCheckedChange={(v) => setStaffChild(v === true)} />
                                Staff child
                            </label>
                        </div>
                        <div className="mb-6 inline-flex rounded-lg bg-muted p-1">
                            {[['next', 'Next term'], ['current', 'This term'], ['direct', 'Bill direct']].map(([v, label]) => (
                                <button
                                    key={v}
                                    type="button"
                                    onClick={() => chooseTerm(v)}
                                    className={cn('rounded-md px-4 py-1.5 text-sm font-medium', term === v ? 'bg-surface text-fg shadow-sm' : 'text-fg-muted')}
                                >
                                    {label}
                                </button>
                            ))}
                        </div>
                        {term === 'direct' ? (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField label="Reason">
                                    <Input value={label} maxLength={120} onChange={(e) => setLabel(e.target.value)} />
                                </FormField>
                                <FormField label="Amount">
                                    <Input {...moneyInput} value={amount} onChange={(e) => setAmount(e.target.value)} />
                                </FormField>
                                <FormField label="Year">
                                    <Select value={billYear} onChange={(v) => v && setBillYear(v)} options={direct.years.map((y) => ({ value: y, label: y }))} clearable={false} />
                                </FormField>
                                <FormField label="Term">
                                    <Select value={billTerm} onChange={(v) => v && setBillTerm(v)} options={Object.entries(TERMS).map(([value, l]) => ({ value, label: l }))} clearable={false} />
                                </FormField>
                            </div>
                        ) : (
                            <ServicesPicker catalogue={catalogue} value={services} onChange={setServices} mode="edit" classId={student.class_id} />
                        )}
                    </Panel>
                )}

            </div>
            <ConfirmDialog
                open={confirmBill}
                onOpenChange={(o) => !directBusy && setConfirmBill(o)}
                title={`Bill ${student?.name ?? ''} GHS ${formatMoney(Number(amount) || 0)}?`}
                description={`${label} · ${TERMS[billTerm]} ${billYear}`}
                confirmLabel="Bill"
                tone="primary"
                loading={directBusy}
                onConfirm={billDirect}
            />
        </>
    );
}

AssignServices.layout = withAppLayout;
