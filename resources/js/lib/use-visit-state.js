import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';

/** True while an Inertia visit is in flight (for table dimming / skeletons). */
export function useVisitState() {
    const [busy, setBusy] = useState(false);
    useEffect(() => {
        const offStart = router.on('start', () => setBusy(true));
        const offFinish = router.on('finish', () => setBusy(false));
        return () => {
            offStart();
            offFinish();
        };
    }, []);
    return busy;
}

/** Download rows as a CSV file (client-side, current data only). */
export function downloadCsv(filename, columns, rows) {
    const escape = (v) => {
        const s = v === null || v === undefined ? '' : String(v);
        return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
    };
    const lines = [columns.map((c) => escape(c.label)).join(','), ...rows.map((r) => columns.map((c) => escape(c.value(r))).join(','))];
    const blob = new Blob([`﻿${lines.join('\r\n')}`], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
}
