import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { formatDate } from '@/lib/utils';
import { MomoPay, TermInvoice } from '@/components/fees/fee-statement';


/**
 * Fees invoice for parents, opened from the email/SMS link: one short table (school fees, each
 * service, balance from earlier terms, total due), pay online and how to pay. No login required.
 */
export default function FeeStatement({ school, student, session, invoice, momo, instructions, generated }) {

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
                            <div className="overline-label">Fees invoice</div>
                            <div className="mt-1 text-sm text-fg-muted">Year {session}</div>
                            <div className="text-xs text-fg-subtle">Generated {formatDate(generated, 'dd/MM/yyyy HH:mm')}</div>
                        </div>
                    </header>

                    <section className="grid gap-4 border-b border-border p-6 sm:grid-cols-3">
                        <Info label="Student" value={student.name} />
                        <Info label="Admission no." value={student.adm_no} />
                        <Info label="Class" value={`${student.class}${student.category === 'new' ? ' · New student' : ''}`} />
                    </section>

                    {invoice ? (
                        <section className="p-6">
                            <TermInvoice invoice={invoice} />
                            {momo && (
                                <div className="mt-4">
                                    <MomoPay url={momo.url} maxAmount={invoice.total} test={momo.test} onPaid={() => setTimeout(() => window.location.reload(), 2500)} />
                                </div>
                            )}
                        </section>
                    ) : (
                        <p className="p-6 text-sm text-fg-muted">No fees have been billed yet.</p>
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


