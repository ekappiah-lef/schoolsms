import { Head, Link } from '@inertiajs/react';
import { Pencil, Printer } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { ModuleHeader } from '@/components/app/module';
import { EmptyState, Panel } from '@/components/app/page';
import { Button } from '@/components/ui/button';
import { TimetableGrid } from '@/components/timetable-grid';

/** A class or exam timetable: days down the side, periods across, breaks as tall columns. */
export default function TimetableShow({ record, isExam, slots, rows, others = [], urls }) {
    return (
        <>
            <Head title={record.name} />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6">
                <ModuleHeader
                    crumbs={['Academics', 'Timetables', record.name]}
                    title={record.name}
                    description={`${record.class ?? ''}${record.exam ? ` · ${record.exam}` : ''} · ${record.year}`}
                    aside={
                        <div className="flex flex-wrap gap-2">
                            {others.map((o) => (
                                <Button key={o.url} asChild>
                                    <Link href={o.url}>{o.name}</Link>
                                </Button>
                            ))}
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
                    {rows.length && slots.length ? (
                        <TimetableGrid isExam={isExam} slots={slots} rows={rows} />
                    ) : (
                        <EmptyState title="Nothing scheduled yet" description={urls.manage ? 'Add time slots and subjects under Edit timetable.' : undefined} />
                    )}
                </Panel>
            </div>
        </>
    );
}

TimetableShow.layout = withAppLayout;
