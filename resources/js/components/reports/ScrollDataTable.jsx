import { cn } from '@/lib/utils';

export function ScrollDataTable({ children, className }) {
    return (
        <div
            className={cn(
                'max-w-full overflow-hidden rounded-xl border border-border bg-card shadow-sm',
                '[&_tbody_tr:nth-child(even)]:bg-muted/30',
                '[&_tbody_tr]:border-b [&_tbody_tr]:border-border/50',
                '[&_tbody_tr:last-child]:border-b-0',
                '[&_tbody_tr]:transition-colors [&_tbody_tr:hover]:bg-primary/[0.04]',
                className
            )}
        >
            <div className="max-w-full overflow-x-auto">{children}</div>
        </div>
    );
}

export function Th({ children, className, colSpan }) {
    return (
        <th
            colSpan={colSpan}
            className={cn(
                'whitespace-nowrap border-b-2 border-border bg-muted/80 px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-foreground/70',
                className
            )}
        >
            {children}
        </th>
    );
}

export function Td({ children, className, colSpan }) {
    return (
        <td
            colSpan={colSpan}
            className={cn(
                'whitespace-nowrap px-3 py-3 text-xs leading-relaxed',
                className
            )}
        >
            {children}
        </td>
    );
}
