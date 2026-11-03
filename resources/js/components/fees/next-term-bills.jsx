import { useState } from "react";
import { toast } from "sonner";
import { Modal } from "@/components/ui/dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { submitForm } from "@/lib/http";
import { formatDate, formatMoney, formatNumber } from "@/lib/utils";

/** The director checks what each class will be billed next term and approves it to go out on the term's last day. */
export function NextTermBills({ url }) {
    const [open, setOpen] = useState(false);
    const [data, setData] = useState(null);
    const [busy, setBusy] = useState(false);
    const load = async (extra = {}) => {
        setBusy(true);
        const r = await submitForm(url, extra);
        setBusy(false);
        if (r.ok) setData(r.data);
        else toast.error(r.message);
    };
    const s = data?.status;
    return (
        <>
            <Button onClick={() => { setOpen(true); load(); }}>
                Next term bills
            </Button>
            <Modal
                open={open}
                onOpenChange={setOpen}
                title={s ? `Next term bills · ${s.label}` : "Next term bills"}
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setOpen(false)}>
                            Close
                        </Button>
                        {s && !s.sent && (
                            <Button variant={s.approved ? "secondary" : "primary"} loading={busy} onClick={() => load({ approve: s.approved ? 0 : 1 })}>
                                {s.approved ? "Cancel approval" : "Approve"}
                            </Button>
                        )}
                    </>
                }
            >
                {!data ? (
                    <p className="text-sm text-fg-muted">Loading…</p>
                ) : !s ? (
                    <p className="text-sm text-fg-muted">This term has no end date in the school calendar.</p>
                ) : (
                    <div className="space-y-3">
                        <div className="flex items-center justify-between text-sm font-medium">
                            <span>{formatDate(s.date, "d MMM yyyy")}</span>
                            <Badge>{s.sent ? "Sent" : s.approved ? "Approved" : "Not approved"}</Badge>
                        </div>
                        <div className="max-h-80 overflow-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-left text-xs text-fg-muted">
                                        <th className="py-1 pr-2 font-medium">Class</th>
                                        <th className="py-1 pr-2 text-right font-medium">Students</th>
                                        <th className="py-1 pr-2 text-right font-medium">Next term</th>
                                        <th className="py-1 pr-2 text-right font-medium">Still owed</th>
                                        <th className="py-1 text-right font-medium">Total</th>
                                    </tr>
                                </thead>
                                <tbody className="tabular">
                                    {data.classes.map((c) => (
                                        <tr key={c.class} className="border-t border-border">
                                            <td className="py-1 pr-2">{c.class}</td>
                                            <td className="py-1 pr-2 text-right">{formatNumber(c.students)}</td>
                                            <td className="py-1 pr-2 text-right">{formatMoney(c.next)}</td>
                                            <td className="py-1 pr-2 text-right">{formatMoney(c.owed)}</td>
                                            <td className="py-1 text-right font-medium">{formatMoney(c.total)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {data.not_set > 0 && (
                            <p className="text-sm font-medium text-danger">{data.not_set} students: next term's fees not set up</p>
                        )}
                    </div>
                )}
            </Modal>
        </>
    );
}
