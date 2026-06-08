import { Head, router } from '@inertiajs/react';
import { PackagePlus, Search } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import { DeleteRowButton } from '@/components/ui/delete-row-button';
import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { formatNumber } from '@/lib/format';

export default function Inventory({ report, filterOptions, intakeUrl = '/admin/warehouse/inventory/intake' }) {
    const f = report.filters ?? {};
    const rows = report.rows?.data ?? [];
    const recentIntakes = report.recentIntakes ?? [];

    const [warehouseId, setWarehouseId] = useState('');
    const [productId, setProductId] = useState('');
    const [quantity, setQuantity] = useState('');
    const [note, setNote] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const search = (overrides) => {
        router.get(window.location.pathname, { ...f, ...overrides }, { preserveState: true });
    };

    const submitIntake = () => {
        if (!warehouseId || !productId || !quantity) {
            toast.error('Chọn kho, sản phẩm và số lượng nhập.');
            return;
        }

        setSubmitting(true);
        router.post(
            intakeUrl,
            {
                warehouse_id: warehouseId,
                product_id: productId,
                quantity: Number(quantity),
                note: note || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setQuantity('');
                    setNote('');
                    toast.success('Đã nhập kho.');
                },
                onError: (errors) => {
                    toast.error(errors.quantity ?? errors.warehouse_id ?? 'Không nhập kho được.');
                },
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <AppLayout>
            <Head title="Sản phẩm kho" />

            <div className="space-y-6">
                <h1 className="text-2xl font-bold tracking-tight">Danh sách sản phẩm kho</h1>

                <div className="rounded-xl border bg-card p-4">
                    <div className="mb-3 flex items-center gap-2">
                        <PackagePlus className="size-4 text-primary" />
                        <h2 className="text-sm font-semibold">Nhập kho</h2>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        <div className="space-y-1">
                            <Label htmlFor="intake-warehouse">Kho</Label>
                            <select
                                id="intake-warehouse"
                                className="flex h-9 w-full rounded-md border bg-background px-2 text-sm"
                                value={warehouseId}
                                onChange={(e) => setWarehouseId(e.target.value)}
                            >
                                <option value="">— Chọn kho —</option>
                                {filterOptions?.warehouses?.map((w) => (
                                    <option key={w.id} value={w.id}>
                                        {w.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="intake-product">Sản phẩm</Label>
                            <select
                                id="intake-product"
                                className="flex h-9 w-full rounded-md border bg-background px-2 text-sm"
                                value={productId}
                                onChange={(e) => setProductId(e.target.value)}
                            >
                                <option value="">— Chọn SP —</option>
                                {filterOptions?.products?.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                        {p.sku ? ` (${p.sku})` : ''}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="intake-qty">Số lượng</Label>
                            <Input
                                id="intake-qty"
                                type="number"
                                min={1}
                                value={quantity}
                                onChange={(e) => setQuantity(e.target.value)}
                            />
                        </div>
                        <div className="space-y-1 lg:col-span-2">
                            <Label htmlFor="intake-note">Ghi chú</Label>
                            <Input
                                id="intake-note"
                                value={note}
                                onChange={(e) => setNote(e.target.value)}
                                placeholder="Lô hàng, nhà cung cấp…"
                            />
                        </div>
                    </div>
                    <div className="mt-3">
                        <Button size="sm" onClick={submitIntake} disabled={submitting}>
                            Xác nhận nhập kho
                        </Button>
                    </div>
                </div>

                {recentIntakes.length > 0 && (
                    <div className="rounded-xl border bg-card p-4">
                        <h2 className="mb-3 text-sm font-semibold">Lịch sử nhập kho gần đây</h2>
                        <ScrollDataTable>
                            <table className="w-full border-collapse text-xs">
                                <thead>
                                    <tr>
                                        <Th>Thời gian</Th>
                                        <Th>Kho</Th>
                                        <Th>Sản phẩm</Th>
                                        <Th>Số lượng</Th>
                                        <Th>Tồn sau nhập</Th>
                                        <Th>Người nhập</Th>
                                        <Th>Ghi chú</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recentIntakes.map((m) => (
                                        <tr key={m.id} className="hover:bg-muted/30">
                                            <Td>{m.createdAt}</Td>
                                            <Td>{m.warehouseName}</Td>
                                            <Td>
                                                {m.productName}
                                                {m.sku && (
                                                    <span className="text-muted-foreground"> ({m.sku})</span>
                                                )}
                                            </Td>
                                            <Td className="font-semibold tabular-nums text-emerald-600">
                                                +{formatNumber(m.quantity)}
                                            </Td>
                                            <Td className="tabular-nums">{formatNumber(m.stockAfter)}</Td>
                                            <Td>{m.userName}</Td>
                                            <Td className="max-w-xs truncate text-muted-foreground">
                                                {m.note ?? '—'}
                                            </Td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </ScrollDataTable>
                    </div>
                )}

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
                                <Th />
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
                                    <Td>
                                        <DeleteRowButton url={`/admin/warehouse-inventories/${r.id}`} label={r.productName} />
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </ScrollDataTable>
            </div>
        </AppLayout>
    );
}
