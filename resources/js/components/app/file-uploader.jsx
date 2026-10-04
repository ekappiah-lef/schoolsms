import { useEffect, useRef, useState } from 'react';
import { ImageUp, Trash2 } from 'lucide-react';
import { cn, isPlaceholderPhoto } from '@/lib/utils';
import { Avatar } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';

const MAX_BYTES = 2 * 1024 * 1024; // matches the server rule: max:2048 (KB)
const ACCEPT = ['image/jpeg', 'image/png', 'image/gif'];

/**
 * Photo picker with preview and client-side checks mirroring the server rules
 * (image, jpeg/png/gif, ≤ 2 MB). The server still validates.
 */
export function PhotoUploader({ value, onChange, currentUrl, name, error, onError }) {
    const inputRef = useRef(null);
    const [preview, setPreview] = useState(null);
    const [dragging, setDragging] = useState(false);
    const hasPhoto = !isPlaceholderPhoto(currentUrl);

    useEffect(() => {
        if (!value) {
            setPreview(null);
            return undefined;
        }
        const url = URL.createObjectURL(value);
        setPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [value]);

    const pick = (file) => {
        if (!file) return;
        if (!ACCEPT.includes(file.type)) return onError?.('Use a JPEG, PNG or GIF image.');
        if (file.size > MAX_BYTES) return onError?.('The photo must be 2 MB or smaller.');
        onError?.(null);
        onChange(file);
    };

    return (
        <div
            onDragOver={(e) => {
                e.preventDefault();
                setDragging(true);
            }}
            onDragLeave={() => setDragging(false)}
            onDrop={(e) => {
                e.preventDefault();
                setDragging(false);
                pick(e.dataTransfer.files?.[0]);
            }}
            className={cn(
                'flex items-center gap-4 rounded-lg border border-dashed p-3 transition-colors',
                dragging ? 'border-primary bg-primary-soft' : error ? 'border-danger bg-danger-soft/40' : 'border-border-strong bg-muted/50',
            )}
        >
            {preview ? (
                <img src={preview} alt="" className="size-16 shrink-0 rounded-md object-cover ring-1 ring-border" />
            ) : hasPhoto ? (
                <Avatar src={currentUrl} name={name} size="xl" square />
            ) : (
                <div className="flex size-16 shrink-0 items-center justify-center rounded-md border border-border bg-surface text-fg-subtle">
                    <ImageUp className="size-6" />
                </div>
            )}
            <div className="min-w-0 flex-1">
                <p className="text-sm font-medium">{value ? value.name : hasPhoto ? 'Current passport photo' : 'No photo uploaded'}</p>
                <p className="text-xs text-fg-muted">JPEG, PNG or GIF, up to 2 MB. Drag an image here or browse.</p>
                <div className="mt-2 flex gap-2">
                    <Button size="xs" onClick={() => inputRef.current?.click()}>
                        <ImageUp />
                        {value || hasPhoto ? 'Replace' : 'Browse'}
                    </Button>
                    {value && (
                        <Button size="xs" variant="ghost" onClick={() => onChange(null)}>
                            <Trash2 />
                            Remove
                        </Button>
                    )}
                </div>
            </div>
            <input
                ref={inputRef}
                type="file"
                accept={ACCEPT.join(',')}
                className="hidden"
                onChange={(e) => {
                    pick(e.target.files?.[0]);
                    e.target.value = '';
                }}
            />
        </div>
    );
}
