import { Head, Link } from '@inertiajs/react';
import { Pencil, Printer } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { ModuleHeader } from '@/components/app/module';
import { EmptyState, Panel } from '@/components/app/page';
import { Button } from '@/components/ui/button';
import { cn, formatDate } from '@/lib/utils';

/** A class or exam timetable: time slots down the side, days (or exam dates) across. */
export default function TimetableShow({ record, days, slots, cells, urls }) {
    const isExam = !!record.exam;
    const subject = (day, time) => cells.find((c) => c.day === day && c.time === time)?.subject;

    return (
        <>
            <Head title={record.name} />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6">
                <ModuleHeader
                    crumbs={['Academics', 'Timetables', record.name]}
                    title={record.name}
                    description={`${record.class}${record.exam ? ` · ${record.exam}` : ''} · ${record.year}`}
                    aside={
                        <div className="flex gap-2">
                            {urls.manage && (
                                <Button asChild>
                                    <Link href={urls.manage}>
                                        <Pencil />
                                        Edit timetable
                                    </Link>
                                </Button>
                            )}
                            <Button variant="primary" asChild>
                                <a href={urls.print} target="_blank" rel="noreferrer">
                                    <Printer />
                                    Print
                                </a>
                            </Button>
                        </div>
                    }
                />
                <Panel flush>
                    {days.length && slots.length ? (
                        <div className="scrollbar-thin overflow-x-auto">
                            <table className="w-full min-w-[640px] table-fixed text-sm">
                                <thead>
                                    <tr className="border-b border-border bg-canvas text-2xs font-semibold uppercase text-fg-muted">
                                        <th className="h-10 w-40 px-4 text-left">{isExam ? 'Date' : 'Day'}</th>
                                        {slots.map((t) => (
                                            <th key={t} className="tabular h-10 px-2 text-left normal-case">
                                                {t}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {days.map((d) => (
                                        <tr key={d} className="border-b border-border last:border-0">
                                            <td className="px-4 py-2 font-medium">{isExam ? formatDate(d, 'EEEE dd/MM/yyyy') : d}</td>
                                            {slots.map((t) => {
                                                const s = subject(d, t);
                                                return (
                                                    <td key={t} className="p-1">
                                                        <div className={cn('flex h-11 items-center rounded-md px-2 text-xs', s ? 'bg-primary-soft/60 font-medium text-primary-hover' : 'text-fg-subtle')}>
                                                            <span className="line-clamp-2">{s || '—'}</span>
                                                        </div>
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <EmptyState title="Nothing scheduled yet" description={urls.manage ? 'Add time slots and subjects under Edit timetable.' : undefined} />
                    )}
                </Panel>
            </div>
        </>
    );
}

TimetableShow.layout = withAppLayout;
