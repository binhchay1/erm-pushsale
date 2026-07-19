import { useState } from 'react';
import { Head } from '@inertiajs/react';
import { PackageSearch } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { ShippingOrderDetailDialog } from '@/components/shipping/ShippingOrderDetailDialog';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { StatusBadge } from '@/components/ui/status-badge';
import { useTableSort } from '@/hooks/use-table-sort';
import { deliveryTone, shipmentTone } from '@/lib/status-tones';
import { formatCurrency } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

export default function ShippingOrders({ filters, filterOptions, orders, pageTitle, routeUrl }) {
    const t = useT();
    const [selectedId, setSelectedId] = useState(null);
    const apiBase = routeUrl?.replace(/\/$/, '') ?? '/admin/shipping/orders';
    const title = pageTitle ?? t('shipping.orders_title');
    const { sortedRows, sort, toggleSort } = useTableSort(orders?.data ?? [], { defaultKey: 'closedAt', defaultDir: 'desc' });

    return (
        <AppLayout>
            <Head title={title} />

            <div className="ps-feature-page ps-shipping-orders-page">
                <PageHeader icon={PackageSearch} title={title} description={t('shipping.orders_desc')} />

                <ReportFilterBar routeUrl={routeUrl} filters={filters} filterOptions={filterOptions} />

                <ScrollDataTable>
                    <table className="min-w-[1100px] w-full border-collapse text-sm">
                        <thead>
                            <tr>
                                <Th sortable sortKey="orderCode" sort={sort} onSort={toggleSort}>{t('shipping.col_order')}</Th>
                                <Th sortable sortKey="shippingProviderLabel" sort={sort} onSort={toggleSort}>{t('shipping.col_carrier')}</Th>
                                <Th sortable sortKey="customerName" sort={sort} onSort={toggleSort}>{t('shipping.col_customer')}</Th>
                                <Th sortable sortKey="saleName" sort={sort} onSort={toggleSort}>{t('shipping.col_sale')}</Th>
                                <Th sortable sortKey="closedAt" sort={sort} onSort={toggleSort}>{t('shipping.col_closed_at')}</Th>
                                <Th sortable sortKey="deliveryStatus" sort={sort} onSort={toggleSort}>{t('shipping.col_delivery')}</Th>
                                <Th sortable sortKey="trackingNumber" sort={sort} onSort={toggleSort}>{t('shipping.col_waybill')}</Th>
                                <Th sortable sortKey="carrierFee" sort={sort} onSort={toggleSort}>{t('shipping.col_fee')}</Th>
                                <Th />
                            </tr>
                        </thead>
                        <tbody>
                            {sortedRows.length ? (
                                sortedRows.map((row) => (
                                    <tr key={row.id} className="align-top hover:bg-muted/30">
                                        <Td className="font-mono text-primary">{row.orderCode}</Td>
                                        <Td className="text-xs">{row.shippingProviderLabel ?? '—'}</Td>
                                        <Td>
                                            <div className="font-medium">{row.customerName}</div>
                                            <div className="text-muted-foreground">{row.customerPhone}</div>
                                        </Td>
                                        <Td>{row.saleName}</Td>
                                        <Td className="text-muted-foreground">
                                            {row.closedAt?.slice(0, 16)?.replace('T', ' ')}
                                        </Td>
                                        <Td>
                                            <StatusBadge tone={deliveryTone(row.deliveryStatusValue)}>
                                                {row.deliveryStatus}
                                            </StatusBadge>
                                        </Td>
                                        <Td>
                                            <div className="font-mono text-xs">
                                                {row.trackingNumber ?? '—'}
                                            </div>
                                            <StatusBadge
                                                tone={shipmentTone(row.shipmentState)}
                                                className="mt-1"
                                            >
                                                {row.shipmentStatus ?? row.shipmentState ?? t('shipping.not_created')}
                                            </StatusBadge>
                                            {row.shipmentError && (
                                                <p className="text-xs text-destructive">{row.shipmentError}</p>
                                            )}
                                        </Td>
                                        <Td className="tabular-nums">{formatCurrency(row.carrierFee)}</Td>
                                        <Td>
                                            <button
                                                type="button"
                                                onClick={() => setSelectedId(row.id)}
                                                className="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-medium hover:bg-muted"
                                            >
                                                <PackageSearch className="size-3.5" />
                                                {t('pages.detail')}
                                            </button>
                                        </Td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <Td colSpan={9} className="py-10 text-center text-muted-foreground">
                                        {t('shipping.empty_filtered')}
                                    </Td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </ScrollDataTable>
            </div>

            <ShippingOrderDetailDialog
                open={!!selectedId}
                onOpenChange={(open) => !open && setSelectedId(null)}
                orderId={selectedId}
                apiBase={apiBase}
            />
        </AppLayout>
    );
}
