import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    AlertTriangle,
    BadgeCheck,
    Link2Off,
    PackageX,
    Truck,
    Wallet,
} from 'lucide-react';

import { StatCard } from '@/components/charts/StatCard';
import { PageHeader } from '@/components/layout/PageHeader';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { StatusBadge } from '@/components/ui/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { deliveryLabel, deliveryTone } from '@/lib/status-tones';
import { formatCurrency } from '@/lib/format';
import AppLayout from '@/layouts/AppLayout';
import { useLabels } from '@/hooks/use-labels';
import { useTableSort } from '@/hooks/use-table-sort';
import { useT } from '@/providers/I18nProvider';
import { cn } from '@/lib/utils';

const SELECT_CLASS = 'h-9 rounded-md border border-input bg-background px-2 text-sm';

function moneyStateTone(state) {
    return (
        {
            paid: 'success',
            pending: 'warning',
            returned: 'danger',
            transit: 'info',
            cancelled: 'muted',
            mismatch: 'danger',
        }[state] ?? 'muted'
    );
}

function reconTone(status) {
    return (
        {
            settled: 'success',
            reconciled: 'success',
            short_paid: 'warning',
            over_paid: 'danger',
            mismatch: 'danger',
            missing_settlement: 'warning',
            returned: 'orange',
            in_transit: 'info',
            pending: 'muted',
        }[status] ?? 'muted'
    );
}

const VIEW_TABS = ['overview', 'short_paid', 'mismatch', 'missing', 'returned', 'settled', 'unmatched'];

function webhookIssueType(row) {
    if (row.is_cod_mismatch) return 'cod_mismatch';
    if (!row.order_code) return 'unmatched';
    return 'matched';
}

export default function ShippingReconciliation({
    summary: summaryProp,
    stats: statsProp,
    webhookStats,
    webhookIssues = [],
    statusBreakdown,
    returnsByProduct,
    unmatchedSettlements = [],
    orders,
    filters,
    range,
    providerOptions,
    syncProviders = [],
    yearOptions,
}) {
    const t = useT();
    const labels = useLabels();
    const summary = {
        total_orders: 0,
        cod_total: 0,
        cod_paid: 0,
        paid_orders: 0,
        cod_pending: 0,
        pending_orders: 0,
        cod_transit: 0,
        transit_orders: 0,
        returned_orders: 0,
        returned_value: 0,
        cod_mismatch_orders: 0,
        reconciled_orders: 0,
        cod_settled: 0,
        short_paid_orders: 0,
        missing_settlement_orders: 0,
        unmatched_settlement_lines: 0,
        ...summaryProp,
    };
    const webhookStatsSafe = webhookStats ?? statsProp ?? {
        callbacks_today: 0,
        matched_today: 0,
        unmatched_today: 0,
        cod_mismatch_today: 0,
    };
    const { sortedRows: sortedWebhookIssues, sort: webhookSort, toggleSort: toggleWebhookSort } = useTableSort(
        webhookIssues,
        {
            defaultKey: 'received_at',
            defaultDir: 'desc',
            accessors: { issue_type: (row) => webhookIssueType(row) },
        },
    );
    const [form, setForm] = useState(filters);

    const [importing, setImporting] = useState(false);

    const rows = orders?.data ?? [];
    const meta = orders?.meta ?? { current_page: 1, last_page: 1, total: 0 };
    const { sortedRows, sort, toggleSort } = useTableSort(rows, {
        defaultKey: 'closed_at',
        defaultDir: 'desc',
    });

    const apply = (overrides = {}) => {
        const next = { ...form, ...overrides, page: undefined };
        setForm(next);
        router.get('/admin/shipping/reconciliation', next, { preserveState: true, preserveScroll: true });
    };

    const goPage = (page) => {
        router.get(
            '/admin/shipping/reconciliation',
            { ...filters, page },
            { preserveState: true, preserveScroll: true },
        );
    };

    const onImportCsv = (e) => {
        const file = e.target.files?.[0];
        if (!file || !form.provider) return;
        setImporting(true);
        router.post(
            '/admin/shipping/reconciliation/import',
            { provider: form.provider, file, period_from: range.from, period_to: range.to },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => {
                    setImporting(false);
                    e.target.value = '';
                },
                onSuccess: () => apply(),
            },
        );
    };

    const onSyncApi = () => {
        if (!form.provider || !syncProviders.includes(form.provider)) return;
        router.post(
            '/admin/shipping/reconciliation/sync',
            { provider: form.provider, ...form },
            { preserveScroll: true, onSuccess: () => apply() },
        );
    };

    const periodTabs = ['month', 'quarter', 'year', 'custom'];
    const activeTab = form.tab ?? 'overview';

    const webhookIssueLabel = (row) => {
        const type = webhookIssueType(row);
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
                        value={webhookStatsSafe.callbacks_today}
                        hint={t('shipping.reconciliation.callback_hint')}
                    />
                    <StatCard
                        icon={Wallet}
                        title={t('shipping.reconciliation.matched')}
                        value={webhookStatsSafe.matched_today}
                        hint={t('shipping.reconciliation.matched_hint')}
                    />
                    <StatCard
                        icon={Link2Off}
                        title={t('shipping.reconciliation.unmatched')}
                        value={webhookStatsSafe.unmatched_today}
                        hint={t('shipping.reconciliation.unmatched_hint')}
                    />
                    <StatCard
                        icon={AlertTriangle}
                        title={t('shipping.reconciliation.cod_mismatch')}
                        value={webhookStatsSafe.cod_mismatch_today}
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
                            <table className="w-full min-w-[1100px] border-collapse text-xs">
                                <thead>
                                    <tr>
                                        <Th sortable sortKey="id" sort={webhookSort} onSort={toggleWebhookSort}>
                                            {t('shipping.reconciliation.col_id')}
                                        </Th>
                                        <Th sortable sortKey="received_at" sort={webhookSort} onSort={toggleWebhookSort}>
                                            {t('shipping.reconciliation.col_time')}
                                        </Th>
                                        <Th>{t('shipping.reconciliation.col_partner')}</Th>
                                        <Th>{t('shipping.reconciliation.col_tracking')}</Th>
                                        <Th>{t('shipping.reconciliation.col_partner_order')}</Th>
                                        <Th>{t('shipping.reconciliation.col_system_order')}</Th>
                                        <Th>{t('shipping.reconciliation.col_delivery')}</Th>
                                        <Th sortable sortKey="issue_type" sort={webhookSort} onSort={toggleWebhookSort}>
                                            {t('shipping.reconciliation.col_issue_type')}
                                        </Th>
                                        <Th className="text-right">{t('shipping.reconciliation.col_partner_cod')}</Th>
                                        <Th className="text-right">{t('shipping.reconciliation.col_system_cod')}</Th>
                                        <Th>{t('shipping.reconciliation.col_note')}</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {sortedWebhookIssues.length ? (
                                        sortedWebhookIssues.map((row) => (
                                            <tr key={row.id} className="border-t hover:bg-muted/30">
                                                <Td>{row.id}</Td>
                                                <Td className="whitespace-nowrap">{row.received_at}</Td>
                                                <Td>{row.provider}</Td>
                                                <Td>{row.tracking_number ?? '—'}</Td>
                                                <Td>{row.partner_order_code ?? '—'}</Td>
                                                <Td>{row.order_code ?? '—'}</Td>
                                                <Td>
                                                    {row.delivery_status
                                                        ? labels.delivery(row.delivery_status)
                                                        : '—'}
                                                </Td>
                                                <Td>
                                                    <StatusBadge tone={row.is_cod_mismatch ? 'danger' : row.order_code ? 'success' : 'warning'}>
                                                        {webhookIssueLabel(row)}
                                                    </StatusBadge>
                                                </Td>
                                                <Td className="text-right tabular-nums">
                                                    {row.partner_cod != null ? formatCurrency(row.partner_cod) : '—'}
                                                </Td>
                                                <Td className="text-right tabular-nums">
                                                    {row.system_cod != null ? formatCurrency(row.system_cod) : '—'}
                                                </Td>
                                                <Td className="max-w-[200px] truncate">{row.note ?? '—'}</Td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <Td colSpan={11} className="py-8 text-center text-muted-foreground">
                                                {t('shipping.reconciliation.webhook_empty')}
                                            </Td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </ScrollDataTable>
                    </CardContent>
                </Card>

                {/* Bộ lọc kỳ đối soát */}
                <Card>
                    <CardContent className="flex flex-col gap-4 pt-6">
                        <div className="flex flex-wrap items-center gap-3">
                            <div className="flex rounded-lg border bg-muted/40 p-1">
                                {periodTabs.map((id) => (
                                    <button
                                        key={id}
                                        type="button"
                                        onClick={() => apply({ period_type: id })}
                                        className={cn(
                                            'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                            form.period_type === id
                                                ? 'bg-background text-foreground shadow-sm'
                                                : 'text-muted-foreground hover:text-foreground',
                                        )}
                                    >
                                        {t(`shipping.reconciliation.period_${id}`)}
                                    </button>
                                ))}
                            </div>

                            {form.period_type === 'month' && (
                                <input
                                    type="month"
                                    className={SELECT_CLASS}
                                    value={form.month ?? ''}
                                    onChange={(e) => apply({ month: e.target.value })}
                                />
                            )}

                            {form.period_type === 'quarter' && (
                                <>
                                    <select
                                        className={SELECT_CLASS}
                                        value={form.quarter ?? ''}
                                        onChange={(e) => apply({ quarter: e.target.value })}
                                    >
                                        {[1, 2, 3, 4].map((q) => (
                                            <option key={q} value={q}>
                                                {t('shipping.reconciliation.quarter_n', { n: q })}
                                            </option>
                                        ))}
                                    </select>
                                    <select
                                        className={SELECT_CLASS}
                                        value={form.year ?? ''}
                                        onChange={(e) => apply({ year: e.target.value })}
                                    >
                                        {yearOptions.map((y) => (
                                            <option key={y} value={y}>{y}</option>
                                        ))}
                                    </select>
                                </>
                            )}

                            {form.period_type === 'year' && (
                                <select
                                    className={SELECT_CLASS}
                                    value={form.year ?? ''}
                                    onChange={(e) => apply({ year: e.target.value })}
                                >
                                    {yearOptions.map((y) => (
                                        <option key={y} value={y}>{y}</option>
                                    ))}
                                </select>
                            )}

                            {form.period_type === 'custom' && (
                                <>
                                    <input
                                        type="date"
                                        className={SELECT_CLASS}
                                        value={form.date_from ?? ''}
                                        onChange={(e) => setForm({ ...form, date_from: e.target.value })}
                                    />
                                    <span className="text-muted-foreground">—</span>
                                    <input
                                        type="date"
                                        className={SELECT_CLASS}
                                        value={form.date_to ?? ''}
                                        onChange={(e) => setForm({ ...form, date_to: e.target.value })}
                                    />
                                    <Button size="sm" onClick={() => apply()}>
                                        {t('shipping.reconciliation.apply')}
                                    </Button>
                                </>
                            )}

                            <span className="ml-auto text-xs text-muted-foreground">
                                {t('shipping.reconciliation.range_label', { from: range.from, to: range.to })}
                            </span>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <select
                                className={SELECT_CLASS}
                                value={form.provider ?? ''}
                                onChange={(e) => apply({ provider: e.target.value || undefined })}
                            >
                                <option value="">{t('shipping.reconciliation.all_carriers')}</option>
                                {providerOptions.map((p) => (
                                    <option key={p.value} value={p.value}>{p.label}</option>
                                ))}
                            </select>
                            <select
                                className={SELECT_CLASS}
                                value={form.recon_status ?? ''}
                                onChange={(e) => apply({ recon_status: e.target.value || undefined })}
                            >
                                <option value="">{t('shipping.reconciliation.all_recon')}</option>
                                <option value="pending">{t('shipping.reconciliation.recon_pending')}</option>
                                <option value="reconciled">{t('shipping.reconciliation.recon_reconciled')}</option>
                                <option value="mismatch">{t('shipping.reconciliation.recon_mismatch')}</option>
                            </select>
                            <Input
                                className="h-9 w-56"
                                placeholder={t('shipping.reconciliation.search_placeholder')}
                                value={form.search ?? ''}
                                onChange={(e) => setForm({ ...form, search: e.target.value })}
                                onKeyDown={(e) => e.key === 'Enter' && apply()}
                            />
                            <Button size="sm" variant="outline" onClick={() => apply()}>
                                {t('shipping.reconciliation.apply')}
                            </Button>
                            <label className="cursor-pointer">
                                <Button size="sm" variant="outline" type="button" disabled={!form.provider || importing} asChild>
                                    <span>{importing ? '...' : t('shipping.reconciliation.import_csv')}</span>
                                </Button>
                                <input type="file" accept=".csv,.txt" className="hidden" onChange={onImportCsv} />
                            </label>
                            {form.provider && syncProviders.includes(form.provider) && (
                                <Button size="sm" variant="secondary" onClick={onSyncApi}>
                                    {t('shipping.reconciliation.sync_api')}
                                </Button>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <div className="flex flex-wrap gap-1 rounded-lg border bg-muted/40 p-1">
                    {VIEW_TABS.map((id) => (
                        <button
                            key={id}
                            type="button"
                            onClick={() => apply({ tab: id })}
                            className={cn(
                                'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                activeTab === id
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {t(`shipping.reconciliation.tab_${id}`)}
                        </button>
                    ))}
                </div>

                {/* Thẻ tổng quan dòng tiền */}
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        icon={Wallet}
                        title={t('shipping.reconciliation.card_total')}
                        value={summary.total_orders.toLocaleString()}
                        hint={t('shipping.reconciliation.card_total_hint', {
                            amount: formatCurrency(summary.cod_total),
                        })}
                    />
                    <StatCard
                        icon={BadgeCheck}
                        title={t('shipping.reconciliation.card_paid')}
                        value={formatCurrency(summary.cod_paid)}
                        hint={t('shipping.reconciliation.card_paid_hint', { count: summary.paid_orders })}
                    />
                    <StatCard
                        icon={AlertTriangle}
                        title={t('shipping.reconciliation.card_pending')}
                        value={formatCurrency(summary.cod_pending)}
                        hint={t('shipping.reconciliation.card_pending_hint', { count: summary.pending_orders })}
                    />
                    <StatCard
                        icon={PackageX}
                        title={t('shipping.reconciliation.card_returned')}
                        value={summary.returned_orders.toLocaleString()}
                        hint={t('shipping.reconciliation.card_returned_hint', {
                            amount: formatCurrency(summary.returned_value),
                        })}
                    />
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <StatCard
                        icon={Truck}
                        title={t('shipping.reconciliation.card_transit')}
                        value={summary.transit_orders.toLocaleString()}
                        hint={formatCurrency(summary.cod_transit)}
                    />
                    <StatCard
                        icon={BadgeCheck}
                        title={t('shipping.reconciliation.card_settled_amount')}
                        value={formatCurrency(summary.cod_settled ?? 0)}
                        hint={t('shipping.reconciliation.card_reconciled_hint')}
                    />
                    <StatCard
                        icon={AlertTriangle}
                        title={t('shipping.reconciliation.card_missing')}
                        value={(summary.missing_settlement_orders ?? 0).toLocaleString()}
                        hint={t('shipping.reconciliation.card_pending_hint', { count: summary.short_paid_orders ?? 0 })}
                    />
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <StatCard
                        icon={AlertTriangle}
                        title={t('shipping.reconciliation.card_mismatch')}
                        value={summary.cod_mismatch_orders.toLocaleString()}
                        hint={t('shipping.reconciliation.card_mismatch_hint')}
                    />
                    <StatCard
                        icon={BadgeCheck}
                        title={t('shipping.reconciliation.card_reconciled')}
                        value={summary.reconciled_orders.toLocaleString()}
                        hint={t('shipping.reconciliation.card_reconciled_hint')}
                    />
                    <StatCard
                        icon={PackageX}
                        title={t('shipping.reconciliation.card_unmatched_lines')}
                        value={(summary.unmatched_settlement_lines ?? 0).toLocaleString()}
                        hint={t('shipping.reconciliation.unmatched_desc')}
                    />
                </div>

                {/* Phân rã trạng thái + hoàn theo sản phẩm */}
                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('shipping.reconciliation.breakdown_title')}</CardTitle>
                            <CardDescription>{t('shipping.reconciliation.breakdown_desc')}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-xs text-muted-foreground">
                                        <th className="py-2 text-left font-medium">{t('shipping.reconciliation.col_delivery')}</th>
                                        <th className="py-2 text-right font-medium">{t('shipping.reconciliation.col_orders')}</th>
                                        <th className="py-2 text-right font-medium">{t('shipping.reconciliation.col_cod')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {statusBreakdown.length ? (
                                        statusBreakdown.map((r) => (
                                            <tr key={r.delivery_status} className="border-b border-border/50">
                                                <td className="py-2">
                                                    <StatusBadge tone={deliveryTone(r.delivery_status)}>
                                                        {deliveryLabel(r.delivery_status, labels)}
                                                    </StatusBadge>
                                                </td>
                                                <td className="py-2 text-right tabular-nums">{r.orders.toLocaleString()}</td>
                                                <td className="py-2 text-right tabular-nums">{formatCurrency(r.cod)}</td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={3} className="py-6 text-center text-muted-foreground">
                                                {t('shipping.reconciliation.empty')}
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('shipping.reconciliation.returns_title')}</CardTitle>
                            <CardDescription>{t('shipping.reconciliation.returns_desc')}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-xs text-muted-foreground">
                                        <th className="py-2 text-left font-medium">{t('shipping.reconciliation.col_product')}</th>
                                        <th className="py-2 text-right font-medium">{t('shipping.reconciliation.col_orders')}</th>
                                        <th className="py-2 text-right font-medium">{t('shipping.reconciliation.col_value')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {returnsByProduct.length ? (
                                        returnsByProduct.map((r, i) => (
                                            <tr key={r.product_id ?? `na-${i}`} className="border-b border-border/50">
                                                <td className="py-2">{r.product_name}</td>
                                                <td className="py-2 text-right tabular-nums">{r.orders.toLocaleString()}</td>
                                                <td className="py-2 text-right tabular-nums">{formatCurrency(r.value)}</td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={3} className="py-6 text-center text-muted-foreground">
                                                {t('shipping.reconciliation.no_returns')}
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                </div>

                {(activeTab === 'unmatched' || activeTab === 'overview') && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('shipping.reconciliation.unmatched_title')}</CardTitle>
                            <CardDescription>{t('shipping.reconciliation.unmatched_desc')}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-xs text-muted-foreground">
                                        <th className="py-2 text-left">{t('shipping.reconciliation.col_carrier')}</th>
                                        <th className="py-2 text-left">{t('shipping.reconciliation.col_tracking')}</th>
                                        <th className="py-2 text-left">{t('shipping.reconciliation.col_order')}</th>
                                        <th className="py-2 text-right">{t('shipping.reconciliation.col_cod')}</th>
                                        <th className="py-2 text-left">{t('shipping.reconciliation.col_closed_at')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {unmatchedSettlements.length ? (
                                        unmatchedSettlements.map((line) => (
                                            <tr key={line.id} className="border-b border-border/50">
                                                <td className="py-2">{line.provider}</td>
                                                <td className="py-2 font-mono">{line.tracking_number ?? '—'}</td>
                                                <td className="py-2 font-mono">{line.partner_order_code ?? '—'}</td>
                                                <td className="py-2 text-right tabular-nums">{formatCurrency(line.cod_amount)}</td>
                                                <td className="py-2">{line.settled_at ?? '—'}</td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={5} className="py-6 text-center text-muted-foreground">
                                                {t('shipping.reconciliation.empty')}
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                )}

                {/* Danh sách đơn đối soát */}
                {activeTab !== 'unmatched' && (
                <Card>
                    <CardHeader>
                        <CardTitle>{t('shipping.reconciliation.orders_title')}</CardTitle>
                        <CardDescription>
                            {t('shipping.reconciliation.orders_total', { total: meta.total.toLocaleString() })}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ScrollDataTable>
                            <table className="w-full border-collapse text-xs">
                                <thead>
                                    <tr>
                                        <Th sortable sortKey="order_code" sort={sort} onSort={toggleSort}>{t('shipping.reconciliation.col_order')}</Th>
                                        <Th sortable sortKey="provider_label" sort={sort} onSort={toggleSort}>{t('shipping.reconciliation.col_carrier')}</Th>
                                        <Th sortable sortKey="tracking_number" sort={sort} onSort={toggleSort}>{t('shipping.reconciliation.col_tracking')}</Th>
                                        <Th sortable sortKey="product_name" sort={sort} onSort={toggleSort}>{t('shipping.reconciliation.col_product')}</Th>
                                        <Th sortable sortKey="closed_at" sort={sort} onSort={toggleSort}>{t('shipping.reconciliation.col_closed_at')}</Th>
                                        <Th sortable sortKey="delivery_status" sort={sort} onSort={toggleSort}>{t('shipping.reconciliation.col_delivery')}</Th>
                                        <Th sortable sortKey="reconciliation_status" sort={sort} onSort={toggleSort}>{t('shipping.reconciliation.col_recon')}</Th>
                                        <Th sortable sortKey="cod_to_collect" sort={sort} onSort={toggleSort}>{t('shipping.reconciliation.col_system_cod')}</Th>
                                        <Th sortable sortKey="settled_cod" sort={sort} onSort={toggleSort}>{t('shipping.reconciliation.col_settled_cod')}</Th>
                                        <Th sortable sortKey="partner_cod" sort={sort} onSort={toggleSort}>{t('shipping.reconciliation.col_partner_cod')}</Th>
                                        <Th sortable sortKey="money_state" sort={sort} onSort={toggleSort}>{t('shipping.reconciliation.col_money_state')}</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {sortedRows.length ? (
                                        sortedRows.map((row) => (
                                            <tr key={row.id} className="border-b border-border/60 hover:bg-muted/30">
                                                <Td className="font-mono">{row.order_code}</Td>
                                                <Td>{row.provider_label}</Td>
                                                <Td className="font-mono">{row.tracking_number ?? '—'}</Td>
                                                <Td className="max-w-[12rem] truncate">{row.product_name}</Td>
                                                <Td>{row.closed_at ?? '—'}</Td>
                                                <Td>
                                                    <StatusBadge tone={deliveryTone(row.delivery_status)}>
                                                        {deliveryLabel(row.delivery_status, labels)}
                                                    </StatusBadge>
                                                </Td>
                                                <Td>
                                                    <StatusBadge tone={reconTone(row.reconciliation_status)}>
                                                        {row.reconciliation_label ?? row.reconciliation_status}
                                                    </StatusBadge>
                                                </Td>
                                                <Td className="tabular-nums">{formatCurrency(row.cod_to_collect)}</Td>
                                                <Td className="tabular-nums">{formatCurrency(row.settled_cod ?? 0)}</Td>
                                                <Td className="tabular-nums">
                                                    {row.partner_cod != null ? (
                                                        <span className={cn(row.cod_gap ? 'text-destructive font-medium' : '')}>
                                                            {formatCurrency(row.partner_cod)}
                                                        </span>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            {t('shipping.reconciliation.no_callback')}
                                                        </span>
                                                    )}
                                                </Td>
                                                <Td>
                                                    <StatusBadge tone={moneyStateTone(row.money_state)}>
                                                        {t(`shipping.reconciliation.money_${row.money_state}`)}
                                                    </StatusBadge>
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

                        {meta.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={meta.current_page <= 1}
                                    onClick={() => goPage(meta.current_page - 1)}
                                >
                                    {t('shipping.reconciliation.prev')}
                                </Button>
                                <span className="text-xs text-muted-foreground">
                                    {t('shipping.reconciliation.page_of', {
                                        current: meta.current_page,
                                        last: meta.last_page,
                                    })}
                                </span>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={meta.current_page >= meta.last_page}
                                    onClick={() => goPage(meta.current_page + 1)}
                                >
                                    {t('shipping.reconciliation.next')}
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>
                )}
            </div>
        </AppLayout>
    );
}
