import { useEffect, useRef, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { Toaster, toast } from 'sonner';
import {
    ChevronDown,
    LogOut,
    Menu,
    MonitorSmartphone,
    PanelLeftClose,
    PanelLeftOpen,
    Search,
    UserRound,
    UserRoundCog,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { Avatar } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Sheet } from '@/components/ui/dialog';
import { TooltipProvider } from '@/components/ui/tooltip';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Brand, SessionCard, SidebarNav } from '@/components/app/sidebar';
import { CommandMenu } from '@/components/app/command-menu';
import { NavLink } from '@/components/app/nav-link';

const COLLAPSE_KEY = 'ui:sidebar-collapsed';

function useFlashToasts() {
    const { flash, errors } = usePage().props;
    const shown = useRef(new Set());

    useEffect(() => {
        (flash ?? []).forEach((f) => {
            if (shown.current.has(f.id)) return;
            shown.current.add(f.id);
            const fn = { success: toast.success, error: toast.error, warning: toast.warning, info: toast.info }[f.type] ?? toast;
            fn(f.message);
        });
    }, [flash]);

    // Validation errors from redirect-based endpoints (not the JSON forms).
    useEffect(() => {
        const list = Object.values(errors ?? {});
        if (list.length) toast.error(list[0]);
    }, [errors]);
}

export function quickActions(can, nav) {
    const find = (label) => nav.flatMap((s) => s.items.flatMap((i) => i.children ?? [i])).find((i) => i.label === label);
    return [
        can?.teamSA && find('Admit student') && { label: 'Admit student', ...find('Admit student') },
        can?.teamAccount && find('Student payments') && { label: 'Record a payment', ...find('Student payments') },
        can?.teamSAT && find('Marks entry') && { label: 'Enter marks', ...find('Marks entry') },
    ].filter(Boolean);
}

export default function AppLayout({ children }) {
    const { app, auth, nav } = usePage().props;
    const user = auth?.user;
    const [collapsed, setCollapsed] = useState(() => {
        try {
            return window.localStorage.getItem(COLLAPSE_KEY) === '1';
        } catch {
            return false;
        }
    });
    const [mobileOpen, setMobileOpen] = useState(false);
    const [commandOpen, setCommandOpen] = useState(false);
    const searchUrl = auth?.can?.teamSAT ? '/students/search' : null;

    useFlashToasts();

    useEffect(() => {
        try {
            window.localStorage.setItem(COLLAPSE_KEY, collapsed ? '1' : '0');
        } catch {
            /* ignore */
        }
    }, [collapsed]);

    useEffect(() => {
        const onKey = (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                setCommandOpen((o) => !o);
            }
        };
        window.addEventListener('keydown', onKey);
        const off = router.on('navigate', () => setMobileOpen(false));
        return () => {
            window.removeEventListener('keydown', onKey);
            off();
        };
    }, []);

    return (
        <TooltipProvider>
            <div className="min-h-screen bg-canvas">
                {/* Desktop sidebar */}
                <aside
                    className={cn(
                        'fixed inset-y-0 left-0 z-30 hidden flex-col border-r border-sidebar-border bg-sidebar transition-[width] duration-200 lg:flex',
                        collapsed ? 'w-16' : 'w-64',
                    )}
                >
                    <Brand app={app} collapsed={collapsed} />
                    <SidebarNav nav={nav ?? []} collapsed={collapsed} />
                    <SessionCard session={app.session} collapsed={collapsed} />
                </aside>

                {/* Mobile / tablet drawer */}
                <Sheet open={mobileOpen} onOpenChange={setMobileOpen} side="left" title="Navigation" hideTitle className="w-72">
                    <Brand app={app} />
                    <SidebarNav nav={nav ?? []} onNavigate={() => setMobileOpen(false)} />
                    <SessionCard session={app.session} />
                </Sheet>

                <div className={cn('flex min-h-screen flex-col transition-[padding] duration-200', collapsed ? 'lg:pl-16' : 'lg:pl-64')}>
                    <header className="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-3 border-b border-border bg-surface/90 px-3 backdrop-blur-xl md:px-6">
                        <Button variant="ghost" size="icon" className="lg:hidden" onClick={() => setMobileOpen(true)} aria-label="Open navigation">
                            <Menu />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="hidden lg:inline-flex"
                            onClick={() => setCollapsed((c) => !c)}
                            aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                        >
                            {collapsed ? <PanelLeftOpen /> : <PanelLeftClose />}
                        </Button>

                        <button
                            type="button"
                            onClick={() => setCommandOpen(true)}
                            className="focus-ring group flex h-9 min-w-0 flex-1 items-center gap-2 rounded-lg border border-border bg-muted px-3 text-sm text-fg-subtle transition-colors hover:border-border-strong sm:w-72 sm:flex-none"
                        >
                            <Search className="size-4 shrink-0" />
                            <span className="truncate">{searchUrl ? 'Find a student or page…' : 'Jump to a page…'}</span>
                            <kbd className="ml-auto hidden h-[18px] items-center rounded border border-border-strong bg-surface px-1.5 text-[10px] font-medium shadow-[0_1px_0_rgb(var(--border-strong))] sm:inline-flex">
                                Ctrl K
                            </kbd>
                        </button>

                        <span className="tabular hidden items-center gap-1.5 rounded-full border border-border bg-subtle px-3 py-1 text-xs font-semibold text-fg xl:inline-flex">
                            {app.session.replace('-', ' – ')} Year
                        </span>

                        <div className="ml-auto flex items-center gap-3">
                            <div className="hidden h-5 w-px bg-border sm:block" />

                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <button type="button" className="focus-ring flex items-center gap-2.5 rounded-md py-1 pl-1 pr-1.5 hover:bg-subtle">
                                        <Avatar src={user?.photo} name={user?.name} size="md" />
                                        <span className="hidden min-w-0 text-left leading-tight md:block">
                                            <span className="block max-w-[160px] truncate text-sm font-medium">{user?.name}</span>
                                            <span className="block text-xs text-fg-muted">{user?.role}</span>
                                        </span>
                                        <ChevronDown className="hidden size-3.5 text-fg-subtle md:block" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent className="w-60">
                                    <div className="px-2 py-1.5">
                                        <div className="truncate text-sm font-medium">{user?.name}</div>
                                        <div className="truncate text-xs text-fg-muted">{user?.email || user?.username}</div>
                                    </div>
                                    <DropdownMenuSeparator />
                                    {user?.profile_url && (
                                        <DropdownMenuItem asChild>
                                            <NavLink href={user.profile_url} spa={user.type === 'student'}>
                                                <UserRound />
                                                My profile
                                            </NavLink>
                                        </DropdownMenuItem>
                                    )}
                                    <DropdownMenuItem asChild>
                                        <a href={user?.account_url}>
                                            <UserRoundCog />
                                            Account settings
                                        </a>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem asChild>
                                        <a href={app.classic_url}>
                                            <MonitorSmartphone />
                                            Switch to classic interface
                                        </a>
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem asChild>
                                        <Link href="/logout" method="post" as="button" className="w-full">
                                            <LogOut />
                                            Sign out
                                        </Link>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </header>

                    <main className="flex-1 px-4 py-6 md:px-6 xl:px-8">{children}</main>
                </div>

                <CommandMenu open={commandOpen} onOpenChange={setCommandOpen} nav={nav ?? []} searchStudents={searchUrl} />
                <Toaster
                    position="bottom-right"
                    closeButton
                    toastOptions={{
                        classNames: {
                            toast: 'rounded-lg border border-border bg-surface text-fg shadow-lg text-sm font-sans',
                            description: 'text-fg-muted',
                        },
                    }}
                />
            </div>
        </TooltipProvider>
    );
}

/** Persistent layout helper: `Page.layout = withAppLayout` keeps the shell mounted between visits. */
export const withAppLayout = (page) => <AppLayout>{page}</AppLayout>;
