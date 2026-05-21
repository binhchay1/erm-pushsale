import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { formatNumber } from '@/lib/format';

export default function Inventory({ report, filterOptions }) {
    const f = report.filters ?? {};
    const rows = report.rows?.data ?? [];

    const search = (overrides) => {
        router.get('/admin/warehouse/inventory', { ...f, ...overrides }, { preserveState: true });
    };

    return (
        <AppLayout>
            <Head title="Sản phẩm kho" />

            <div className="space-y-6">
                <h1 className="text-2xl font-bold tracking-tight">Danh sách sản phẩm kho</h1>

                <div className="flex flex-wrap gap-3 rounded-xl border bg-card p-4">
                    <Input
                        placeholder="Tên sản phẩm"
                        value={f.search ?? ''}
                        onChange={(e) => search({ search: e.target.value })}
                        className="max-w-xs"
                    />
                    <select
                        className="h-8 rounded-lg border px-2 text-sm"
                        value={f.warehouse_id ?? ''}
                        onChange={(e) => search({ warehouse_id: e.target.value || null })}
                    >
                        <option value="">— Chọn kho —</option>
                        {filterOptions?.warehouses?.map((w) => (
                            <option key={w.id} value={w.id}>
                                {w.name}
                            </option>
                        ))}
                    </select>
                    <Button size="sm" onClick={() => search()}>
                        <Search className="size-4" />
                        Tìm kiếm
                    </Button>
                </div>

                <ScrollDataTable>
                    <table className="w-full min-w-[900px] border-collapse text-xs">
                        <thead>
                            <tr>
                                <Th>#</Th>
                                <Th>Kho</Th>
                                <Th>Sản phẩm</Th>
                                <Th>Mã lô</Th>
                                <Th>Vị trí</Th>
                                <Th>Tồn</Th>
                                <Th>Chờ xuất</Th>
                                <Th>Ngừng KD</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => (
                                <tr key={r.id} className="hover:bg-muted/30">
                                    <Td>{r.id}</Td>
                                    <Td>{r.warehouseName}</Td>
                                    <Td>
                                        {r.productName}
                                        {r.sku && (
                                            <span className="text-muted-foreground"> ({r.sku})</span>
                                        )}
                                    </Td>
                                    <Td>{r.batchCode ?? '—'}</Td>
                                    <Td>{r.locationCode ?? '—'}</Td>
                                    <Td className="font-semibold tabular-nums">
                                        {formatNumber(r.stockQuantity)}
                                    </Td>
                                    <Td className="tabular-nums">{formatNumber(r.pendingSalesQuantity)}</Td>
                                    <Td>{r.isDiscontinued ? '✓' : ''}</Td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </ScrollDataTable>
            </div>
        </AppLayout>
    );
}
