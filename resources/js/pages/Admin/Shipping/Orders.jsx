import { useState } from 'react';
import { Head } from '@inertiajs/react';
import { PackageSearch } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { ShippingOrderDetailModal } from '@/components/shipping/ShippingOrderDetailModal';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { formatCurrency } from '@/lib/format';
import { cn } from '@/lib/utils';

export default function ShippingOrders({ filters, filterOptions, orders, pageTitle, routeUrl }) {
    const [selectedId, setSelectedId] = useState(null);
    const apiBase = routeUrl?.replace(/\/$/, '') ?? '/admin/shipping/orders';

    return (
        <AppLayout>
            <Head title={pageTitle ?? 'Đơn vận chuyển'} />

            <div className="space-y-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">{pageTitle ?? 'Đơn vận chuyển'}</h1>
                    <p className="text-sm text-muted-foreground">
                        Đơn đã chốt — tạo vận đơn GHTK, đồng bộ trạng thái và in nhãn
                    </p>
                </div>

                <ReportFilterBar routeUrl={routeUrl} filters={filters} filterOptions={filterOptions} />

                <ScrollDataTable>
                    <table className="min-w-[1100px] w-full border-collapse text-sm">
                        <thead>
                            <tr>
                                <Th>Mã đơn</Th>
                                <Th>ĐVVC</Th>
                                <Th>Khách / SĐT</Th>
                                <Th>Sale</Th>
                                <Th>Chốt lúc</Th>
                                <Th>Trạng thái giao</Th>
                                <Th>Vận đơn GHTK</Th>
                                <Th>Phí VC</Th>
                                <Th />
                            </tr>
                        </thead>
                        <tbody>
                            {orders?.data?.length ? (
                                orders.data.map((row) => (
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
                                        <Td>{row.deliveryStatus}</Td>
                                        <Td>
                                            <div className="font-mono text-xs">
                                                {row.trackingNumber ?? '—'}
                                            </div>
                                            <div
                                                className={cn(
                                                    'text-xs',
                                                    row.shipmentState === 'failed' && 'text-destructive',
                                                    row.shipmentState === 'submitted' && 'text-emerald-600'
                                                )}
                                            >
                                                {row.shipmentStatus ?? row.shipmentState ?? 'Chưa tạo'}
                                            </div>
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
                                                Chi tiết
                                            </button>
                                        </Td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <Td colSpan={9} className="py-10 text-center text-muted-foreground">
                                        Chưa có đơn đã chốt trong bộ lọc hiện tại
                                    </Td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </ScrollDataTable>
            </div>

            <ShippingOrderDetailModal
                open={!!selectedId}
                onOpenChange={(open) => !open && setSelectedId(null)}
                orderId={selectedId}
                apiBase={apiBase}
            />
        </AppLayout>
    );
}
