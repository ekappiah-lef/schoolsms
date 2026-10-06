import { Fragment, useState } from 'react';
import { toast } from 'sonner';
import { ChevronRight, Download, FileText, MoreHorizontal, Printer, Receipt, RotateCcw, Send } from 'lucide-react';
import { EmptyState, Panel } from '@/components/app/page';
import { usePaged } from '@/components/app/data-table';
import { Meter } from '@/components/app/charts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { submitForm } from '@/lib/http';
import { cn, formatDate, formatMoney } from '@/lib/utils';

const CATEGORY = { new: 'New student', old: 'Continuing student' };

export const sumRows = (rows) =>
    rows.reduce((a, r) => ({ amount: a.amount + r.amount, paid: a.paid + r.paid, balance: a.balance + r.balance }), { amount: 0, paid: 0, balance: 0 });

/** Totals for school fees, optional fees and everything together. */
export function FeeTotals({ school, optional, overall, scopeLabel }) {
    return (
        <div className="panel grid divide-y divide-border md:grid-cols-3 md:divide-x md:divide-y-0">
            <TotalBlock title="School fees" t={school} />
            <TotalBlock title="Optional fees" t={optional} />
            <TotalBlock title={scopeLabel ?? 'All fees'} t={overall} strong />
        </div>
    );
}

function TotalBlock({ title, t, strong }) {
    return (
        <div className={cn('px-4 py-3.5', strong && 'bg-muted/50')}>
            <div className="flex items-center justify-between">
                <span className="overline-label">{title}</span>
                <StatusBadge t={t} />
            </div>
            <div className={cn('tabular mt-1.5 text-xl font-semibold', t.balance > 0 ? 'text-danger-fg' : 'text-success-fg')}>{formatMoney(t.balance)}</div>
            <div className="mt-2">
                <Meter value={t.paid} max={t.amount} />
            </div>
            <div className="tabular mt-1.5 flex justify-between text-xs text-fg-muted">
                <span>Paid {formatMoney(t.paid)}</span>
                <span>of {formatMoney(t.amount)}</span>
            </div>
        </div>
    );
}

export function StatusBadge({ t }) {
    if (!t.amount) return <Badge>None</Badge>;
    if (t.balance <= 0) return <Badge tone="success">Paid</Badge>;
    if (t.paid > 0) return <Badge tone="warning">Part paid</Badge>;
    return <Badge tone="danger">Unpaid</Badge>;
}

/** Invoice 1: school fees, each with its itemised breakdown. */
export function SchoolFeesInvoice({ records, onPaid, confirm, actions }) {
    const [open, setOpen] = useState(() => new Set(records.length === 1 ? [records[0].id] : []));
    const toggle = (id) => setOpen((s) => {
        const n = new Set(s);
        n.has(id) ? n.delete(id) : n.add(id);
        return n;
    });
    const t = sumRows(records);
    const { shown, pager } = usePaged(records, 10, 'bills');
    const payable = records.some((r) => r.urls?.pay);

    return (
        <Panel title="School fees" description="Tuition and termly charges, with their breakdown" flush actions={actions}>
            {!records.length ? (
                <EmptyState compact icon={Receipt} title="No school fees billed" description="School fees are billed when they are set up for the student’s class." />
            ) : (
                <div className="scrollbar-thin overflow-x-auto">
                    <table className="w-full text-left">
                        <thead>
                            <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                                <th className="h-9 px-4">Fee</th>
                                <th className="h-9 px-3 text-right">Amount</th>
                                <th className="h-9 px-3 text-right">Paid</th>
                                <th className="h-9 px-3 text-right">Balance</th>
                                {payable && <th className="h-9 px-3">Record payment</th>}
                                <th className="h-9 w-12 px-4" />
                            </tr>
                        </thead>
                        <tbody className="tabular">
                            {shown.map((r) => {
                                const expanded = open.has(r.id);
                                return (
                                    <Fragment key={r.id}>
                                        <tr className="border-b border-border hover:bg-muted/60">
                                            <td className="px-4 py-3">
                                                <button
                                                    type="button"
                                                    onClick={() => toggle(r.id)}
                                                    disabled={!r.items.length && !r.discount}
                                                    aria-expanded={expanded}
                                                    className="flex items-start gap-1.5 text-left disabled:cursor-default"
                                                >
                                                    <ChevronRight className={cn('mt-0.5 size-4 shrink-0 text-fg-subtle transition-transform', expanded && 'rotate-90', !r.items.length && !r.discount && 'invisible')} />
                                                    <span>
                                                        <span className="block font-medium">{r.title}</span>
                                                        <span className="block text-xs text-fg-muted">
                                                            {r.year}
                                                            {CATEGORY[r.category] ? ` · ${CATEGORY[r.category]}` : ''}
                                                            {r.items.length ? ` · ${r.items.length} items` : ''}
                                                        </span>
                                                        {r.discount > 0 && (
                                                            <span className="mt-0.5 block text-xs text-success-fg">
                                                                {formatMoney(r.gross)} less {formatMoney(r.discount)} discount
                                                            </span>
                                                        )}
                                                    </span>
                                                </button>
                                            </td>
                                            <td className="px-3 text-right">{formatMoney(r.amount)}</td>
                                            <td className="px-3 text-right text-success-fg">{formatMoney(r.paid)}</td>
                                            <td className={cn('px-3 text-right font-semibold', r.balance > 0 ? 'text-danger-fg' : 'text-success-fg')}>{formatMoney(r.balance)}</td>
                                            {payable && <td className="px-3 py-2">{r.balance > 0 ? <PayInline title={r.title} balance={r.balance} url={r.urls.pay} onPaid={onPaid} /> : <Badge tone="success">Paid</Badge>}</td>}
                                            <td className="px-4 text-right">
                                                {r.urls && (
                                                    <RowMenu
                                                        label={r.title}
                                                        receiptUrl={r.receipts.length ? r.urls.receipt : null}
                                                        onReset={r.paid > 0 && r.urls.reset ? () => reversePayments(r.urls.reset, r.title, r.paid, onPaid) : null}
                                                    />
                                                )}
                                            </td>
                                        </tr>
                                        {expanded && (
                                            <tr className="border-b border-border bg-muted/40">
                                                <td colSpan={payable ? 6 : 5} className="px-4 pb-3 pl-10 pt-1">
                                                    <ul className="grid max-w-xl gap-x-8 gap-y-1 py-1 text-sm sm:grid-cols-2">
                                                        {r.items.map((it, i) => (
                                                            <li key={i} className="flex justify-between gap-3 border-b border-dashed border-border py-1">
                                                                <span className="text-fg-muted">{it.name}</span>
                                                                <span>{formatMoney(it.amount)}</span>
                                                            </li>
                                                        ))}
                                                        {r.discount > 0 && (
                                                            <li className="flex justify-between gap-3 border-b border-dashed border-border py-1 text-success-fg">
                                                                <span>Tuition discount</span>
                                                                <span>−{formatMoney(r.discount)}</span>
                                                            </li>
                                                        )}
                                                    </ul>
                                                </td>
                                            </tr>
                                        )}
                                    </Fragment>
                                );
                            })}
                        </tbody>
                        <TotalsFoot t={t} cols={payable ? 2 : 1} />
                    </table>
                    {pager}
                </div>
            )}
        </Panel>
    );
}

/** Invoice 2: optional services, each payable on its own (e.g. only the bus). */
export function OptionalFeesInvoice({ charges, onPaid, confirm }) {
    const t = sumRows(charges);
    const payable = charges.some((c) => c.urls?.pay);
    const groups = [...new Set(charges.map((c) => c.group_label))];

    return (
        <Panel title="Optional fees" description="Feeding, bus, extra-curricular and books. Each service can be paid separately." flush>
            {!charges.length ? (
                <EmptyState compact icon={Receipt} title="No optional services" description="Services chosen at admission or on the edit page appear here." />
            ) : (
                <div className="scrollbar-thin overflow-x-auto">
                    <table className="w-full text-left">
                        <thead>
                            <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                                <th className="h-9 px-4">Service</th>
                                <th className="h-9 px-3 text-right">Amount</th>
                                <th className="h-9 px-3 text-right">Paid</th>
                                <th className="h-9 px-3 text-right">Balance</th>
                                {payable && <th className="h-9 px-3">Record payment</th>}
                                <th className="h-9 w-12 px-4" />
                            </tr>
                        </thead>
                        <tbody className="tabular">
                            {groups.map((g) => (
                                <Fragment key={g}>
                                    <tr className="border-b border-border bg-muted/40">
                                        <td colSpan={payable ? 6 : 5} className="px-4 py-1.5 text-2xs font-semibold uppercase tracking-wide text-fg-muted">
                                            {g}
                                        </td>
                                    </tr>
                                    {charges
                                        .filter((c) => c.group_label === g)
                                        .map((c) => (
                                            <tr key={c.id} className="border-b border-border hover:bg-muted/60">
                                                <td className="px-4 py-3">
                                                    <div className="font-medium">{c.label}</div>
                                                    <div className="text-xs text-fg-muted">{c.year}</div>
                                                </td>
                                                <td className="px-3 text-right">{formatMoney(c.amount)}</td>
                                                <td className="px-3 text-right text-success-fg">{formatMoney(c.paid)}</td>
                                                <td className={cn('px-3 text-right font-semibold', c.balance > 0 ? 'text-danger-fg' : 'text-success-fg')}>{formatMoney(c.balance)}</td>
                                                {payable && <td className="px-3 py-2">{c.balance > 0 ? <PayInline title={c.label} balance={c.balance} url={c.urls.pay} onPaid={onPaid} /> : <Badge tone="success">Paid</Badge>}</td>}
                                                <td className="px-4 text-right">
                                                    {c.urls?.reset && c.paid > 0 && <RowMenu label={c.label} onReset={() => reversePayments(c.urls.reset, c.label, c.paid, onPaid)} />}
                                                </td>
                                            </tr>
                                        ))}
                                </Fragment>
                            ))}
                        </tbody>
                        <TotalsFoot t={t} cols={payable ? 2 : 1} />
                    </table>
                </div>
            )}
        </Panel>
    );
}

function TotalsFoot({ t, cols }) {
    return (
        <tfoot className="tabular">
            <tr className="border-t border-border bg-canvas text-sm font-semibold">
                <td className="px-4 py-2.5">Total</td>
                <td className="px-3 text-right">{formatMoney(t.amount)}</td>
                <td className="px-3 text-right text-success-fg">{formatMoney(t.paid)}</td>
                <td className={cn('px-3 text-right', t.balance > 0 ? 'text-danger-fg' : 'text-success-fg')}>{formatMoney(t.balance)}</td>
                <td colSpan={cols} />
            </tr>
        </tfoot>
    );
}

/**
 * Reverse every payment on a bill (administrators only). A reason is required; the receipts are kept in
 * the finance audit trail with who reversed them and why, and the bill returns to unpaid.
 */
async function reversePayments(url, label, paid, onDone) {
    const reason = window.prompt(`Reverse ${formatMoney(paid)} paid on "${label}"?\n\nThe receipts are kept in the audit trail. Give the reason:`);
    if (reason === null) return;
    if (reason.trim().length < 5) {
        toast.error('Give a reason of at least 5 characters.');
        return;
    }
    const r = await submitForm(url, { reason: reason.trim() }, { method: 'delete' });
    r.ok ? toast.success(r.message) : toast.error(r.message);
    if (r.ok) onDone?.();
}

function RowMenu({ label, receiptUrl, onReset }) {
    if (!receiptUrl && !onReset) return null;
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon-sm" aria-label={`Actions for ${label}`}>
                    <MoreHorizontal />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent>
                {receiptUrl && (
                    <DropdownMenuItem asChild>
                        <a href={receiptUrl} target="_blank" rel="noreferrer">
                            <Printer />
                            Print receipt
                        </a>
                    </DropdownMenuItem>
                )}
                {onReset && (
                    <DropdownMenuItem destructive onSelect={onReset}>
                        <RotateCcw />
                        Reverse payments
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/** Every receipt across both invoices, newest first: view, download or send each one. */
export function PaymentHistory({ school, optional }) {
    const history = [
        ...school.flatMap((r) => r.receipts.map((rc) => ({ ...rc, title: r.title, kind: 'School fees' }))),
        ...optional.flatMap((c) => c.receipts.map((rc) => ({ ...rc, title: c.label, kind: c.group_label }))),
    ].sort((a, b) => (b.date ?? '').localeCompare(a.date ?? ''));
    const { shown, pager } = usePaged(history, 10, 'payments');

    return (
        <Panel title="Payment history" description="Receipt: Download the PDF or send it to the parent" flush>
            {history.length ? (
                <ul className="divide-y divide-border">
                    {shown.map((h) => (
                        <li key={h.id} className="flex items-center gap-3 px-4 py-2 text-sm">
                            <Receipt className="size-4 shrink-0 text-fg-subtle" />
                            <span className="tabular w-24 shrink-0 text-fg-muted">{formatDate(h.date, 'dd/MM/yyyy')}</span>
                            <span className="min-w-0 flex-1 truncate">
                                {h.title} <span className="text-fg-subtle">· {h.kind}</span>
                            </span>
                            <span className="tabular hidden text-xs text-fg-subtle md:block">{h.number}</span>
                            <span className="tabular font-medium">{formatMoney(h.amount)}</span>
                            <span className="tabular hidden w-28 text-right text-xs text-fg-muted sm:block">bal. {formatMoney(h.balance)}</span>
                            {h.urls ? <ReceiptMenu urls={h.urls} number={h.number} /> : <span className="w-8" />}
                        </li>
                    ))}
                </ul>
            ) : (
                <EmptyState compact icon={Receipt} title="No payments received yet" />
            )}
            {pager}
        </Panel>
    );
}

/** Email (PDF attached) and SMS a receipt to the parent. */
export async function sendReceipt(url) {
    const id = toast.loading('Sending receipt to the parent…');
    const r = await submitForm(url, {});
    if (r.ok) toast.success(r.message, { id });
    else toast.error(r.message, { id });
}

function ReceiptMenu({ urls, number }) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon-sm" aria-label={`Receipt ${number}`}>
                    <MoreHorizontal />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent>
                <DropdownMenuItem asChild>
                    <a href={urls.view} target="_blank" rel="noreferrer">
                        <FileText />
                        View / print receipt
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <a href={urls.pdf}>
                        <Download />
                        Download PDF
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem onSelect={() => sendReceipt(urls.send)}>
                    <Send />
                    Send to parent (email &amp; SMS)
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/** Amount field + Pay button. The balance after payment is shown as you type. */
export const PAY_METHODS = ['Cash', 'Mobile payment', 'Bank transfer', 'Cheque'];

export function PayInline({ title, balance, url, onPaid }) {
    const [value, setValue] = useState('');
    const [method, setMethod] = useState('Cash');
    const [processing, setProcessing] = useState(false);
    const amount = Number(value);
    const invalid = value !== '' && (!Number.isFinite(amount) || amount < 1 || amount > balance);
    const after = value !== '' && !invalid ? balance - amount : null;

    const pay = async (e) => {
        e.preventDefault();
        if (!value || invalid) return;
        setProcessing(true);
        const result = await submitForm(url, { amt_paid: amount, method });
        setProcessing(false);
        if (result.ok) {
            const receipt = result.data?.receipt;
            toast.success(`${formatMoney(amount)} (${method}) recorded for ${title}`, {
                duration: 10000,
                action: receipt ? { label: 'Send receipt', onClick: () => sendReceipt(receipt.send) } : undefined,
                cancel: receipt ? { label: 'View', onClick: () => window.open(receipt.view, '_blank') } : undefined,
            });
            setValue('');
            onPaid?.();
        } else {
            toast.error(result.errors?.amt_paid ?? result.message);
        }
    };

    return (
        <form onSubmit={pay} className="flex min-w-[340px] items-start gap-2">
            <select
                value={method}
                onChange={(e) => setMethod(e.target.value)}
                aria-label="Payment method"
                className="h-8 w-[118px] shrink-0 rounded-md border-0 bg-surface px-2 text-xs shadow-field focus:shadow-[0_0_0_2px_rgb(var(--primary)/0.35)] focus:outline-none"
            >
                {PAY_METHODS.map((m) => (
                    <option key={m} value={m}>
                        {m}
                    </option>
                ))}
            </select>
            <div className="flex-1">
                <div className="relative">
                    <input
                        type="number"
                        inputMode="numeric"
                        min={1}
                        max={balance}
                        value={value}
                        onChange={(e) => setValue(e.target.value)}
                        placeholder="Amount"
                        aria-label={`Amount to pay for ${title}`}
                        aria-invalid={invalid || undefined}
                        className="tabular h-8 w-full rounded-md border-0 bg-surface px-2.5 pr-12 text-sm shadow-field placeholder:text-fg-subtle focus:shadow-[0_0_0_2px_rgb(var(--primary)/0.35)] focus:outline-none aria-[invalid=true]:shadow-[0_0_0_1.5px_rgb(var(--danger))]"
                    />
                    <button
                        type="button"
                        onClick={() => setValue(String(balance))}
                        className="absolute right-1 top-1/2 -translate-y-1/2 rounded px-1.5 py-0.5 text-2xs font-semibold uppercase text-primary hover:bg-primary-soft"
                    >
                        Full
                    </button>
                </div>
                <div className={cn('mt-1 h-4 text-xs', invalid ? 'text-danger-fg' : 'text-fg-muted')}>
                    {invalid ? `Enter 1 – ${formatMoney(balance)}` : after !== null ? (after === 0 ? 'Clears this item' : `Balance after: ${formatMoney(after)}`) : ''}
                </div>
            </div>
            <Button type="submit" size="sm" variant="primary" loading={processing} disabled={!value || invalid}>
                Pay
            </Button>
        </form>
    );
}

/** The current term's invoice: this term's fees, any balance brought forward, and the total due now. */
export function TermInvoice({ invoice, sendUrl }) {
    const [sending, setSending] = useState(false);
    if (!invoice) return null;
    const { current, forward } = invoice;
    const send = async () => {
        setSending(true);
        const r = await submitForm(sendUrl, {});
        setSending(false);
        r.ok ? toast.success(r.message) : toast.error(r.message);
    };
    const row = 'flex items-baseline justify-between gap-4 px-4 py-2 text-sm';

    return (
        <Panel
            title={`Invoice · ${invoice.label.replace('-', ' – ')}`}
            description={forward.total > 0 ? 'This term’s fees plus the balance brought forward from earlier terms.' : 'This term’s fees.'}
            flush
            actions={
                sendUrl ? (
                    <Button size="sm" onClick={send} disabled={sending}>
                        <Send />
                        {sending ? 'Sending…' : 'Send to parent'}
                    </Button>
                ) : null
            }
        >
            <div className="tabular divide-y divide-border">
                {current.lines.map((l, i) => (
                    <div key={`c${i}`} className={row}>
                        <span className="min-w-0 truncate">{l.label}</span>
                        <span>{formatMoney(l.amount)}</span>
                    </div>
                ))}
                {current.paid > 0 && (
                    <div className={cn(row, 'text-success-fg')}>
                        <span>Already paid this term</span>
                        <span>−{formatMoney(current.paid)}</span>
                    </div>
                )}
                <div className={cn(row, 'font-semibold')}>
                    <span>This term’s balance</span>
                    <span>{formatMoney(current.balance)}</span>
                </div>
                {forward.total > 0 && (
                    <>
                        <div className="bg-danger-soft/40 px-4 pb-1 pt-2.5 text-2xs font-semibold uppercase tracking-wider text-danger-fg">Balance brought forward</div>
                        {forward.lines.map((l, i) => (
                            <div key={`f${i}`} className={cn(row, 'bg-danger-soft/20 text-danger-fg')}>
                                <span className="min-w-0 truncate">{l.label}</span>
                                <span>{formatMoney(l.balance)}</span>
                            </div>
                        ))}
                    </>
                )}
                <div className="flex items-baseline justify-between gap-4 bg-canvas px-4 py-3">
                    <span className="font-semibold">Total due</span>
                    <span className={cn('text-xl font-semibold', invoice.total > 0 ? 'text-danger-fg' : 'text-success-fg')}>{formatMoney(invoice.total)}</span>
                </div>
            </div>
        </Panel>
    );
}

/**
 * Pay (or request payment) with MTN MoMo: the payer gets a prompt on their phone and approves it
 * with their PIN; this waits for MTN's answer, then the payment is applied to the fees.
 */
export function MomoPay({ url, maxAmount, defaultPhone = '', test, onPaid, staff = false }) {
    const [phone, setPhone] = useState(defaultPhone ?? '');
    const [amount, setAmount] = useState(maxAmount > 0 ? String(maxAmount) : '');
    const [state, setState] = useState({ step: 'form' }); // form | waiting | done | failed
    const [busy, setBusy] = useState(false);
    if (!url || maxAmount <= 0) return null;

    const poll = async (statusUrl, started) => {
        try {
            const r = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
            const j = await r.json();
            if (j.status === 'successful') {
                setState({ step: 'done', amount: j.amount, transaction: j.transaction });
                toast.success(`${formatMoney(j.amount)} received by MTN MoMo.`);
                onPaid?.();
                return;
            }
            if (j.status === 'failed') {
                setState({ step: 'failed', reason: j.reason });
                return;
            }
        } catch {
            /* keep waiting */
        }
        if (Date.now() - started > 3 * 60 * 1000) {
            setState({ step: 'failed', reason: 'No answer yet. If money was deducted it will show here shortly; otherwise try again.' });
            return;
        }
        setTimeout(() => poll(statusUrl, started), 3000);
    };

    const start = async (e) => {
        e.preventDefault();
        setBusy(true);
        const r = await submitForm(url, { phone, amount: Number(amount) });
        setBusy(false);
        if (!r.ok) {
            toast.error(r.errors ? Object.values(r.errors)[0] : r.message);
            return;
        }
        setState({ step: 'waiting' });
        poll(r.data.status_url, Date.now());
    };

    return (
        <div className="rounded-lg border border-[#ffcc00] bg-[#fffbea] p-4">
            <div className="mb-3 flex items-center gap-2">
                <span className="rounded bg-[#ffcc00] px-1.5 py-0.5 text-2xs font-bold text-black">MTN MoMo</span>
                <span className="text-sm font-semibold">{staff ? 'Request payment by MoMo' : 'Pay with MTN Mobile Money'}</span>
                {test && <span className="ml-auto text-2xs font-semibold uppercase text-warning-fg">Test mode</span>}
            </div>
            {state.step === 'form' && (
                <form onSubmit={start} className="flex flex-wrap items-end gap-2">
                    <label className="flex flex-col gap-1 text-xs text-fg-muted">
                        MoMo number
                        <input className="h-9 w-44 rounded-md border border-border bg-surface px-2.5 text-sm text-fg" inputMode="tel" value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="024 123 4567" required />
                    </label>
                    <label className="flex flex-col gap-1 text-xs text-fg-muted">
                        Amount (GHS)
                        <input className="tabular h-9 w-32 rounded-md border border-border bg-surface px-2.5 text-sm text-fg" type="number" min={1} max={maxAmount} value={amount} onChange={(e) => setAmount(e.target.value)} required />
                    </label>
                    <Button type="submit" variant="primary" disabled={busy}>
                        {busy ? 'Sending…' : staff ? 'Send prompt to phone' : 'Pay now'}
                    </Button>
                    <p className="w-full text-xs text-fg-muted">
                        {staff ? 'The parent approves the payment on their phone with their MoMo PIN.' : 'You will get a prompt on your phone. Approve it with your MoMo PIN.'}
                        {test && ' Test mode: no real money moves; numbers ending in 0 are declined.'}
                    </p>
                </form>
            )}
            {state.step === 'waiting' && <p className="text-sm">Waiting for approval on {phone}… Check the phone and enter the MoMo PIN.</p>}
            {state.step === 'done' && (
                <p className="text-sm text-success-fg">
                    Payment of {formatMoney(state.amount)} received{state.transaction ? ` (MTN ref ${state.transaction})` : ''}. It has been applied to the fees, oldest balance first.
                </p>
            )}
            {state.step === 'failed' && (
                <div className="flex flex-wrap items-center gap-3 text-sm text-danger-fg">
                    {state.reason}
                    <Button size="xs" onClick={() => setState({ step: 'form' })}>
                        Try again
                    </Button>
                </div>
            )}
        </div>
    );
}
