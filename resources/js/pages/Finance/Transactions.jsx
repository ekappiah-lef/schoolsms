import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowDownLeft, ArrowUpRight, Landmark, Pencil, Trash2, Wallet } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { Field, InfoCallout, ModuleHeader, ModuleTabs, NativeSelect, RegistryCard, RowIconButton, SetupCard, SummaryTile, fieldInput, useModuleTab } from '@/components/app/module';
import { useConfirmAction } from '@/components/app/confirm-action';
import { DatePicker } from '@/components/ui/date-picker';
import { Segmented } from '@/components/ui/tabs';
import { useModuleForm } from '@/lib/use-module-form';
import { PeriodFilter } from '@/components/fees/period-picker';
import { cn, formatDate, formatMoney } from '@/lib/utils';

const METHODS = ['Cash', 'Mobile payment', 'Bank transfer', 'Cheque', 'Other'];

/** Income & expenses outside student fees — Record / Show pattern (FinanceTransactionController, JSON store & update). */
export default function FinanceTransactions({ session, categories, usedCategories, totals, selection, transactions, editing, urls }) {
    const [tab, setTab] = useModuleTab(editing ? 'create' : 'list');
    const [confirm, confirmDialog] = useConfirmAction();
    const [typeFilter, setTypeFilter] = useState('all');
    const today = formatDate(new Date(), 'yyyy-MM-dd');

    const form = useModuleForm({
        initial: { type: 'expense', category: '', amount: '', date: today, method: 'Cash', reference: '', description: '' },
        editing,
        storeUrl: urls.store,
        indexUrl: urls.index,
        only: ['transactions', 'totals', 'usedCategories'],
        onCreated: () => setTab('list'),
        validate: (d) => ({
            ...(!d.category.trim() ? { category: 'Choose or type a category.' } : {}),
            ...(!(Number(d.amount) > 0) ? { amount: 'Enter an amount greater than zero.' } : {}),
            ...(!d.date ? { date: 'Choose the date.' } : {}),
        }),
    });

    const suggestions = [...new Set([...(categories[form.data.type] ?? []), ...(usedCategories?.[form.data.type] ?? [])])];
    const rows = transactions.filter((t) => typeFilter === 'all' || t.type === typeFilter);
    const income = transactions.filter((t) => t.type === 'income').reduce((a, t) => a + t.amount, 0);
    const expenses = transactions.filter((t) => t.type === 'expense').reduce((a, t) => a + t.amount, 0);

    return (
        <>
            <Head title="Income & expenses" />
            <div className={cn('mx-auto flex w-full flex-col gap-6', tab === 'create' ? 'max-w-4xl' : 'max-w-7xl')}>
                <ModuleHeader
                    crumbs={['Finance', 'Income & expenses']}
                    title="Income & expenses"
                    session={session}
                />
                <ModuleTabs
                    value={tab}
                    onChange={(t) => (editing && t === 'list' ? router.visit(urls.index) : setTab(t))}
                    createLabel="Entry"
                    listLabel="Show Entries"
                    count={transactions.length}
                    editing={!!editing}
                />

                {tab === 'create' ? (
                    <div className="flex flex-col gap-3">
                        <SetupCard
                            title={editing ? 'Edit entry' : 'Record income or expense'}
                            description="Every entry updates the cash position on the finance dashboard."
                            icon={Wallet}
                            onSubmit={form.submit}
                            onReset={form.reset}
                            onCancel={() => (editing ? router.visit(urls.index) : setTab('list'))}
                            submitLabel={editing ? 'Save entry' : 'Record entry'}
                            processing={form.processing}
                        >
                            <Field label="Type" required span={2}>
                                <Segmented
                                    className="self-start"
                                    value={form.data.type}
                                    onChange={(v) => form.set('type', v)}
                                    options={[
                                        { value: 'income', label: 'Income (money in)', icon: <ArrowDownLeft /> },
                                        { value: 'expense', label: 'Expense (money out)', icon: <ArrowUpRight /> },
                                    ]}
                                />
                            </Field>
                            <Field label="Category" required error={form.errors.category} aside="Choose or type your own">
                                <input
                                    className={fieldInput}
                                    list="tx-categories"
                                    value={form.data.category}
                                    onChange={(e) => form.set('category', e.target.value)}
                                    placeholder={form.data.type === 'income' ? 'e.g. Capital injection' : 'e.g. Salaries'}
                                    aria-invalid={!!form.errors.category || undefined}
                                />
                                <datalist id="tx-categories">
                                    {suggestions.map((c) => (
                                        <option key={c} value={c} />
                                    ))}
                                </datalist>
                            </Field>
                            <Field label="Amount" required error={form.errors.amount}>
                                <input
                                    className={`${fieldInput} tabular`}
                                    type="number"
                                    min={1}
                                    inputMode="numeric"
                                    value={form.data.amount}
                                    onChange={(e) => form.set('amount', e.target.value)}
                                    placeholder="e.g. 1000000"
                                    aria-invalid={!!form.errors.amount || undefined}
                                />
                            </Field>
                            <Field label="Date" required error={form.errors.date}>
                                <DatePicker value={form.data.date} onChange={(v) => form.set('date', v)} fromYear={2015} defaultMonth={new Date()} />
                            </Field>
                            <Field label="Payment method">
                                <NativeSelect value={form.data.method} onChange={(v) => form.set('method', v)}>
                                    {METHODS.map((m) => (
                                        <option key={m} value={m}>
                                            {m}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                            <Field label="Reference" error={form.errors.reference} hint="Cheque, transfer or invoice number.">
                                <input className={fieldInput} value={form.data.reference} onChange={(e) => form.set('reference', e.target.value)} />
                            </Field>
                            <Field label="Description" error={form.errors.description}>
                                <input className={fieldInput} value={form.data.description} onChange={(e) => form.set('description', e.target.value)} placeholder="What it was for" />
                            </Field>
                        </SetupCard>
                        <InfoCallout>Fee payments recorded under Student payments are already counted as income. Use this page for everything else.</InfoCallout>
                    </div>
                ) : (
                    <>
                        <div className="flex justify-end">
                            <PeriodFilter selection={selection} url={urls.index} />
                        </div>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <Tile label={`Money received · ${selection.label}`} value={totals.received} tone="success" />
                            <Tile label={`Expenses · ${selection.label}`} value={totals.expenses} tone="danger" />
                            <Tile label={`${selection.balanceName} · ${selection.label}`} value={totals.balance} strong />
                        </div>
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <Segmented
                                value={typeFilter}
                                onChange={setTypeFilter}
                                options={[
                                    { value: 'all', label: 'All' },
                                    { value: 'income', label: 'Income' },
                                    { value: 'expense', label: 'Expenses' },
                                ]}
                            />
                            <Link href={urls.dashboard} className="text-sm font-medium text-primary hover:underline">
                                Finance dashboard
                            </Link>
                        </div>
                        <RegistryCard
                            title="Entries"
                            rows={rows}
                            exportName="income-expenses"
                            searchText={(r) => `${r.category} ${r.description ?? ''} ${r.reference ?? ''} ${r.method ?? ''}`}
                            columns={[
                                { key: 'sn', header: 'S/N', headerClassName: 'w-16' },
                                {
                                    key: 'date',
                                    header: 'Date',
                                    sort: (r) => r.date,
                                    exportValue: (r) => r.date,
                                    cell: (r) => <span className="tabular">{formatDate(r.date, 'dd/MM/yyyy')}</span>,
                                },
                                {
                                    key: 'category',
                                    header: 'Category',
                                    sort: (r) => r.category,
                                    exportValue: (r) => r.category,
                                    cell: (r) => (
                                        <div className="flex items-center gap-2.5">
                                            <span className={cn('flex size-7 shrink-0 items-center justify-center rounded-full', r.type === 'income' ? 'bg-success-soft text-success-fg' : 'bg-danger-soft text-danger-fg')}>
                                                {r.type === 'income' ? <ArrowDownLeft className="size-3.5" /> : <ArrowUpRight className="size-3.5" />}
                                            </span>
                                            <div className="min-w-0">
                                                <div className="font-medium">{r.category}</div>
                                                {r.description && <div className="max-w-xs truncate text-xs text-fg-muted">{r.description}</div>}
                                            </div>
                                        </div>
                                    ),
                                },
                                { key: 'method', header: 'Method', sort: (r) => r.method ?? '', exportValue: (r) => r.method, cell: (r) => <span className="text-fg-muted">{[r.method, r.reference].filter(Boolean).join(' · ') || '—'}</span> },
                                {
                                    key: 'amount',
                                    header: 'Amount',
                                    headerClassName: 'text-right',
                                    className: 'text-right',
                                    sort: (r) => (r.type === 'income' ? r.amount : -r.amount),
                                    exportValue: (r) => (r.type === 'income' ? r.amount : -r.amount),
                                    cell: (r) => (
                                        <span className={cn('tabular font-semibold', r.type === 'income' ? 'text-success-fg' : 'text-danger-fg')}>
                                            {r.type === 'income' ? '+' : '−'}
                                            {formatMoney(r.amount)}
                                        </span>
                                    ),
                                },
                                { key: 'by', header: 'Recorded by', sort: (r) => r.by ?? '', exportValue: (r) => r.by, cell: (r) => <span className="text-fg-muted">{r.by ?? '—'}</span> },
                                {
                                    key: 'action',
                                    header: 'Action',
                                    headerClassName: 'text-right',
                                    className: 'text-right',
                                    cell: (r) => (
                                        <div className="flex justify-end gap-0.5">
                                            <RowIconButton icon={Pencil} title="Edit" onClick={() => router.visit(r.urls.edit)} />
                                            {r.urls.destroy && (
                                                <RowIconButton
                                                    icon={Trash2}
                                                    title="Delete"
                                                    tone="danger"
                                                    onClick={() =>
                                                        confirm({
                                                            title: `Delete this ${r.type}?`,
                                                            description: `${r.category} · ${formatMoney(r.amount)} on ${formatDate(r.date, 'dd/MM/yyyy')}. The cash position will be recalculated.`,
                                                            confirmLabel: 'Delete entry',
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

FinanceTransactions.layout = withAppLayout;

function Tile({ label, value, tone, strong }) {
    return (
        <div className={cn('flex flex-col gap-2 rounded-lg p-5 shadow-card', strong ? 'bg-primary-soft/50' : 'bg-surface')}>
            <span className="text-2xs font-semibold uppercase tracking-wider text-fg-subtle">{label}</span>
            <span className={cn('tabular text-3xl font-semibold tracking-tight', tone === 'success' && 'text-success-fg', tone === 'danger' && 'text-danger-fg', strong && 'text-primary-hover', value < 0 && 'text-danger-fg')}>
                {value < 0 ? '−' : ''}
                {formatMoney(Math.abs(value))}
            </span>
        </div>
    );
}
