import { Columns3 } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';

import { Button } from '@/components/ui/button';
import { useT } from '@/providers/I18nProvider';
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
    const t = useT();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button type="button" variant="outline" size="sm" className="gap-1.5">
                    <Columns3 className="size-4" />
                    {t('reports.column_toggle.title')}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-48">
                <DropdownMenuLabel>{t('reports.column_toggle.desc')}</DropdownMenuLabel>
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

function marketingSourceColumns(t) {
    return [
        { id: 'aov', label: t('reports.column_toggle.aov'), default: true },
        { id: 'channel', label: t('reports.column_toggle.channel'), default: false },
        { id: 'budget', label: t('reports.column_toggle.budget'), default: false },
        { id: 'interactions', label: t('reports.column_toggle.interactions'), default: false },
        { id: 'contacts', label: t('reports.column_toggle.contacts'), default: true },
        { id: 'contactRate', label: t('reports.column_toggle.contact_rate'), default: false },
        { id: 'costPerContact', label: t('reports.column_toggle.cost_per_contact'), default: false },
        { id: 'closedOrders', label: t('reports.column_toggle.closed_orders'), default: true },
        { id: 'closingRate', label: t('reports.column_toggle.closing_rate'), default: true },
        { id: 'productQuantity', label: t('reports.column_toggle.product_quantity'), default: false },
        { id: 'avgProductPerOrder', label: t('reports.column_toggle.avg_product_per_order'), default: false },
        { id: 'utmSource', label: t('reports.column_toggle.utm_source'), default: false },
        { id: 'utmCampaign', label: t('reports.column_toggle.utm_campaign'), default: false },
    ];
}

export function useMarketingSourceColumns() {
    const t = useT();
    const columns = useMemo(() => marketingSourceColumns(t), [t]);
    const visibility = useTableColumnVisibility(columns, 'saleops-mkt-source-columns');

    return { ...visibility, columns };
}

export { marketingSourceColumns as MARKETING_SOURCE_COLUMNS };
