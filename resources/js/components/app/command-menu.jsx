import { useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import { Command } from 'cmdk';
import { ArrowRight, LoaderCircle, Search } from 'lucide-react';
import http from '@/lib/http';
import { Avatar } from '@/components/ui/avatar';
import { navIcon } from './icons';
import { visit } from './nav-link';

function flatten(nav) {
    const out = [];
    nav.forEach((section) =>
        section.items.forEach((item) => {
            if (item.children) item.children.forEach((c) => out.push({ ...c, group: item.label, icon: item.icon }));
            else out.push({ ...item, group: section.label });
        }),
    );
    return out;
}

/**
 * Ctrl/⌘ + K: jump to any page the user can access, or find a student by
 * name / admission number (staff only; uses GET /students/search).
 */
export function CommandMenu({ open, onOpenChange, nav, searchStudents }) {
    const [query, setQuery] = useState('');
    const [students, setStudents] = useState([]);
    const [loading, setLoading] = useState(false);
    const pages = useMemo(() => flatten(nav), [nav]);

    useEffect(() => {
        if (!open) {
            setQuery('');
            setStudents([]);
        }
    }, [open]);

    useEffect(() => {
        if (!searchStudents || query.trim().length < 2) {
            setStudents([]);
            return undefined;
        }
        setLoading(true);
        const controller = new AbortController();
        const t = setTimeout(() => {
            http.get(searchStudents, { params: { q: query }, signal: controller.signal })
                .then(({ data }) => setStudents(Array.isArray(data) ? data : []))
                .catch(() => {})
                .finally(() => setLoading(false));
        }, 200);
        return () => {
            clearTimeout(t);
            controller.abort();
        };
    }, [query, searchStudents]);

    const go = (href, spa) => {
        onOpenChange(false);
        visit(router, href, spa);
    };

    return (
        <DialogPrimitive.Root open={open} onOpenChange={onOpenChange}>
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-slate-900/40 data-[state=open]:animate-fade-in" />
                <DialogPrimitive.Content className="fixed left-1/2 top-[14vh] z-50 w-[calc(100%-2rem)] max-w-xl -translate-x-1/2 overflow-hidden rounded-xl border border-border bg-surface shadow-lg data-[state=open]:animate-pop-in">
                    <DialogPrimitive.Title className="sr-only">Quick find</DialogPrimitive.Title>
                    <DialogPrimitive.Description className="sr-only">Search pages and students</DialogPrimitive.Description>
                    <Command shouldFilter loop>
                        <div className="flex items-center gap-2.5 border-b border-border px-4">
                            {loading ? <LoaderCircle className="size-4 animate-spin text-fg-subtle" /> : <Search className="size-4 text-fg-subtle" />}
                            <Command.Input
                                value={query}
                                onValueChange={setQuery}
                                placeholder={searchStudents ? 'Search students or jump to a page…' : 'Jump to a page…'}
                                className="h-12 w-full bg-transparent text-md outline-none placeholder:text-fg-subtle"
                            />
                        </div>
                        <Command.List className="scrollbar-thin max-h-[360px] overflow-y-auto p-2">
                            <Command.Empty className="py-10 text-center text-sm text-fg-muted">
                                {query.length < 2 && searchStudents ? 'Type at least 2 characters to search students.' : 'No results.'}
                            </Command.Empty>
                            {students.length > 0 && (
                                <Command.Group heading="Students" className="[&_[cmdk-group-heading]]:px-2 [&_[cmdk-group-heading]]:pb-1 [&_[cmdk-group-heading]]:pt-2 [&_[cmdk-group-heading]]:text-2xs [&_[cmdk-group-heading]]:font-semibold [&_[cmdk-group-heading]]:uppercase [&_[cmdk-group-heading]]:text-fg-subtle">
                                    {students.map((s) => (
                                        <Command.Item
                                            key={s.url}
                                            value={`student ${s.name} ${s.adm_no} ${query}`}
                                            onSelect={() => go(s.url, true)}
                                            className="flex h-11 cursor-default items-center gap-3 rounded-md px-2 text-sm outline-none data-[selected=true]:bg-subtle"
                                        >
                                            <Avatar src={s.photo} name={s.name} size="sm" />
                                            <div className="min-w-0 flex-1">
                                                <div className="truncate font-medium">{s.name}</div>
                                                <div className="truncate text-xs text-fg-muted">
                                                    {s.adm_no} · {s.class}
                                                </div>
                                            </div>
                                            <ArrowRight className="size-4 text-fg-subtle" />
                                        </Command.Item>
                                    ))}
                                </Command.Group>
                            )}
                            <Command.Group heading="Pages" className="[&_[cmdk-group-heading]]:px-2 [&_[cmdk-group-heading]]:pb-1 [&_[cmdk-group-heading]]:pt-2 [&_[cmdk-group-heading]]:text-2xs [&_[cmdk-group-heading]]:font-semibold [&_[cmdk-group-heading]]:uppercase [&_[cmdk-group-heading]]:text-fg-subtle">
                                {pages.map((p) => {
                                    const Icon = navIcon(p.icon);
                                    return (
                                        <Command.Item
                                            key={p.href}
                                            value={`${p.label} ${p.group ?? ''}`}
                                            onSelect={() => go(p.href, p.spa)}
                                            className="flex h-9 cursor-default items-center gap-3 rounded-md px-2 text-sm outline-none data-[selected=true]:bg-subtle"
                                        >
                                            <Icon className="size-4 text-fg-subtle" />
                                            <span>{p.label}</span>
                                            {p.group && <span className="ml-auto text-xs text-fg-subtle">{p.group}</span>}
                                        </Command.Item>
                                    );
                                })}
                            </Command.Group>
                        </Command.List>
                    </Command>
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}
