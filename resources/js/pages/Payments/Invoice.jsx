import { Head, Link, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { Link2, UserRound } from 'lucide-react';
import { withAppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/components/app/page';
import { useConfirmAction } from '@/components/app/confirm-action';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Segmented } from '@/components/ui/tabs';
import { FeeTotals, MomoPay, OptionalFeesInvoice, PaymentHistory, SchoolFeesInvoice, TermInvoice } from '@/components/fees/fee-statement';

/** A student's two invoices (school fees with breakdown, optional services) and payments. */
export default function PaymentsInvoice({ student, year, years, statement, invoice, parentPhone, momoTest, urls }) {
    const [confirm, confirmDialog] = useConfirmAction();
    const reload = () => router.reload({ only: ['statement', 'invoice'], preserveScroll: true });
    const scope = year || 'all';

    const copyLink = async () => {
        try {
            await navigator.clipboard.writeText(urls.link);
            toast.success('Parent fees link copied. It works without logging in for 90 days.');
        } catch {
            window.prompt('Copy the parent fees link:', urls.link);
        }
    };

    return (
        <>
            <Head title={`Payments · ${student.name}`} />
            <PageHeader
                breadcrumbs={[{ label: 'Finance' }, { label: 'Student payments', href: urls.manage }, { label: student.name }]}
                title="Payment records"
                meta={
                    <span className="inline-flex flex-wrap items-center gap-2 text-base text-fg-muted">
                        <Avatar src={student.photo} name={student.name} size="sm" />
                        <Link href={student.profile_url} className="font-medium text-fg hover:text-primary">
                            {student.name}
                        </Link>
                        · <span className="tabular">{student.adm_no}</span> · {student.class}
                        <Badge tone={student.category === 'new' ? 'primary' : 'neutral'}>{student.category === 'new' ? 'New student' : 'Continuing student'}</Badge>
                    </span>
                }
                actions={
                    <>
                        <Button onClick={copyLink}>
                            <Link2 />
                            Copy parent link
                        </Button>
                        <Button asChild>
                            <Link href={student.profile_url}>
                                <UserRound />
                                Profile
                            </Link>
                        </Button>
                    </>
                }
            />

            {years.length > 1 && (
                <Segmented
                    className="mb-4"
                    value={scope}
                    onChange={(v) => router.visit(v === 'all' ? urls.all : urls.year.replace(':year', v), { preserveScroll: true })}
                    options={[{ value: 'all', label: 'All years' }, ...years.map((y) => ({ value: y, label: y }))]}
                />
            )}

            <div className="space-y-6">
                <TermInvoice invoice={invoice} sendUrl={urls.sendInvoice} />
                <MomoPay url={urls.momo} maxAmount={invoice?.total ?? 0} defaultPhone={parentPhone} test={momoTest} staff onPaid={reload} />
                <FeeTotals
                    school={statement.school.totals}
                    optional={statement.optional.totals}
                    overall={statement.totals}
                    scopeLabel={year ? `Total ${year}` : 'Total, all years'}
                />
                <SchoolFeesInvoice records={statement.school.records} onPaid={reload} confirm={confirm} />
                <OptionalFeesInvoice charges={statement.optional.charges} onPaid={reload} confirm={confirm} />
                <PaymentHistory school={statement.school.records} optional={statement.optional.charges} />
            </div>
            {confirmDialog}
        </>
    );
}

PaymentsInvoice.layout = withAppLayout;
