import { cn } from '@/lib/utils';

const TONES = {
    success: 'border border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    warning: 'border border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300',
    danger: 'border border-red-200 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-300',
    info: 'border border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-300',
    muted: 'border border-border bg-muted/60 text-muted-foreground',
};

export function StatusBadge({ tone = 'muted', icon: Icon, children, className }) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-semibold leading-tight',
                TONES[tone] ?? TONES.muted,
                className,
            )}
        >
            {Icon && <Icon className="size-3" />}
            {children}
        </span>
    );
}
