import * as AvatarPrimitive from '@radix-ui/react-avatar';
import { cn, initials, isPlaceholderPhoto, photoUrl } from '@/lib/utils';

const sizes = {
    xs: 'size-6 text-[10px]',
    sm: 'size-7 text-2xs',
    md: 'size-8 text-xs',
    lg: 'size-10 text-sm',
    xl: 'size-16 text-lg',
    '2xl': 'size-20 text-xl',
};

// Deterministic, muted tints so initials are distinguishable without being loud.
const tints = [
    'bg-indigo-50 text-indigo-700',
    'bg-sky-50 text-sky-700',
    'bg-emerald-50 text-emerald-700',
    'bg-amber-50 text-amber-700',
    'bg-rose-50 text-rose-700',
    'bg-violet-50 text-violet-700',
    'bg-teal-50 text-teal-700',
    'bg-slate-100 text-slate-700',
];

function tintFor(name = '') {
    let h = 0;
    for (let i = 0; i < name.length; i++) h = (h * 31 + name.charCodeAt(i)) >>> 0;
    return tints[h % tints.length];
}

export function Avatar({ src, name, size = 'md', className, square = false }) {
    const showImage = src && !isPlaceholderPhoto(src);
    return (
        <AvatarPrimitive.Root
            className={cn(
                'relative inline-flex shrink-0 select-none items-center justify-center overflow-hidden ring-1 ring-border',
                square ? 'rounded-md' : 'rounded-full',
                sizes[size],
                className,
            )}
        >
            {showImage && <AvatarPrimitive.Image src={photoUrl(src)} alt="" className="size-full object-cover" />}
            <AvatarPrimitive.Fallback
                delayMs={showImage ? 400 : 0}
                className={cn('flex size-full items-center justify-center font-semibold', tintFor(name))}
            >
                {initials(name) || '?'}
            </AvatarPrimitive.Fallback>
        </AvatarPrimitive.Root>
    );
}
