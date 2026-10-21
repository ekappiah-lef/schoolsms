import { Head, router } from '@inertiajs/react';
import { withAppLayout } from '@/layouts/AppLayout';
import { ModuleHeader } from '@/components/app/module';
import { EmptyState, Panel, StatCard, StatStrip } from '@/components/app/page';
import { HorizontalBars, Legend, SERIES, StackedBars } from '@/components/app/charts';
import { Segmented } from '@/components/ui/tabs';
import { Select } from '@/components/ui/select';
import { formatMoney, formatNumber } from '@/lib/utils';

const C_PAID = '#4f46e5';
const C_OWED = '#f87185';

/** Results, attendance, fees and enrolment for any year, term and exam. */
export default function Analytics({ filters, years, exams, results, attendance, fees, enrolment, urls }) {
    const go = (change) => router.get(urls.self, { ...filters, ...change }, { preserveScroll: true, preserveState: true });

    return (
        <>
            <Head title="Analytics" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6">
                <ModuleHeader crumbs={['Overview', 'Analytics']} title="Analytics" />

                <div className="flex flex-wrap items-end gap-3">
                    <div className="w-full sm:w-44">
                        <span className="mb-1.5 block text-sm font-medium">Year</span>
                        <Select value={filters.year} onChange={(v) => v && go({ year: v, exam: null, term: null })} options={years.map((y) => ({ value: y, label: y.replace('-', ' – ') }))} />
                    </div>
                    <div>
                        <span className="mb-1.5 block text-sm font-medium">Term</span>
                        <Segmented value={String(filters.term)} onChange={(v) => go({ term: v, exam: null })} options={[1, 2, 3].map((t) => ({ value: String(t), label: `Term ${t}` }))} />
                    </div>
                    <div className="w-full sm:w-72">
                        <span className="mb-1.5 block text-sm font-medium">Exam</span>
                        <Select value={filters.exam ? String(filters.exam) : ''} onChange={(v) => v && go({ exam: v })} options={exams} placeholder="No exams this year" disabled={!exams.length} />
                    </div>
                </div>

                <StatStrip>
                    <StatCard label="Students with results" value={results ? formatNumber(results.students) : '—'} />
                    <StatCard label="School average" value={results && results.students ? `${results.average}%` : '—'} />
                    <StatCard label="Attendance" value={attendance.rate !== null ? `${attendance.rate}%` : '—'} />
                    <StatCard label="Fees paid" value={fees.billed ? `${Math.round((100 * fees.paid) / fees.billed)}%` : '—'} />
                </StatStrip>

                <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    <Panel title={results ? `Average per class · ${results.exam}` : 'Average per class'}>
                        {results?.classes.length ? (
                            <HorizontalBars data={results.classes} labelKey="class" valueKey="average" name="Average" suffix="%" labelWidth={110} />
                        ) : (
                            <EmptyState compact title="No results" />
                        )}
                    </Panel>
                    <Panel title="Grades">
                        {results?.grades.length ? (
                            <StackedBars data={results.grades} xKey="grade" series={[{ key: 'count', label: 'Students', color: SERIES.one }]} />
                        ) : (
                            <EmptyState compact title="No results" />
                        )}
                    </Panel>
                    <Panel title="Average per subject" className="xl:col-span-2">
                        {results?.subjects.length ? (
                            <HorizontalBars data={results.subjects} labelKey="name" valueKey="average" name="Average" suffix="%" labelWidth={220} />
                        ) : (
                            <EmptyState compact title="No results" />
                        )}
                    </Panel>
                    <Panel title={`Attendance · Term ${filters.term}`}>
                        {attendance.classes.length ? (
                            <HorizontalBars data={attendance.classes} labelKey="class" valueKey="rate" name="Present" suffix="%" labelWidth={110} color={SERIES.two} />
                        ) : (
                            <EmptyState compact title="No registers taken" />
                        )}
                    </Panel>
                    <Panel title={`Fees · Term ${filters.term}`} actions={<Legend items={[{ label: 'Paid', color: C_PAID }, { label: 'Owed', color: C_OWED }]} />}>
                        {fees.byType.length ? (
                            <>
                                <div className="mb-3 grid grid-cols-3 gap-2 text-sm">
                                    <div>
                                        <div className="text-xs text-fg-muted">Billed</div>
                                        <div className="tabular font-semibold">{formatMoney(fees.billed)}</div>
                                    </div>
                                    <div>
                                        <div className="text-xs text-fg-muted">Paid</div>
                                        <div className="tabular font-semibold text-success-fg">{formatMoney(fees.paid)}</div>
                                    </div>
                                    <div>
                                        <div className="text-xs text-fg-muted">Owed</div>
                                        <div className="tabular font-semibold text-danger-fg">{formatMoney(fees.owed)}</div>
                                    </div>
                                </div>
                                <StackedBars data={fees.byType} xKey="type" series={[{ key: 'paid', label: 'Paid', color: C_PAID }, { key: 'owed', label: 'Owed', color: C_OWED }]} />
                            </>
                        ) : (
                            <EmptyState compact title="No fees billed" />
                        )}
                    </Panel>
                    <Panel title="Students per class" className="xl:col-span-2" actions={<Legend items={[{ label: 'Male', color: SERIES.one }, { label: 'Female', color: SERIES.two }]} />}>
                        {enrolment.length ? (
                            <StackedBars data={enrolment} xKey="class" series={[{ key: 'male', label: 'Male', color: SERIES.one }, { key: 'female', label: 'Female', color: SERIES.two }]} />
                        ) : (
                            <EmptyState compact title="No students" />
                        )}
                    </Panel>
                </div>
            </div>
        </>
    );
}

Analytics.layout = withAppLayout;
