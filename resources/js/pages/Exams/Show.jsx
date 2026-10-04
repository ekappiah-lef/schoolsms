import { Head, Link } from '@inertiajs/react';
import { NotebookPen, Pencil } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { ModuleHeader } from '@/components/app/module';
import { EmptyState, Panel } from '@/components/app/page';
import { HorizontalBars } from '@/components/app/charts';
import { usePaged } from '@/components/app/data-table';
import { Button } from '@/components/ui/button';

/**
 * Examination overview: results by class (with tabulation and report sheets),
 * subject averages and the top students. Marks are entered under Marks entry.
 */
export default function ExamShow({ exam, classes, subjects, top, urls }) {
    const { shown, pager } = usePaged(classes, 10, 'classes');
    const tiles = [
        { label: 'Student results', value: exam.results.toLocaleString('en-GB') },
        { label: 'Avg. score', value: exam.results ? `${exam.average}%` : '—' },
        { label: 'Classes', value: classes.length },
        { label: 'Term', value: `Term ${exam.term}` },
    ];

    return (
        <>
            <Head title={exam.name} />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6">
                <ModuleHeader
                    crumbs={['Academics', 'Examinations', exam.name]}
                    title={exam.name}
                    description={`Term ${exam.term} · Academic year ${exam.year.replace('-', ' – ')}`}
                    aside={
                        <div className="flex gap-2">
                            <Button asChild>
                                <Link href={urls.edit}>
                                    <Pencil />
                                    Edit examination
                                </Link>
                            </Button>
                            {exam.current && (
                                <Button variant="primary" asChild>
                                    <Link href={urls.marks}>
                                        <NotebookPen />
                                        Enter marks
                                    </Link>
                                </Button>
                            )}
                        </div>
                    }
                />

                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    {tiles.map((t) => (
                        <div key={t.label} className="flex flex-col gap-2 rounded-lg bg-surface p-5 shadow-card">
                            <span className="text-sm font-medium text-fg-muted">{t.label}</span>
                            <span className="tabular text-3xl font-semibold tracking-tight">{t.value}</span>
                        </div>
                    ))}
                </div>

                <Panel title="Results by class" description={exam.current ? 'Open the tabulation sheet or each student’s report sheet.' : 'Tabulation and report sheets are available for the current academic year.'} flush>
                    {classes.length ? (
                        <div className="scrollbar-thin overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                                        <th className="h-10 px-5">Class</th>
                                        <th className="h-10 px-3 text-right">Students</th>
                                        <th className="h-10 px-3 text-right">Avg. score</th>
                                        <th className="h-10 px-3 text-right">Best score</th>
                                        <th className="h-10 px-5 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="tabular">
                                    {shown.map((c) => (
                                        <tr key={c.name} className="border-b border-border last:border-0 hover:bg-muted/50">
                                            <td className="px-5 py-2.5 font-medium">{c.name}</td>
                                            <td className="px-3 py-2.5 text-right">{c.students}</td>
                                            <td className="px-3 py-2.5 text-right">{c.average}%</td>
                                            <td className="px-3 py-2.5 text-right">{c.best}%</td>
                                            <td className="px-5 py-2.5 text-right">
                                                {c.urls ? (
                                                    <div className="flex justify-end gap-2">
                                                        <Button size="xs" asChild>
                                                            <Link href={c.urls.tabulation}>Tabulation</Link>
                                                        </Button>
                                                        <Button size="xs" asChild>
                                                            <Link href={c.urls.results}>Report sheets</Link>
                                                        </Button>
                                                    </div>
                                                ) : (
                                                    <span className="text-fg-subtle">—</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            {pager}
                        </div>
                    ) : (
                        <EmptyState compact icon={NotebookPen} title="No marks recorded for this examination yet" />
                    )}
                </Panel>

                <div className="grid gap-6 xl:grid-cols-5">
                    <Panel title="Performance by subject" description="Average score in each subject" className="xl:col-span-3">
                        {subjects.length ? (
                            <HorizontalBars data={subjects} labelKey="subject" valueKey="average" name="Average" suffix="%" labelWidth={170} />
                        ) : (
                            <EmptyState compact title="No subject scores yet" />
                        )}
                    </Panel>
                    <Panel title="Top students" description="Highest average across all classes" className="xl:col-span-2" flush>
                        {top.length ? (
                            <ol className="divide-y divide-border">
                                {top.map((t, i) => (
                                    <li key={t.url} className="flex items-center gap-3 px-5 py-2.5 text-sm">
                                        <span className="tabular w-5 text-fg-subtle">{i + 1}</span>
                                        <div className="min-w-0 flex-1">
                                            <a href={t.url} className="block truncate font-medium hover:text-primary">
                                                {t.name}
                                            </a>
                                            <div className="text-xs text-fg-muted">{t.class}</div>
                                        </div>
                                        <span className="tabular font-semibold">{t.average}%</span>
                                    </li>
                                ))}
                            </ol>
                        ) : (
                            <EmptyState compact title="No results yet" />
                        )}
                    </Panel>
                </div>
            </div>
        </>
    );
}

ExamShow.layout = withAppLayout;
