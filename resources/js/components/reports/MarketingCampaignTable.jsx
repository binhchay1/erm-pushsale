import { router } from '@inertiajs/react';
import { Check, Loader2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { CurrencyInput } from '@/components/ui/currency-input';
import { Button } from '@/components/ui/button';
import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

function BudgetCell({ row, budgetUpdateUrl, canEditBudget }) {
    const t = useT();
    const [value, setValue] = useState(row.adCost ?? 0);
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
                onSuccess: () => toast.success(t('reports.marketing_campaign_table.budget_saved')),
                onError: () => toast.error(t('reports.marketing_campaign_table.budget_failed')),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <div className="flex min-w-[140px] items-center gap-1">
            <CurrencyInput
                className="h-7 px-2 text-xs"
                value={value}
                onChange={setValue}
            />
            <Button type="button" size="icon" variant="ghost" className="size-7" onClick={save} disabled={saving}>
                {saving ? <Loader2 className="size-3.5 animate-spin" /> : <Check className="size-3.5" />}
            </Button>
        </div>
    );
}

function formatRoas(value) {
    if (!value || value <= 0) return '—';
    return `${Number(value).toFixed(2)}x`;
}

export function MarketingCampaignTable({ rows = [], budgetUpdateUrl, canEditBudget = false }) {
    const t = useT();

    return (
        <ScrollDataTable>
            <table className="w-full min-w-[1200px] border-collapse text-xs">
                <thead>
                    <tr>
                        <Th>{t('reports.marketing_campaign_table.stt')}</Th>
                        <Th>{t('reports.marketing_campaign_table.campaign_name')}</Th>
                        <Th>{t('reports.marketing_campaign_table.marketer')}</Th>
                        <Th>{t('reports.marketing_campaign_table.creator')}</Th>
                        <Th className="text-right">{t('reports.marketing_campaign_table.leads')}</Th>
                        <Th className="text-right">{t('reports.marketing_campaign_table.junk_rate')}</Th>
                        <Th>{t('reports.marketing_campaign_table.ad_cost')}</Th>
                        <Th className="text-right">{t('reports.marketing_campaign_table.revenue')}</Th>
                        <Th className="text-right">{t('reports.marketing_campaign_table.roas')}</Th>
                        <Th className="text-right">{t('reports.marketing_campaign_table.net_contribution')}</Th>
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
                            <Td>{row.creatorName ?? '—'}</Td>
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
                            <Td className="text-right tabular-nums">{formatRoas(row.roas)}</Td>
                            <Td className="text-right tabular-nums">{formatCurrency(row.netContribution ?? 0)}</Td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </ScrollDataTable>
    );
}
