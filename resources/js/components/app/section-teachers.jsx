import { ChevronDown } from 'lucide-react';
import { DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';

/** Teachers of a subject per section: one tick-list per section. value/onChange: JSON {section_id: [teacher id, …]}. */
export function SectionTeachers({ sections, teachers, value, onChange }) {
    const map = (() => {
        try {
            return JSON.parse(value || '{}') || {};
        } catch {
            return {};
        }
    })();
    const names = Object.fromEntries(teachers.map((t) => [t.id, t.name]));
    const toggle = (sid, tid) => {
        const cur = map[sid] ?? [];
        const next = { ...map, [sid]: cur.includes(tid) ? cur.filter((x) => x !== tid) : [...cur, tid] };
        onChange(JSON.stringify(next));
    };

    return (
        <div className="flex flex-col divide-y divide-border rounded-lg bg-surface shadow-field">
            {sections.map((s) => {
                const picked = map[s.id] ?? [];
                return (
                    <div key={s.id} className="flex items-center gap-3 px-3 py-2">
                        <span className="w-40 shrink-0 truncate text-sm font-medium">{s.name}</span>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button type="button" className="flex min-h-9 flex-1 items-center justify-between gap-2 rounded-md px-2 text-left text-sm hover:bg-muted">
                                    <span className={picked.length ? 'text-fg' : 'text-fg-subtle'}>{picked.length ? picked.map((id) => names[id]).filter(Boolean).join(', ') : 'No teacher'}</span>
                                    <ChevronDown className="size-4 shrink-0 text-fg-subtle" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent className="max-h-72 overflow-y-auto">
                                {teachers.map((t) => (
                                    <DropdownMenuCheckboxItem key={t.id} checked={picked.includes(t.id)} onSelect={(e) => e.preventDefault()} onCheckedChange={() => toggle(s.id, t.id)}>
                                        {t.name}
                                    </DropdownMenuCheckboxItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                );
            })}
        </div>
    );
}
