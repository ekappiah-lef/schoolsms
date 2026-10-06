import { useEffect, useRef, useState } from 'react';
import { Camera, ImageUp, Trash2 } from 'lucide-react';
import { cn, isPlaceholderPhoto } from '@/lib/utils';
import { Avatar } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Modal } from '@/components/ui/dialog';

const MAX_BYTES = 2 * 1024 * 1024; // matches the server rule: max:2048 (KB)
const ACCEPT = ['image/jpeg', 'image/png', 'image/gif'];

/**
 * Photo picker with preview and client-side checks mirroring the server rules
 * (image, jpeg/png/gif, ≤ 2 MB). The server still validates.
 */
export function PhotoUploader({ value, onChange, currentUrl, name, error, onError, camera = true }) {
    const inputRef = useRef(null);
    const captureRef = useRef(null);
    const [cameraOpen, setCameraOpen] = useState(false);
    // Live preview needs a secure page (https or localhost); elsewhere phones open their camera app instead.
    const liveCamera = typeof navigator !== 'undefined' && !!navigator.mediaDevices?.getUserMedia;
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
                <p className="text-sm font-medium">{value ? value.name : hasPhoto ? 'Current photo' : 'No photo uploaded'}</p>
                <p className="text-xs text-fg-muted">JPEG, PNG or GIF, up to 2 MB. Drag an image here, browse{camera ? ' or take a photo' : ''}.</p>
                <div className="mt-2 flex gap-2">
                    <Button size="xs" onClick={() => inputRef.current?.click()}>
                        <ImageUp />
                        {value || hasPhoto ? 'Replace' : 'Browse'}
                    </Button>
                    {camera && (
                        <Button size="xs" onClick={() => (liveCamera ? setCameraOpen(true) : captureRef.current?.click())}>
                            <Camera />
                            Take photo
                        </Button>
                    )}
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
            {camera && (
                <input
                    ref={captureRef}
                    type="file"
                    accept="image/*"
                    capture="user"
                    className="hidden"
                    onChange={(e) => {
                        pick(e.target.files?.[0]);
                        e.target.value = '';
                    }}
                />
            )}
            {cameraOpen && (
                <CameraDialog
                    onClose={() => setCameraOpen(false)}
                    onCapture={(file) => {
                        setCameraOpen(false);
                        pick(file);
                    }}
                    onError={(msg) => {
                        setCameraOpen(false);
                        onError?.(msg);
                    }}
                />
            )}
        </div>
    );
}

/** Live camera preview; "Capture" takes a square JPEG snapshot. */
function CameraDialog({ onClose, onCapture, onError }) {
    const videoRef = useRef(null);
    const streamRef = useRef(null);
    const [ready, setReady] = useState(false);
    const [facing, setFacing] = useState('user');

    useEffect(() => {
        let cancelled = false;
        navigator.mediaDevices
            .getUserMedia({ video: { facingMode: facing, width: { ideal: 1280 }, height: { ideal: 960 } }, audio: false })
            .then((stream) => {
                if (cancelled) return stream.getTracks().forEach((t) => t.stop());
                streamRef.current = stream;
                if (videoRef.current) {
                    videoRef.current.srcObject = stream;
                    videoRef.current.play().then(() => setReady(true)).catch(() => {});
                }
            })
            .catch((err) => !cancelled && onError(err?.name === 'NotAllowedError' ? 'Camera access was blocked. Allow the camera in the browser, or browse for a photo.' : 'No camera was found on this device.'));
        return () => {
            cancelled = true;
            streamRef.current?.getTracks().forEach((t) => t.stop());
            setReady(false);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [facing]);

    const capture = () => {
        const v = videoRef.current;
        if (!v?.videoWidth) return;
        const side = Math.min(v.videoWidth, v.videoHeight);
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = Math.min(side, 800);
        canvas.getContext('2d').drawImage(v, (v.videoWidth - side) / 2, (v.videoHeight - side) / 2, side, side, 0, 0, canvas.width, canvas.height);
        canvas.toBlob((blob) => blob && onCapture(new File([blob], `photo-${Date.now()}.jpg`, { type: 'image/jpeg' })), 'image/jpeg', 0.88);
    };

    return (
        <Modal
            open
            onOpenChange={(o) => !o && onClose()}
            title="Take photo"
            description="Centre the face in the square, then capture."
            footer={
                <>
                    <Button variant="ghost" className="mr-auto" onClick={() => setFacing((f) => (f === 'user' ? 'environment' : 'user'))}>
                        Switch camera
                    </Button>
                    <Button variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button variant="primary" onClick={capture} disabled={!ready}>
                        <Camera />
                        Capture
                    </Button>
                </>
            }
        >
            <div className="relative mx-auto aspect-square w-full max-w-sm overflow-hidden rounded-lg bg-black">
                <video ref={videoRef} playsInline muted className="size-full object-cover" style={{ transform: facing === 'user' ? 'scaleX(-1)' : undefined }} />
                {!ready && <div className="absolute inset-0 flex items-center justify-center text-sm text-white/80">Starting camera…</div>}
            </div>
        </Modal>
    );
}
