import { Head } from '@inertiajs/react';
import { NotebookPen } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { EmptyState, PageHeader, Panel } from '@/components/app/page';
import { Badge } from '@/components/ui/badge';
import { MarkSelector } from '@/components/marks/mark-selector';

export default function MarksIndex({ session, exams, classes, urls }) {
    return (
        <>
            <Head title="Marks entry" />
            <PageHeader
                breadcrumbs={[{ label: 'Academics' }, { label: 'Examinations' }, { label: 'Marks entry' }]}
                title="Marks entry"
                meta={<Badge tone="outline">{session} session</Badge>}
                description="Pick an exam, class, section and subject to open its score sheet. Every student in the section is listed on one sheet."
            />
            <Panel title="Open a score sheet">
                {exams.length ? (
                    <MarkSelector exams={exams} classes={classes} urls={urls} />
                ) : (
                    <EmptyState compact icon={NotebookPen} title="No exams this year" description="An administrator needs to create the exams for this year first." />
                )}
            </Panel>
        </>
    );
}

MarksIndex.layout = withAppLayout;
