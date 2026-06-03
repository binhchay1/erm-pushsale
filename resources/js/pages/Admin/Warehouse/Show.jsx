import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Search } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { formatNumber } from '@/lib/format';
import { DeleteRowButton } from '@/components/ui/delete-row-button';
import AppLayout from '@/layouts/AppLayout';

export default function WarehouseShow({ warehouse, filters, rows }) {
    const search = (value) => {
        router.get(`/admin/warehouses/${warehouse.id}`, { search: value }, { preserveState: true });
    };

    return (
        <AppLayout>
            <Head title={`Kho ${warehouse.name}`} />

            <div className="space-y-6">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon-sm" asChild>
                        <Link href="/admin/warehouses">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">{warehouse.name}</h1>
                        <p className="text-sm text-muted-foreground">
                            {warehouse.address ?? '—'} · {warehouse.phone ?? '—'} · Quản kho: {warehouse.manager_name ?? '—'} · Mã VTP:{' '}
                            {warehouse.vtp_code ?? '—'}
                        </p>
                    </div>
                </div>

                <div className="flex flex-wrap gap-3 rounded-xl border bg-card p-4">
                    <Input
                        placeholder="Tìm theo tên sản phẩm / SKU"
                        value={filters.search ?? ''}
                        onChange={(e) => search(e.target.value)}
                        className="max-w-md"
                    />
                    <Button size="sm" onClick={() => search(filters.search ?? '')}>
                        <Search className="size-4" />
                        Tìm kiếm
                    </Button>
                </div>

                <ScrollDataTable>
                    <table className="w-full min-w-[860px] border-collapse text-xs">
                        <thead>
                            <tr>
                                <Th>#</Th>
                                <Th>Sản phẩm</Th>
                                <Th>Mã lô</Th>
                                <Th>Vị trí</Th>
                                <Th>Tồn kho</Th>
                                <Th>Chờ xuất</Th>
                                <Th>Ngừng KD</Th>
                                <Th />
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length ? (
                                rows.map((r) => (
                                    <tr key={r.id} className="hover:bg-muted/30">
                                        <Td>{r.id}</Td>
                                        <Td>
                                            {r.product_name ?? '—'}
                                            {r.sku && <span className="text-muted-foreground"> ({r.sku})</span>}
                                        </Td>
                                        <Td>{r.batch_code ?? '—'}</Td>
                                        <Td>{r.location_code ?? '—'}</Td>
                                        <Td className="font-semibold">{formatNumber(r.stock_quantity)}</Td>
                                        <Td>{formatNumber(r.pending_sales_quantity)}</Td>
                                        <Td>{r.is_discontinued ? '✓' : ''}</Td>
                                        <Td>
                                            <DeleteRowButton
                                                url={`/admin/warehouse-inventories/${r.id}`}
                                                label={r.product_name}
                                            />
                                        </Td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <Td colSpan={8} className="py-8 text-center text-muted-foreground">
                                        Kho chưa có sản phẩm nào
                                    </Td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </ScrollDataTable>
            </div>
        </AppLayout>
    );
}
