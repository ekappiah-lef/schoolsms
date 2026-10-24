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
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6">
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

                <div className="overflow-hidden rounded-xl bg-surface shadow-card">
                    <div className="scrollbar-thin overflow-x-auto">
                        <table className="w-full min-w-[760px] text-left text-sm">
                            <thead>
                                <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase tracking-wider text-fg-muted">
                                    <th className="h-10 px-5">Term</th>
                                    <th className="h-10 px-2">Starts</th>
                                    <th className="h-10 px-2">Ends</th>
                                    <th className="h-10 px-2">Mid-term from</th>
                                    <th className="h-10 px-2">Mid-term to</th>
                                    <th className="h-10 px-5 text-right">Days</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((r, i) => (
                                    <tr key={r.term} className={cn('border-b border-border last:border-0', now === r.term && 'bg-primary-soft/30')}>
                                        <td className="whitespace-nowrap px-5 py-3 font-semibold">
                                            {ORDINAL[i]} term
                                            {now === r.term && <Badge tone="primary" className="ml-2">Now</Badge>}
                                        </td>
                                        {['starts_on', 'ends_on', 'midterm_starts_on', 'midterm_ends_on'].map((k) => (
                                            <td key={k} className="w-[18%] px-2 py-3">
                                                <DatePicker value={r[k]} onChange={(v) => set(i, k, v)} fromYear={startYear} toYear={startYear + 1} defaultMonth={new Date(startYear, 8)} />
                                            </td>
                                        ))}
                                        <td className="tabular px-5 py-3 text-right font-semibold">{r.days ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}

SchoolCalendar.layout = withAppLayout;

