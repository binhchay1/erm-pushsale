import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { formatCurrency } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

function formatRoas(value) {
    if (!value || value <= 0) return '—';
    return `${Number(value).toFixed(2)}x`;
}

export function MarketingKpiTable({ rows = [], nameKey = 'marketerName', nameLabel }) {
    const t = useT();

    if (!rows.some((row) => row.attributedRevenue !== undefined)) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>{t('reports.marketing_kpi.title')}</CardTitle>
                <CardDescription>
                    {t('reports.marketing_kpi.attributed_revenue')} · {t('reports.marketing_kpi.net_contribution')}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ScrollDataTable>
                    <table className="w-full min-w-[720px] border-collapse text-xs">
                        <thead>
                            <tr>
                                <Th>{nameLabel}</Th>
                                <Th className="text-right">{t('reports.marketing_kpi.attributed_revenue')}</Th>
                                <Th className="text-right">{t('reports.marketing_kpi.ad_spend')}</Th>
                                <Th className="text-right">{t('reports.marketing_kpi.roas')}</Th>
                                <Th className="text-right">{t('reports.marketing_kpi.net_contribution')}</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.marketerId ?? row.stt} className={row.isTotalRow ? 'bg-muted/50 font-semibold' : 'hover:bg-muted/30'}>
                                    <Td className="font-medium">{row[nameKey]}</Td>
                                    <Td className="text-right tabular-nums">{formatCurrency(row.attributedRevenue ?? 0)}</Td>
                                    <Td className="text-right tabular-nums">{formatCurrency(row.adSpend ?? 0)}</Td>
                                    <Td className="text-right tabular-nums">{formatRoas(row.roas)}</Td>
                                    <Td className="text-right tabular-nums">{formatCurrency(row.netContribution ?? 0)}</Td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </ScrollDataTable>
            </CardContent>
        </Card>
    );
}
