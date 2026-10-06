import { useState } from 'react';
import * as Collapsible from '@radix-ui/react-collapsible';
import { BadgeCheck, CalendarDays, ChevronDown } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Tooltip } from '@/components/ui/tooltip';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { NavLink } from './nav-link';
import { navIcon } from './icons';

export function Brand({ app, collapsed }) {
    const code = (app.code || app.name || 'S').slice(0, 2).toUpperCase();
    return (
        <div className={cn('flex h-16 shrink-0 items-center gap-2.5 border-b border-sidebar-border', collapsed ? 'justify-center px-2' : 'px-4')}>
            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary text-xs font-bold tracking-tight text-white shadow-btn">
                {code}
            </div>
            {!collapsed && (
                <div className="min-w-0 leading-tight">
                    <div className="truncate text-md font-semibold leading-tight tracking-tight text-fg">{app.name}</div>
                </div>
            )}
        </div>
    );
}

function itemClass(active, collapsed) {
    return cn(
        'focus-ring group flex h-9 w-full items-center gap-3 rounded-lg text-sm font-medium transition-colors [&_svg]:size-[18px] [&_svg]:shrink-0',
        collapsed ? 'justify-center px-0' : 'px-3',
        active ? 'bg-primary font-semibold text-white shadow-btn [&_svg]:text-white' : 'text-fg-muted hover:bg-muted hover:text-fg [&_svg]:text-fg-subtle hover:[&_svg]:text-fg-muted',
    );
}

function NavItem({ item, collapsed, onNavigate }) {
    const Icon = navIcon(item.icon);
    return (
        <Tooltip content={item.label} side="right" disabled={!collapsed}>
            <NavLink href={item.href} spa={item.spa} onClick={onNavigate} className={itemClass(item.active, collapsed)} aria-current={item.active ? 'page' : undefined}>
                <Icon />
                {!collapsed && <span className="truncate">{item.label}</span>}
            </NavLink>
        </Tooltip>
    );
}

function NavGroup({ item, collapsed, onNavigate }) {
    const Icon = navIcon(item.icon);
    const [open, setOpen] = useState(item.active);

    if (collapsed) {
        return (
            <DropdownMenu>
                <Tooltip content={item.label} side="right">
                    <DropdownMenuTrigger asChild>
                        <button type="button" className={cn(itemClass(false, true), item.active && 'bg-primary-soft [&_svg]:text-primary')}>
                            <Icon />
                        </button>
                    </DropdownMenuTrigger>
                </Tooltip>
                <DropdownMenuContent side="right" align="start" sideOffset={8}>
                    <DropdownMenuLabel>{item.label}</DropdownMenuLabel>
                    {item.children.map((child) => (
                        <DropdownMenuItem key={child.href} asChild className={child.active ? 'font-medium text-primary' : undefined}>
                            <NavLink href={child.href} spa={child.spa}>
                                {child.label}
                            </NavLink>
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>
        );
    }

    return (
        <Collapsible.Root open={open} onOpenChange={setOpen}>
            <Collapsible.Trigger
                className={cn(
                    itemClass(false, false),
                    item.active && 'text-fg [&_svg]:text-fg-muted',
                )}
            >
                <Icon />
                <span className="flex-1 truncate text-left">{item.label}</span>
                <ChevronDown className={cn('!size-3.5 transition-transform', open ? 'rotate-0' : '-rotate-90')} />
            </Collapsible.Trigger>
            <Collapsible.Content className="overflow-hidden">
                <ul className="relative ml-[17px] mt-0.5 space-y-0.5 border-l border-border pl-2.5">
                    {item.children.map((child) => (
                        <li key={child.href} className="relative">
                            {child.active && <span className="absolute -left-[11px] top-1.5 h-5 w-px bg-primary" />}
                            <NavLink
                                href={child.href}
                                spa={child.spa}
                                onClick={onNavigate}
                                aria-current={child.active ? 'page' : undefined}
                                className={cn(
                                    'focus-ring flex h-8 items-center rounded-lg px-2.5 text-sm transition-colors',
                                    child.active ? 'bg-muted font-semibold text-primary-hover' : 'text-fg-muted hover:bg-muted hover:text-fg',
                                )}
                            >
                                <span className="truncate">{child.label}</span>
                            </NavLink>
                        </li>
                    ))}
                </ul>
            </Collapsible.Content>
        </Collapsible.Root>
    );
}

export function SidebarNav({ nav, collapsed = false, onNavigate }) {
    return (
        <nav className={cn('scrollbar-thin flex-1 overflow-y-auto py-3', collapsed ? 'px-2' : 'px-3')} aria-label="Main">
          
            {nav.map((section, i) => (
                <div key={section.label ?? i} className={cn(i > 0 && 'mt-5')}>
                    {section.label &&
                        (collapsed ? (
                            <div className="mx-auto mb-2 h-px w-6 bg-border" />
                        ) : (
                            <div className="mb-1.5 px-3 text-2xs font-semibold uppercase tracking-wider text-fg-subtle">{section.label}</div>
                        ))}
                    <ul className="space-y-0.5">
                        {section.items.map((item) => (
                            <li key={item.label}>
                                {item.children ? (
                                    <NavGroup item={item} collapsed={collapsed} onNavigate={onNavigate} />
                                ) : (
                                    <NavItem item={item} collapsed={collapsed} onNavigate={onNavigate} />
                                )}
                            </li>
                        ))}
                    </ul>
                </div>
            ))}
        </nav>
    );
}

export function SessionCard({ session, collapsed }) {
    if (collapsed) {
        return (
            <Tooltip content={`Year ${session}`} side="right">
                <div className="mx-auto mb-3 flex size-8 items-center justify-center rounded-md border border-border text-fg-subtle">
                    <CalendarDays className="size-4" />
                </div>
            </Tooltip>
        );
    }
    return (
        <div className="border-t border-sidebar-border p-3">
          <div className="flex items-center gap-2.5 rounded-lg bg-muted px-3 py-2.5">
            <BadgeCheck className="size-5 shrink-0 text-success" />
            <div className="min-w-0 leading-tight">
                <div className="text-xs font-semibold text-fg">Academic year</div>
                <div className="tabular text-xs text-fg-muted">{session} · active</div>
            </div>
            </div>
        </div>
    );
}
