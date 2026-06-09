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

function issueType(row) {
    if (row.is_cod_mismatch) return 'cod_mismatch';
    if (!row.order_code) return 'unmatched';
    return 'matched';
}

function issueLabel(row) {
    if (row.is_cod_mismatch) return 'Lệch COD';
    if (!row.order_code) return 'Không map đơn';
    return 'Khớp đơn';
}

export default function ShippingReconciliation({ stats, issues }) {
    return (
        <AppLayout>
            <Head title="Đối soát vận chuyển" />

            <div className="space-y-6">
                <PageHeader
                    title="Đối soát vận chuyển"
                    description="Theo dõi callback từ hãng giao hàng và phát hiện lệch COD/không map được đơn."
                />

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        icon={BadgeCheck}
                        title="Callback hôm nay"
                        value={stats.callbacks_today}
                        hint="Tổng webhook nhận từ hãng vận chuyển"
                    />
                    <StatCard
                        icon={Wallet}
                        title="Match đơn thành công"
                        value={stats.matched_today}
                        hint="Tìm được order từ tracking/order code"
                    />
                    <StatCard
                        icon={Link2Off}
                        title="Không map được đơn"
                        value={stats.unmatched_today}
                        hint="Cần kiểm tra mapping mã đơn của đối tác"
                    />
                    <StatCard
                        icon={AlertTriangle}
                        title="Lệch COD"
                        value={stats.cod_mismatch_today}
                        hint="COD đối tác khác số hệ thống (> 500đ)"
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Danh sách vấn đề cần xử lý</CardTitle>
                        <CardDescription>
                            Ưu tiên các dòng lệch COD và callback không tìm được order
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ScrollDataTable>
                            <table className="w-full border-collapse text-xs">
                                <thead>
                                    <tr>
                                        <Th>ID</Th>
                                        <Th>Thời gian</Th>
                                        <Th>Đối tác</Th>
                                        <Th>Tracking</Th>
                                        <Th>Mã đơn đối tác</Th>
                                        <Th>Mã đơn hệ thống</Th>
                                        <Th>Trạng thái giao</Th>
                                        <Th>Loại vấn đề</Th>
                                        <Th>COD đối tác</Th>
                                        <Th>COD hệ thống</Th>
                                        <Th>Ghi chú</Th>
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
                                                            {deliveryLabel(row.mapped_status)}
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
                                                Chưa phát hiện bất thường vận chuyển
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
