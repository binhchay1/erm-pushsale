import { Head } from '@inertiajs/react';
import { AlertTriangle, BadgeCheck, Link2Off, Wallet } from 'lucide-react';

import { StatCard } from '@/components/charts/StatCard';
import { PageHeader } from '@/components/layout/PageHeader';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { StatusBadge } from '@/components/ui/status-badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    deliveryLabel,
    deliveryTone,
    reconciliationIssueTone,
} from '@/lib/status-tones';
import { formatCurrency } from '@/lib/format';
import AppLayout from '@/layouts/AppLayout';
import { useLabels } from '@/hooks/use-labels';
import { useT } from '@/providers/I18nProvider';

function issueType(row) {
    if (row.is_cod_mismatch) return 'cod_mismatch';
    if (!row.order_code) return 'unmatched';
    return 'matched';
}

export default function ShippingReconciliation({ stats, issues }) {
    const t = useT();
    const labels = useLabels();

    const issueLabel = (row) => {
        const type = issueType(row);
        if (type === 'cod_mismatch') return t('shipping.reconciliation.issue_cod');
        if (type === 'unmatched') return t('shipping.reconciliation.issue_unmatched');
        return t('shipping.reconciliation.issue_matched');
    };

    return (
        <AppLayout>
            <Head title={t('shipping.reconciliation_title')} />

            <div className="space-y-6">
                <PageHeader
                    title={t('shipping.reconciliation_title')}
                    description={t('shipping.reconciliation.desc')}
                />

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        icon={BadgeCheck}
                        title={t('shipping.reconciliation.callback_today')}
                        value={stats.callbacks_today}
                        hint={t('shipping.reconciliation.callback_hint')}
                    />
                    <StatCard
                        icon={Wallet}
                        title={t('shipping.reconciliation.matched')}
                        value={stats.matched_today}
                        hint={t('shipping.reconciliation.matched_hint')}
                    />
                    <StatCard
                        icon={Link2Off}
                        title={t('shipping.reconciliation.unmatched')}
                        value={stats.unmatched_today}
                        hint={t('shipping.reconciliation.unmatched_hint')}
                    />
                    <StatCard
                        icon={AlertTriangle}
                        title={t('shipping.reconciliation.cod_mismatch')}
                        value={stats.cod_mismatch_today}
                        hint={t('shipping.reconciliation.cod_mismatch_hint')}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('shipping.reconciliation.issues_title')}</CardTitle>
                        <CardDescription>{t('shipping.reconciliation.issues_desc')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ScrollDataTable>
                            <table className="w-full border-collapse text-xs">
                                <thead>
                                    <tr>
                                        <Th>{t('shipping.reconciliation.col_id')}</Th>
                                        <Th>{t('shipping.reconciliation.col_time')}</Th>
                                        <Th>{t('shipping.reconciliation.col_partner')}</Th>
                                        <Th>{t('shipping.reconciliation.col_tracking')}</Th>
                                        <Th>{t('shipping.reconciliation.col_partner_order')}</Th>
                                        <Th>{t('shipping.reconciliation.col_system_order')}</Th>
                                        <Th>{t('shipping.reconciliation.col_delivery')}</Th>
                                        <Th>{t('shipping.reconciliation.col_issue_type')}</Th>
                                        <Th>{t('shipping.reconciliation.col_partner_cod')}</Th>
                                        <Th>{t('shipping.reconciliation.col_system_cod')}</Th>
                                        <Th>{t('shipping.reconciliation.col_note')}</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {issues.length ? (
                                        issues.map((row) => (
                                            <tr key={row.id} className="hover:bg-muted/30">
                                                <Td>{row.id}</Td>
                                                <Td>{row.received_at ?? '—'}</Td>
                                                <Td>{row.provider}</Td>
                                                <Td className="font-mono">{row.tracking_number ?? '—'}</Td>
                                                <Td className="font-mono">{row.partner_order_code ?? '—'}</Td>
                                                <Td className="font-mono">{row.order_code ?? '—'}</Td>
                                                <Td>
                                                    {row.mapped_status ? (
                                                        <StatusBadge tone={deliveryTone(row.mapped_status)}>
                                                            {deliveryLabel(row.mapped_status, labels)}
                                                        </StatusBadge>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            {row.raw_status ?? '—'}
                                                        </span>
                                                    )}
                                                </Td>
                                                <Td>
                                                    <StatusBadge tone={reconciliationIssueTone(issueType(row))}>
                                                        {issueLabel(row)}
                                                    </StatusBadge>
                                                </Td>
                                                <Td className="tabular-nums">
                                                    {row.partner_cod != null ? formatCurrency(row.partner_cod) : '—'}
                                                </Td>
                                                <Td className="tabular-nums">
                                                    {row.system_cod != null ? formatCurrency(row.system_cod) : '—'}
                                                </Td>
                                                <Td className="max-w-xs whitespace-normal text-muted-foreground">
                                                    {row.note ?? row.result ?? '—'}
                                                </Td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <Td colSpan={11} className="py-8 text-center text-muted-foreground">
                                                {t('shipping.reconciliation.empty')}
                                            </Td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </ScrollDataTable>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
