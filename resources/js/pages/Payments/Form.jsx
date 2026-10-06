import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { Banknote, Plus, X } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { Field, InfoCallout, ModuleHeader, ModuleTabs, NativeSelect, SetupCard, fieldInput } from '@/components/app/module';
import { Button } from '@/components/ui/button';
import { submitForm } from '@/lib/http';
import { cn, formatMoney } from '@/lib/utils';

/** Starting points for the breakdown; amounts are filled in by the school. */
const TEMPLATES = {
    new: ['Tuition', 'Medicals', 'Maintenance', 'P.T.A', 'Toiletries', 'Uniform', 'ID card', 'Admission form'],
    old: ['Tuition', 'Medicals', 'Maintenance', 'P.T.A', 'Toiletries', 'Books'],
};

/**
 * Create or edit a fee — same "Create / Show" pattern as the timetable design.
 * A fee can be itemised (Tuition, Medicals, P.T.A …); its amount is then the
 * sum of the items, and parents see the breakdown on their invoice.
 */
export default function PaymentForm({ mode, session, classes, categories, payment, currentTerm, urls }) {
    const editing = mode === 'edit';
    const blank = { title: '', my_class_id: '', method: 'Cash', amount: '', description: '', student_category: 'all', term: String(currentTerm ?? ''), items: [] };
    const initial = editing
        ? { ...blank, title: payment.title, description: payment.description ?? '', student_category: payment.student_category, term: String(payment.term ?? ''), items: payment.items.map((i) => ({ ...i })) }
        : blank;
    const [data, setData] = useState(initial);
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const set = (k, v) => {
        setData((d) => ({ ...d, [k]: v }));
        setErrors((e) => ({ ...e, [k]: undefined }));
    };

    const itemised = data.items.length > 0;
    const itemsTotal = data.items.reduce((t, i) => t + (Number(i.amount) || 0), 0);
    const setItem = (idx, k, v) => set('items', data.items.map((it, i) => (i === idx ? { ...it, [k]: v } : it)));
    const addItem = (name = '') => set('items', [...data.items, { name, amount: '' }]);
    const removeItem = (idx) => set('items', data.items.filter((_, i) => i !== idx));
    const applyTemplate = (key) => set('items', TEMPLATES[key].map((name) => ({ name, amount: '' })));

    const submit = async () => {
        const errs = {};
        if (data.title.trim().length < 3) errs.title = 'Enter a title of at least 3 characters.';
        if (itemised) {
            if (data.items.some((i) => !i.name.trim() || !(Number(i.amount) >= 0) || i.amount === '')) errs.items = 'Give every item a name and an amount, or remove it.';
            else if (!(itemsTotal > 0)) errs.items = 'The items must add up to more than zero.';
        } else if (!editing && !(Number(data.amount) > 0)) errs.amount = 'Enter the fee amount, or itemise it below.';
        setErrors(errs);
        if (Object.keys(errs).length) return;

        setProcessing(true);
        const items = JSON.stringify(data.items.map((i) => ({ name: i.name.trim(), amount: Number(i.amount) })));
        const payload = editing
            ? { title: data.title, description: data.description, student_category: data.student_category, term: data.term, items }
            : { ...data, amount: itemised ? itemsTotal : data.amount, items: itemised ? items : '' };
        const result = await submitForm(urls.submit, payload, { method: editing ? 'put' : 'post' });
        setProcessing(false);
        if (result.ok) {
            toast.success(result.message);
            router.visit(urls.index);
        } else {
            if (result.errors) setErrors(result.errors);
            toast.error(result.message);
        }
    };

    return (
        <>
            <Head title={editing ? 'Edit fee' : 'New fee'} />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6">
                <ModuleHeader crumbs={['Finance', 'Fee setup']} title="Fees" description="Set up the fees charged to students for the year." session={session} />
                <ModuleTabs value="create" onChange={(t) => t === 'list' && router.visit(urls.index)} createLabel="Fee" listLabel="Show Fees" editing={editing} />
                <SetupCard
                    title={editing ? 'Edit Fee' : 'New Fee Setup'}
                    description={editing ? `Reference ${payment.ref_no}. The class is fixed after creation.` : `The fee is created for the ${session} year.`}
                    icon={Banknote}
                    onSubmit={submit}
                    onReset={() => setData(initial)}
                    onCancel={() => router.visit(urls.index)}
                    submitLabel={editing ? 'Save Fee' : 'Create Fee'}
                    processing={processing}
                >
                    <Field label="Fee Title" required  error={errors.title} span={2}>
                        <input className={fieldInput} value={data.title} onChange={(e) => set('title', e.target.value)} placeholder="e.g., School fees" aria-invalid={!!errors.title || undefined} autoFocus />
                    </Field>
                    <Field label="Applies To" hint={editing ? 'Fixed after creation.' : 'Leave as “All classes” to charge every class.'} error={errors.my_class_id}>
                        <NativeSelect value={editing ? 'x' : data.my_class_id} onChange={(v) => set('my_class_id', v)} disabled={editing}>
                            {editing ? (
                                <option value="x">{payment.class ?? 'All classes'}</option>
                            ) : (
                                <>
                                    <option value="">All classes</option>
                                    {classes.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </>
                            )}
                        </NativeSelect>
                    </Field>
                    <Field label="Students" hint="New students are those admitted this year.">
                        <NativeSelect value={data.student_category} onChange={(v) => set('student_category', v)}>
                            {categories.map((c) => (
                                <option key={c.value} value={c.value}>
                                    {c.label}
                                </option>
                            ))}
                        </NativeSelect>
                    </Field>

                    <Field label="Term" hint="Bills are reported per term (Term 1 Aug–Dec, Term 2 Jan–Apr, Term 3 May–Jul).">
                        <NativeSelect value={data.term} onChange={(v) => set('term', v)}>
                            <option value="">Whole year</option>
                            <option value="1">Term 1</option>
                            <option value="2">Term 2</option>
                            <option value="3">Term 3</option>
                        </NativeSelect>
                    </Field>
                    <div className="md:col-span-2">
                        <div className="mb-1.5 flex flex-wrap items-center justify-between gap-2">
                            <span className="text-sm font-medium">
                                Breakdown <span className="font-normal text-fg-muted">· shown to parents on the invoice</span>
                            </span>
                            <div className="flex gap-1.5">
                                <Button size="xs" variant="ghost" onClick={() => applyTemplate('new')}>
                                    New student items
                                </Button>
                                <Button size="xs" variant="ghost" onClick={() => applyTemplate('old')}>
                                    Continuing student items
                                </Button>
                            </div>
                        </div>
                        <div className={cn('overflow-hidden rounded-lg shadow-field', errors.items && 'shadow-[0_0_0_1.5px_rgb(var(--danger))]')}>
                            {data.items.map((it, i) => (
                                <div key={i} className="flex items-center gap-2 border-b border-border px-2 py-1.5">
                                    <input
                                        className="h-9 min-w-0 flex-1 rounded-md border-0 bg-transparent px-2 text-sm focus:bg-muted focus:outline-none"
                                        value={it.name}
                                        onChange={(e) => setItem(i, 'name', e.target.value)}
                                        placeholder="Item, e.g. Tuition"
                                        aria-label={`Item ${i + 1} name`}
                                    />
                                    <input
                                        className="tabular h-9 w-32 rounded-md border-0 bg-transparent px-2 text-right text-sm focus:bg-muted focus:outline-none"
                                        type="number"
                                        min={0}
                                        inputMode="numeric"
                                        value={it.amount}
                                        onChange={(e) => setItem(i, 'amount', e.target.value)}
                                        placeholder="Amount"
                                        aria-label={`Item ${i + 1} amount`}
                                    />
                                    <Button size="icon-sm" variant="ghost" aria-label={`Remove ${it.name || 'item'}`} onClick={() => removeItem(i)}>
                                        <X />
                                    </Button>
                                </div>
                            ))}
                            <div className="flex items-center justify-between gap-3 bg-muted/50 px-3 py-2">
                                <Button size="xs" variant="ghost" onClick={() => addItem()}>
                                    <Plus />
                                    Add item
                                </Button>
                                {itemised && (
                                    <span className="tabular text-sm font-semibold">
                                        Total {formatMoney(itemsTotal)}
                                    </span>
                                )}
                            </div>
                        </div>
                        {errors.items && <p className="mt-1.5 text-xs text-danger-fg">{errors.items}</p>}
                    </div>

                    <Field
                        label="Amount"
                        required={!editing && !itemised}
                        error={errors.amount}
                        hint={itemised ? 'Sum of the breakdown.' : editing ? 'Add a breakdown to change the amount.' : undefined}
                    >
                        {itemised || editing ? (
                            <input className={`${fieldInput} tabular`} value={formatMoney(itemised ? itemsTotal : payment.amount)} disabled readOnly />
                        ) : (
                            <input className={`${fieldInput} tabular`} type="number" min={1} inputMode="numeric" value={data.amount} onChange={(e) => set('amount', e.target.value)} placeholder="e.g., 2500" aria-invalid={!!errors.amount || undefined} />
                        )}
                    </Field>
                    <Field label="Academic Year">
                        <NativeSelect value="s" onChange={() => {}} disabled>
                            <option value="s">{session.replace('-', ' - ')}</option>
                        </NativeSelect>
                    </Field>
                    <Field label="Description" error={errors.description} span={2}>
                        <input className={fieldInput} value={data.description ?? ''} onChange={(e) => set('description', e.target.value)} placeholder="Optional note shown on the fee list" />
                    </Field>
                </SetupCard>
               
            </div>
        </>
    );
}

PaymentForm.layout = withAppLayout;
