import { Columns3 } from 'lucide-react';
import { useCallback, useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

function readStoredVisibility(columns, storageKey) {
    const defaults = Object.fromEntries(columns.map((c) => [c.id, c.default !== false]));
    if (!storageKey || typeof window === 'undefined') return defaults;

    try {
        const saved = localStorage.getItem(storageKey);
        if (saved) return { ...defaults, ...JSON.parse(saved) };
    } catch {
        /* ignore */
    }

    return defaults;
}

export function useTableColumnVisibility(columns, storageKey) {
    const [visible, setVisible] = useState(() => readStoredVisibility(columns, storageKey));

    const toggle = useCallback(
        (id, checked) => {
            setVisible((prev) => {
                const next = { ...prev, [id]: checked };
                if (storageKey) {
                    localStorage.setItem(storageKey, JSON.stringify(next));
                }
                return next;
            });
        },
        [storageKey]
    );

    const isVisible = useCallback((id) => visible[id] !== false, [visible]);

    return { visible, isVisible, toggle };
}

export function TableColumnToggle({ columns, visible, onToggle }) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button type="button" variant="outline" size="sm" className="gap-1.5">
                    <Columns3 className="size-4" />
                    Cột hiển thị
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-48">
                <DropdownMenuLabel>Ẩn / hiện cột</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {columns.map((col) => (
                    <DropdownMenuCheckboxItem
                        key={col.id}
                        checked={visible[col.id] !== false}
                        onCheckedChange={(checked) => onToggle(col.id, checked)}
                    >
                        {col.label}
                    </DropdownMenuCheckboxItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export const MARKETING_SOURCE_COLUMNS = [
    { id: 'product', label: 'Sản phẩm', default: true },
    { id: 'channel', label: 'Kênh quảng cáo', default: false },
    { id: 'budget', label: 'Ngân sách', default: false },
    { id: 'contacts', label: 'Contact', default: true },
    { id: 'contactRate', label: '% Contact', default: false },
    { id: 'costPerContact', label: 'Giá contact', default: false },
    { id: 'closedOrders', label: 'Đơn chốt', default: true },
    { id: 'closingRate', label: '% Chốt', default: true },
];

export function useMarketingSourceColumns() {
    return useTableColumnVisibility(MARKETING_SOURCE_COLUMNS, 'saleops-mkt-source-columns');
}
