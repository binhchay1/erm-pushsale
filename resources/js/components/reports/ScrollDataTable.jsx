import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';

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
                '[&_thead_th]:!border-[#2f72c4] [&_thead_th]:!bg-[#3782dc] [&_thead_th]:!text-white',
                '[&_thead_th_svg]:!text-white/80',
                className
            )}
        >
            <div className="max-w-full overflow-x-auto">{children}</div>
        </div>
    );
}

export function Th({ children, className, colSpan, sortable, sortKey, sort, onSort }) {
    if (sortable && sortKey && onSort) {
        const active = sort?.key === sortKey;
        const Icon = active ? (sort.dir === 'asc' ? ArrowUp : ArrowDown) : ArrowUpDown;

        return (
            <th
                colSpan={colSpan}
                className={cn(
                    'whitespace-nowrap border-b-2 border-[#2f72c4] bg-[#3782dc] px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-white',
                    'cursor-pointer select-none transition-colors hover:bg-[#2f72c4]',
                    active && 'text-white',
                    className
                )}
                onClick={() => onSort(sortKey)}
            >
                <span className="inline-flex items-center gap-1">
                    {children}
                    <Icon className={cn('size-3 shrink-0', active ? 'text-white' : 'text-white/70')} />
                </span>
            </th>
        );
    }

    return (
        <th
            colSpan={colSpan}
            className={cn(
                'whitespace-nowrap border-b-2 border-[#2f72c4] bg-[#3782dc] px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-white',
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
