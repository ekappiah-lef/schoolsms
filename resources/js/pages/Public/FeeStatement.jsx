import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { cn, formatDate, formatNumber } from '@/lib/utils';
import { TermInvoice } from '@/components/fees/fee-statement';

const cedi = (n) => `GH₵ ${formatNumber(n ?? 0)}`;

/**
 * Fees statement for parents, opened from the admission email/SMS link.
 * Shows the two invoices (school fees with breakdown, optional services),
 * what has been paid and how to pay. Read-only; no login required.
 */
export default function FeeStatement({ school, student, session, current, overall, invoice, instructions, generated }) {
    const schoolRecords = current.school.records;
    const optional = current.optional.charges;
    const arrears = Math.max(overall.balance - current.totals.balance, 0);

    return (
        <div className="min-h-screen bg-canvas px-4 py-8 print:bg-white print:p-0">
            <Head title={`Fees · ${student.name}`} />
            <div className="mx-auto max-w-3xl">
                <div className="mb-4 flex justify-end print:hidden">
                    <button
                        type="button"
                        onClick={() => window.print()}
                        className="inline-flex h-9 items-center gap-2 rounded-lg bg-surface px-3 text-sm font-medium shadow-field hover:shadow-card-hover"
                    >
                        <Printer className="size-4" />
                        Print / save as PDF
                    </button>
                </div>

                <article className="rounded-lg bg-surface shadow-card print:shadow-none">
                    <header className="flex flex-col gap-4 border-b border-border p-6 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-4">
                            {school.logo && <img src={school.logo} alt="" className="size-14 rounded-md object-contain" />}
                            <div>
                                <div className="text-lg font-semibold">{school.name}</div>
                                <div className="text-sm text-fg-muted">{school.address}</div>
                                <div className="text-sm text-fg-muted">{[school.phone, school.email].filter(Boolean).join(' · ')}</div>
                            </div>
                        </div>
                        <div className="text-left sm:text-right">
                            <div className="overline-label">Fees statement</div>
                            <div className="mt-1 text-sm text-fg-muted">Year {session}</div>
                            <div className="text-xs text-fg-subtle">Generated {formatDate(generated, 'dd/MM/yyyy HH:mm')}</div>
                        </div>
                    </header>

                    <section className="grid gap-4 border-b border-border p-6 sm:grid-cols-3">
                        <Info label="Student" value={student.name} />
                        <Info label="Admission no." value={student.adm_no} />
                        <Info label="Class" value={`${student.class}${student.category === 'new' ? ' · New student' : ''}`} />
                    </section>

                    <section className="grid border-b border-border sm:grid-cols-3 sm:divide-x sm:divide-border">
                        <Total label="School fees due" value={current.school.totals.balance} />
                        <Total label="Optional fees due" value={current.optional.totals.balance} />
                        <Total label="Total due this year" value={current.totals.balance} strong />
                    </section>

                    {invoice && (
                        <section className="border-b border-border p-6">
                            <TermInvoice invoice={invoice} />
                        </section>
                    )}

                    <section className="p-6">
                        <h2 className="text-base font-semibold">Invoice 1 · School fees</h2>
                        {schoolRecords.length ? (
                            schoolRecords.map((r) => (
                                <div key={r.id} className="mt-3 overflow-hidden rounded-lg shadow-field">
                                    <div className="flex items-center justify-between bg-muted/60 px-4 py-2.5 text-sm font-medium">
                                        <span>{r.title}</span>
                                        <span className="tabular">{cedi(r.amount)}</span>
                                    </div>
                                    {r.items.length > 0 && (
                                        <ul className="divide-y divide-border px-4 text-sm">
                                            {r.items.map((it, i) => (
                                                <li key={i} className="flex justify-between py-2">
                                                    <span className="text-fg-muted">{it.name}</span>
                                                    <span className="tabular">{cedi(it.amount)}</span>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                    <PaidLine paid={r.paid} balance={r.balance} />
                                </div>
                            ))
                        ) : (
                            <p className="mt-2 text-sm text-fg-muted">No school fees have been billed yet.</p>
                        )}
                    </section>

                    <section className="border-t border-border p-6">
                        <h2 className="text-base font-semibold">Invoice 2 · Optional fees</h2>
                        {optional.length ? (
                            <div className="mt-3 overflow-hidden rounded-lg shadow-field">
                                <ul className="divide-y divide-border px-4 text-sm">
                                    {optional.map((c) => (
                                        <li key={c.id} className="grid grid-cols-[1fr_auto_auto] items-center gap-4 py-2.5">
                                            <span>
                                                <span className="block font-medium">{c.label}</span>
                                                <span className="text-xs text-fg-muted">{c.group_label}</span>
                                            </span>
                                            <span className="tabular text-fg-muted">{cedi(c.amount)}</span>
                                            <span className={cn('tabular w-28 text-right font-medium', c.balance > 0 ? 'text-danger-fg' : 'text-success-fg')}>
                                                {c.balance > 0 ? `${cedi(c.balance)} due` : 'Paid'}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                                <PaidLine paid={current.optional.totals.paid} balance={current.optional.totals.balance} label="Optional fees" />
                            </div>
                        ) : (
                            <p className="mt-2 text-sm text-fg-muted">No optional services (feeding, bus, extra-curricular, books) were selected.</p>
                        )}
                        <p className="mt-2 text-xs text-fg-muted">Optional services can be paid for separately, e.g. only the bus.</p>
                    </section>

                    {arrears > 0 && (
                        <section className="border-t border-border bg-danger-soft/60 px-6 py-3 text-sm text-danger-fg">
                            Outstanding from previous years: <span className="tabular font-semibold">{cedi(arrears)}</span>. Total owed: <span className="tabular font-semibold">{cedi(overall.balance)}</span>.
                        </section>
                    )}

                    <section className="border-t border-border p-6">
                        <h2 className="text-base font-semibold">How to pay</h2>
                        {instructions ? (
                            <p className="mt-2 whitespace-pre-line text-sm text-fg-muted">{instructions}</p>
                        ) : (
                            <p className="mt-2 text-sm text-fg-muted">Please pay at the school’s accounts office{school.phone ? ` or call ${school.phone}` : ''}.</p>
                        )}
                        <p className="mt-4 text-xs text-fg-subtle">
                            Fees are due on or before the deadlines set by the school and are non-refundable unless the school states otherwise in writing. Use the admission number {student.adm_no} as your payment reference.
                        </p>
                    </section>
                </article>
            </div>
        </div>
    );
}

function Info({ label, value }) {
    return (
        <div>
            <div className="overline-label">{label}</div>
            <div className="mt-1 font-medium">{value || '—'}</div>
        </div>
    );
}

function Total({ label, value, strong }) {
    return (
        <div className={cn('px-6 py-4', strong && 'bg-muted/50')}>
            <div className="overline-label">{label}</div>
            <div className={cn('tabular mt-1 text-xl font-semibold', value > 0 ? 'text-danger-fg' : 'text-success-fg')}>{value > 0 ? cedi(value) : 'Nothing due'}</div>
        </div>
    );
}

function PaidLine({ paid, balance, label }) {
    return (
        <div className="tabular flex justify-between border-t border-border bg-muted/40 px-4 py-2 text-sm">
            <span className="text-fg-muted">
                {label ? `${label}: ` : ''}Paid {cedi(paid)}
            </span>
            <span className={cn('font-semibold', balance > 0 ? 'text-danger-fg' : 'text-success-fg')}>{balance > 0 ? `Balance ${cedi(balance)}` : 'Fully paid'}</span>
        </div>
    );
}
