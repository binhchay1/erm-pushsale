import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';

import { DeleteRowButton } from '@/components/ui/delete-row-button';
import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';

export default function FailedOrders({ report, filterOptions }) {
    const f = report.filters ?? {};
    const rows = report.rows?.data ?? [];

    const search = (overrides) => {
        router.get('/admin/orders/failed', { ...f, ...overrides }, { preserveState: true });
    };

    return (
        <AppLayout>
            <Head title="Đơn hàng lỗi" />

            <div className="space-y-6">
                <h1 className="text-2xl font-bold tracking-tight">Danh sách đơn hàng lỗi</h1>

                <div className="flex flex-wrap gap-3 rounded-xl border bg-card p-4">
                    <select
                        className="h-8 rounded-lg border px-2 text-sm"
                        value={f.platform ?? ''}
                        onChange={(e) => search({ platform: e.target.value || null })}
                    >
                        <option value="">— Nền tảng —</option>
                        {report.platforms?.map((p) => (
                            <option key={p} value={p}>
                                {p}
                            </option>
                        ))}
                    </select>
                    <select
                        className="h-8 rounded-lg border px-2 text-sm"
                        value={f.warehouse_id ?? ''}
                        onChange={(e) => search({ warehouse_id: e.target.value || null })}
                    >
                        <option value="">— Kho —</option>
                        {filterOptions?.warehouses?.map((w) => (
                            <option key={w.id} value={w.id}>
                                {w.name}
                            </option>
                        ))}
                    </select>
                    <Input
                        placeholder="Mã đơn đối tác"
                        value={f.partner_order_id ?? ''}
                        onChange={(e) => search({ partner_order_id: e.target.value })}
                        className="max-w-xs"
                    />
                    <Button size="sm" onClick={() => search()}>
                        <Search className="size-4" />
                        Tìm kiếm
                    </Button>
                </div>

                <ScrollDataTable>
                    <table className="w-full border-collapse text-xs">
                        <thead>
                            <tr>
                                <Th>STT</Th>
                                <Th>Mã đơn đối tác</Th>
                                <Th>Nền tảng</Th>
                                <Th>Kho</Th>
                                <Th>Mô tả lỗi</Th>
                                <Th />
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length ? (
                                rows.map((r) => (
                                    <tr key={r.stt} className="hover:bg-muted/30">
                                        <Td>{r.stt}</Td>
                                        <Td className="font-mono">{r.partnerOrderId}</Td>
                                        <Td>{r.platform}</Td>
                                        <Td>{r.warehouseName ?? '—'}</Td>
                                        <Td className="whitespace-normal text-destructive">
                                            {r.errorDescription}
                                        </Td>
                                        <Td>
                                            <DeleteRowButton
                                                url={`/admin/failed-orders/${r.stt}`}
                                                label={r.partnerOrderId}
                                            />
                                        </Td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={6} className="px-3 py-8 text-center text-muted-foreground">
                                        Không có đơn lỗi
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </ScrollDataTable>
            </div>
        </AppLayout>
    );
}
