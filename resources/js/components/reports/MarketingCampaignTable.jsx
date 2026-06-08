import { router } from '@inertiajs/react';
import { Check, Loader2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';

function BudgetCell({ row, budgetUpdateUrl, canEditBudget }) {
    const [value, setValue] = useState(String(row.adCost ?? 0));
    const [saving, setSaving] = useState(false);

    if (row.isTotalRow || !canEditBudget) {
        return <span className="tabular-nums">{formatCurrency(row.adCost)}</span>;
    }

    const save = () => {
        setSaving(true);
        router.patch(
            `${budgetUpdateUrl}/${row.campaignId}/budget`,
            { budget: Number(value) || 0 },
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Đã lưu chi phí quảng cáo.'),
                onError: () => toast.error('Không lưu được chi phí.'),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <div className="flex min-w-[140px] items-center gap-1">
            <Input
                type="number"
                min={0}
                className="h-7 px-2 text-xs"
                value={value}
                onChange={(e) => setValue(e.target.value)}
            />
            <Button type="button" size="icon" variant="ghost" className="size-7" onClick={save} disabled={saving}>
                {saving ? <Loader2 className="size-3.5 animate-spin" /> : <Check className="size-3.5" />}
            </Button>
        </div>
    );
}

export function MarketingCampaignTable({ rows = [], budgetUpdateUrl, canEditBudget = false }) {
    return (
        <ScrollDataTable>
            <table className="w-full min-w-[980px] border-collapse text-xs">
                <thead>
                    <tr>
                        <Th>STT</Th>
                        <Th>Tên chiến dịch</Th>
                        <Th>Người phụ trách</Th>
                        <Th className="text-right">Số lead</Th>
                        <Th className="text-right">Tỷ lệ lead rác</Th>
                        <Th>Chi phí QC</Th>
                        <Th className="text-right">Doanh thu thực tế</Th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr
                            key={row.campaignId}
                            className={row.isTotalRow ? 'bg-muted/50 font-semibold' : 'hover:bg-muted/30'}
                        >
                            <Td>{row.isTotalRow ? '—' : row.stt}</Td>
                            <Td className="font-medium">{row.campaignName}</Td>
                            <Td>{row.marketerName}</Td>
                            <Td className="text-right tabular-nums">{formatNumber(row.leadsGenerated)}</Td>
                            <Td className="text-right tabular-nums text-amber-700 dark:text-amber-400">
                                {formatPercent(row.junkLeadRate)}
                            </Td>
                            <Td>
                                <BudgetCell
                                    row={row}
                                    budgetUpdateUrl={budgetUpdateUrl}
                                    canEditBudget={canEditBudget}
                                />
                            </Td>
                            <Td className="text-right tabular-nums text-emerald-700 dark:text-emerald-400">
                                {formatCurrency(row.actualRevenue)}
                            </Td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </ScrollDataTable>
    );
}
