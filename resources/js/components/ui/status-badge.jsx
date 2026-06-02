import { cn } from '@/lib/utils';

const TONES = {
    success: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    warning: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    danger: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    info: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
    muted: 'bg-muted text-muted-foreground',
};

export function StatusBadge({ tone = 'muted', icon: Icon, children, className }) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
                TONES[tone] ?? TONES.muted,
                className,
            )}
        >
            {Icon && <Icon className="size-3" />}
            {children}
        </span>
    );
}
