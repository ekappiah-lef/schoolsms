import { Mail, MessageSquare } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { formatDate } from '@/lib/utils';

const KIND = { admission: 'Admission letter', fees_link: 'Fees link' };

/** Delivery results for admission emails / SMS. */
export function NoticeList({ notices }) {
    return (
        <ul className="divide-y divide-border overflow-hidden rounded-lg shadow-field">
            {notices.map((n, i) => {
                const Icon = n.channel === 'sms' ? MessageSquare : Mail;
                return (
                    <li key={i} className="flex items-start gap-3 px-3 py-2.5 text-sm">
                        <Icon className="mt-0.5 size-4 shrink-0 text-fg-subtle" />
                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-x-2">
                                <span className="font-medium">{KIND[n.kind] ?? n.kind}</span>
                                <span className="truncate text-fg-muted">to {n.recipient}</span>
                                {n.date && <span className="text-xs text-fg-subtle">{formatDate(n.date, 'dd/MM/yyyy HH:mm')}</span>}
                            </div>
                            {n.error && <p className="mt-0.5 text-xs text-fg-muted">{n.error}</p>}
                        </div>
                        <Badge tone={n.status === 'sent' ? 'success' : 'danger'}>{n.status === 'sent' ? 'Sent' : 'Not sent'}</Badge>
                    </li>
                );
            })}
        </ul>
    );
}
