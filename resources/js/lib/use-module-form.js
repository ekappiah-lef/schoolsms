import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import { submitForm } from '@/lib/http';

/**
 * Form state + submission for the module "Create / Edit" cards.
 *
 * mode 'json'     — endpoint returns { ok, msg } (Qs::jsonStoreOk); errors come back as 422 JSON.
 * mode 'redirect' — endpoint redirects back with a flash message; errors arrive as Inertia `errors`.
 *
 * After creating, the list reloads and the "Show" tab opens; after editing, the index page opens.
 */
export function useModuleForm({ initial, editing, storeUrl, indexUrl, mode = 'json', validate, onCreated, only }) {
    const blank = { ...initial };
    const [data, setData] = useState(() => (editing ? { ...initial, ...pick(editing, Object.keys(initial)) } : blank));
    const [clientErrors, setClientErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const pageErrors = usePage().props.errors ?? {};
    const errors = { ...(mode === 'redirect' ? pageErrors : {}), ...clientErrors };

    const set = (key, value) => {
        setData((d) => ({ ...d, [key]: value }));
        setClientErrors((e) => ({ ...e, [key]: undefined }));
    };

    const reset = () => {
        setData(editing ? { ...initial, ...pick(editing, Object.keys(initial)) } : blank);
        setClientErrors({});
    };

    const submit = async () => {
        const local = validate?.(data) ?? {};
        setClientErrors(local);
        if (Object.keys(local).length) {
            toast.error('Please complete the required fields.');
            return;
        }

        const url = editing ? editing.url : storeUrl;

        if (mode === 'redirect') {
            // Called on the router itself: a detached router.post loses `this` and never sends.
            router[editing ? 'put' : 'post'](url, data, {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: (page) => {
                    if (Object.keys(page.props.errors ?? {}).length) return;
                    if (editing) router.visit(indexUrl);
                    else {
                        setData(blank);
                        onCreated?.();
                    }
                },
            });
            return;
        }

        setProcessing(true);
        const result = await submitForm(url, data, { method: editing ? 'put' : 'post' });
        setProcessing(false);
        if (result.ok) {
            toast.success(result.message);
            if (editing) {
                router.visit(indexUrl);
            } else {
                setData(blank);
                router.reload({ only, preserveScroll: true, onSuccess: () => onCreated?.() });
            }
        } else {
            if (result.errors) setClientErrors(result.errors);
            toast.error(result.message);
        }
    };

    return { data, set, errors, processing, submit, reset };
}

function pick(obj, keys) {
    return Object.fromEntries(keys.filter((k) => obj[k] !== undefined && obj[k] !== null).map((k) => [k, obj[k]]));
}
