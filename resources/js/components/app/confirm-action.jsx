import { useState } from 'react';
import { router } from '@inertiajs/react';
import { ConfirmDialog } from '@/components/ui/dialog';

/**
 * Hook for actions handled by redirect-based Laravel routes (delete, reset,
 * restore). The controller's flash message becomes a toast via the layout.
 *
 * const [confirm, dialog] = useConfirmAction();
 * confirm({ title, description, confirmLabel, method: 'delete', url });
 */
export function useConfirmAction() {
    const [action, setAction] = useState(null);
    const [loading, setLoading] = useState(false);

    const run = () => {
        if (!action) return;
        setLoading(true);
        router.visit(action.url, {
            method: action.method ?? 'get',
            preserveScroll: true,
            // Keep the page's UI state (e.g. the open tab) when the list reloads.
            preserveState: true,
            onFinish: () => {
                setLoading(false);
                setAction(null);
            },
        });
    };

    const dialog = (
        <ConfirmDialog
            open={!!action}
            onOpenChange={(open) => !open && !loading && setAction(null)}
            title={action?.title}
            description={action?.description}
            confirmLabel={action?.confirmLabel}
            tone={action?.tone ?? 'danger'}
            loading={loading}
            onConfirm={run}
        />
    );

    return [setAction, dialog];
}
