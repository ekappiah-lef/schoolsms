import { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { withAppLayout } from '@/layouts/AppLayout';
import { ModuleHeader } from '@/components/app/module';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
import { Select } from '@/components/ui/select';
import { submitForm } from '@/lib/http';
import { cn } from '@/lib/utils';

const ORDINAL = ['First', 'Second', 'Third'];

/** School calendar: when each term starts and ends, and its mid-term break, per school year (SchoolCalendarController). */
export default function SchoolCalendar({ year, years, now, terms, urls }) {
    const [rows, setRows] = useState(terms);
    const [busy, setBusy] = useState(false);
    useEffect(() => setRows(terms), [terms]);

    const startYear = Number(year.slice(0, 4));
    const set = (i, key, value) => setRows((rs) => rs.map((r, j) => (j === i ? { ...r, [key]: value } : r)));
    const save = async () => {
        setBusy(true);
        const r = await submitForm(urls.save, { year, terms: JSON.stringify(rows) });
        setBusy(false);
        if (r.ok) {
            toast.success(r.message);
            router.reload({ preserveScroll: true });
        } else {
            toast.error(r.message);
        }
    };

    return (
        <>
            <Head title="School calendar" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
                <ModuleHeader crumbs={['Administration', 'School calendar']} title="School calendar" />

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Select
                        className="w-48"
                        value={year}
                        onChange={(v) => router.get(urls.self, { year: v }, { preserveScroll: true })}
                        options={years.map((y) => ({ value: y, label: y.replace('-', ' – ') }))}
                    />
                    <Button variant="primary" loading={busy} onClick={save}>
                        Save calendar
                    </Button>
                </div>

                <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    {rows.map((r, i) => (
                        <section key={r.term} className={cn('flex flex-col rounded-xl bg-surface shadow-card', now === r.term && 'ring-2 ring-primary/60')}>
                            <header className="flex items-center justify-between gap-2 border-b border-border px-5 py-4">
                                <div>
                                    <div className="text-2xs font-semibold uppercase tracking-wider text-fg-subtle">Term {r.term}</div>
                                    <h2 className="text-lg font-semibold tracking-tight">{ORDINAL[i]} term</h2>
                                </div>
                                <div className="flex items-center gap-2">
                                    {r.days !== null && <span className="tabular rounded-full bg-muted px-2.5 py-1 text-xs font-semibold">{r.days} days</span>}
                                    {now === r.term && <Badge tone="primary">Now</Badge>}
                                </div>
                            </header>
                            <div className="flex flex-col gap-5 p-5">
                                <div className="grid grid-cols-2 gap-3">
                                    <DateField label="Starts" value={r.starts_on} onChange={(v) => set(i, 'starts_on', v)} startYear={startYear} />
                                    <DateField label="Ends" value={r.ends_on} onChange={(v) => set(i, 'ends_on', v)} startYear={startYear} />
                                </div>
                                <div className="rounded-lg bg-muted/60 p-3">
                                    <div className="mb-2 text-xs font-semibold uppercase tracking-wider text-fg-muted">Mid-term break</div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <DateField label="From" value={r.midterm_starts_on} onChange={(v) => set(i, 'midterm_starts_on', v)} startYear={startYear} />
                                        <DateField label="To" value={r.midterm_ends_on} onChange={(v) => set(i, 'midterm_ends_on', v)} startYear={startYear} />
                                    </div>
                                </div>
                            </div>
                        </section>
                    ))}
                </div>
            </div>
        </>
    );
}

SchoolCalendar.layout = withAppLayout;

function DateField({ label, value, onChange, startYear }) {
    return (
        <label className="flex min-w-0 flex-col gap-1.5">
            <span className="text-sm font-medium">{label}</span>
            <DatePicker value={value} onChange={onChange} fromYear={startYear} toYear={startYear + 1} defaultMonth={new Date(startYear, 8)} />
        </label>
    );
}
