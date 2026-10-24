/**
 * Colours for the school calendar, shared by the School calendar page and the dashboard.
 * day: a day cell; dot: legend/dot; chip: the date block in the agenda; badge: the agenda tag.
 * Order matters: when several fall on one day, the first one listed wins.
 */
export const CALENDAR_KINDS = {
    holiday: { label: 'Holiday', day: 'bg-danger-soft text-danger-fg font-semibold', dot: 'bg-danger', chip: 'bg-danger-soft text-danger-fg', badge: 'bg-danger-soft text-danger-fg' },
    exam: { label: 'Examination', day: 'bg-[#ede9fe] text-[#6d28d9] font-semibold', dot: 'bg-[#7c3aed]', chip: 'bg-[#ede9fe] text-[#6d28d9]', badge: 'bg-[#ede9fe] text-[#6d28d9]' },
    midterm_exam: { label: 'Mid-term exams', day: 'bg-[#fae8ff] text-[#a21caf] font-semibold', dot: 'bg-[#c026d3]', chip: 'bg-[#fae8ff] text-[#a21caf]', badge: 'bg-[#fae8ff] text-[#a21caf]' },
    revision: { label: 'Revision week', day: 'bg-info-soft text-info-fg font-semibold', dot: 'bg-info', chip: 'bg-info-soft text-info-fg', badge: 'bg-info-soft text-info-fg' },
    event: { label: 'Event', day: 'bg-success-soft text-success-fg font-semibold', dot: 'bg-success', chip: 'bg-success-soft text-success-fg', badge: 'bg-success-soft text-success-fg' },
    midterm: { label: 'Mid-term break', day: 'bg-warning-soft text-warning-fg font-semibold', dot: 'bg-warning', chip: 'bg-warning-soft text-warning-fg', badge: 'bg-warning-soft text-warning-fg' },
    term_begins: { label: 'Term begins', day: '', dot: 'bg-primary', chip: 'bg-primary-soft text-primary-hover', badge: 'bg-primary-soft text-primary-hover' },
    term_ends: { label: 'Term ends', day: '', dot: 'bg-primary', chip: 'bg-muted text-fg', badge: 'bg-muted text-fg-muted' },
    term: { label: 'School day', day: 'bg-primary-soft/60 text-fg', dot: 'bg-primary/40', chip: 'bg-primary-soft text-primary-hover', badge: 'bg-primary-soft text-primary-hover' },
};

export const KIND_ORDER = ['holiday', 'exam', 'midterm_exam', 'revision', 'event', 'midterm', 'term'];
