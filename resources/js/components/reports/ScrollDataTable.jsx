import { cn } from '@/lib/utils';

export function ScrollDataTable({ children, className }) {
    return (
        <div className={cn('max-w-full overflow-hidden rounded-xl border border-border bg-card shadow-sm', className)}>
            <div className="max-w-full overflow-x-auto">{children}</div>
        </div>
    );
}

export function Th({ children, className, colSpan }) {
    return (
        <th
            colSpan={colSpan}
            className={cn(
                'whitespace-nowrap border-b border-border bg-primary px-3 py-2 text-left text-xs font-semibold text-primary-foreground',
                className
            )}
        >
            {children}
        </th>
    );
}

export function Td({ children, className }) {
    return (
        <td className={cn('whitespace-nowrap border-b border-border/60 px-3 py-2 text-xs', className)}>
            {children}
        </td>
    );
}
