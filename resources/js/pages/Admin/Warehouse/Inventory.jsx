import { Head, router } from '@inertiajs/react';
import { PackageMinus, PackagePlus, Search } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import { DeleteRowButton } from '@/components/ui/delete-row-button';
import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { StatusBadge } from '@/components/ui/status-badge';
import { cn } from '@/lib/utils';
import { formatNumber } from '@/lib/format';
import { movementTone } from '@/lib/status-tones';

export default function Inventory({
    report,
    filterOptions,
    intakeUrl = '/admin/warehouse/inventory/intake',
    exportUrl = '/admin/warehouse/inventory/export',
    approverOptions = [],
}) {
    const f = report.filters ?? {};
    const rows = report.rows?.data ?? [];
    const recentMovements = report.recentIntakes ?? [];

    const [mode, setMode] = useState('intake');
    const [warehouseId, setWarehouseId] = useState('');
    const [productId, setProductId] = useState('');
    const [quantity, setQuantity] = useState('');
    const [approverId, setApproverId] = useState('');
    const [note, setNote] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const isIntake = mode === 'intake';

    const search = (overrides) => {
        router.get(window.location.pathname, { ...f, ...overrides }, { preserveState: true });
    };

    const submitMovement = () => {
        if (!warehouseId || !productId || !quantity) {
            toast.error('Chọn kho, sản phẩm và số lượng.');
            return;
        }
        if (!approverId) {
            toast.error('Chọn trưởng kho ký duyệt.');
            return;
        }

        setSubmitting(true);
        router.post(
            isIntake ? intakeUrl : exportUrl,
            {
                warehouse_id: warehouseId,
                product_id: productId,
                quantity: Number(quantity),
                approved_by_user_id: approverId,
                note: note || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setQuantity('');
                    setNote('');
                    toast.success(isIntake ? 'Đã nhập kho.' : 'Đã xuất kho.');
                },
                onError: (errors) => {
                    toast.error(
                        errors.quantity ??
                            errors.approved_by_user_id ??
                            errors.warehouse_id ??
                            (isIntake ? 'Không nhập kho được.' : 'Không xuất kho được.')
                    );
                },
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <AppLayout>
            <Head title="Tồn kho sản phẩm" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Tồn kho sản phẩm</h1>
                    <p className="text-sm text-muted-foreground">
                        Theo dõi số lượng hàng từng kho, nhập thêm hoặc xuất bớt hàng — có trưởng kho ký
                        duyệt
                    </p>
                </div>

                <div className="rounded-xl border bg-card p-4">
                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div className="flex items-center gap-2">
                            {isIntake ? (
                                <PackagePlus className="size-4 text-emerald-600" />
                            ) : (
                                <PackageMinus className="size-4 text-amber-600" />
                            )}
                            <h2 className="text-sm font-semibold">
                                {isIntake ? 'Nhập hàng vào kho' : 'Xuất hàng khỏi kho'}
                            </h2>
                        </div>
                        <div className="flex rounded-lg bg-muted p-0.5">
                            <button
                                type="button"
                                onClick={() => setMode('intake')}
                                className={cn(
                                    'rounded-md px-3 py-1 text-xs font-medium transition-colors',
                                    isIntake ? 'bg-card shadow-sm' : 'text-muted-foreground'
                                )}
                            >
                                Nhập kho
                            </button>
                            <button
                                type="button"
                                onClick={() => setMode('export')}
                                className={cn(
                                    'rounded-md px-3 py-1 text-xs font-medium transition-colors',
                                    !isIntake ? 'bg-card shadow-sm' : 'text-muted-foreground'
                                )}
                            >
                                Xuất kho
                            </button>
                        </div>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                        <div className="space-y-1">
                            <Label htmlFor="move-warehouse">Kho</Label>
                            <select
                                id="move-warehouse"
                                className="input-soft flex h-9 w-full px-2"
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
                            <Label htmlFor="move-product">Sản phẩm</Label>
                            <select
                                id="move-product"
                                className="input-soft flex h-9 w-full px-2"
                                value={productId}
                                onChange={(e) => setProductId(e.target.value)}
                            >
                                <option value="">— Chọn sản phẩm —</option>
                                {filterOptions?.products?.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                        {p.sku ? ` (${p.sku})` : ''}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="move-qty">Số lượng</Label>
                            <Input
                                id="move-qty"
                                type="number"
                                min={1}
                                value={quantity}
                                onChange={(e) => setQuantity(e.target.value)}
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="move-approver">Trưởng kho duyệt</Label>
                            <select
                                id="move-approver"
                                className="input-soft flex h-9 w-full px-2"
                                value={approverId}
                                onChange={(e) => setApproverId(e.target.value)}
                            >
                                <option value="">— Chọn người duyệt —</option>
                                {approverOptions.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="space-y-1 lg:col-span-2">
                            <Label htmlFor="move-note">Ghi chú</Label>
                            <Input
                                id="move-note"
                                value={note}
                                onChange={(e) => setNote(e.target.value)}
                                placeholder={
                                    isIntake ? 'Lô hàng, nhà cung cấp…' : 'Lý do xuất, nơi nhận…'
                                }
                            />
                        </div>
                    </div>
                    <div className="mt-3">
                        <Button size="sm" onClick={submitMovement} disabled={submitting}>
                            {isIntake ? 'Xác nhận nhập kho' : 'Xác nhận xuất kho'}
                        </Button>
                    </div>
                </div>

                {recentMovements.length > 0 && (
                    <div className="rounded-xl border bg-card p-4">
                        <h2 className="mb-3 text-sm font-semibold">Phiếu nhập / xuất gần đây</h2>
                        <ScrollDataTable>
                            <table className="w-full border-collapse text-xs">
                                <thead>
                                    <tr>
                                        <Th>Thời gian</Th>
                                        <Th>Loại</Th>
                                        <Th>Kho</Th>
                                        <Th>Sản phẩm</Th>
                                        <Th className="text-right">Số lượng</Th>
                                        <Th className="text-right">Tồn sau</Th>
                                        <Th>Người thực hiện</Th>
                                        <Th>Người duyệt</Th>
                                        <Th>Ghi chú</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recentMovements.map((m) => (
                                        <tr key={m.id}>
                                            <Td>{m.createdAt}</Td>
                                            <Td>
                                                <StatusBadge tone={movementTone(m.type)}>
                                                    {m.typeLabel}
                                                </StatusBadge>
                                            </Td>
                                            <Td>{m.warehouseName}</Td>
                                            <Td>
                                                {m.productName}
                                                {m.sku && (
                                                    <span className="text-muted-foreground"> ({m.sku})</span>
                                                )}
                                            </Td>
                                            <Td
                                                className={cn(
                                                    'text-right font-semibold tabular-nums',
                                                    m.quantity >= 0 ? 'text-emerald-600' : 'text-amber-600'
                                                )}
                                            >
                                                {m.quantity >= 0 ? '+' : ''}
                                                {formatNumber(m.quantity)}
                                            </Td>
                                            <Td className="text-right tabular-nums">
                                                {formatNumber(m.stockAfter)}
                                            </Td>
                                            <Td>{m.userName}</Td>
                                            <Td>{m.approverName ?? '—'}</Td>
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
                        placeholder="Tìm theo tên sản phẩm"
                        value={f.search ?? ''}
                        onChange={(e) => search({ search: e.target.value })}
                        className="max-w-xs"
                    />
                    <select
                        className="input-soft h-9 px-2"
                        value={f.warehouse_id ?? ''}
                        onChange={(e) => search({ warehouse_id: e.target.value || null })}
                    >
                        <option value="">Tất cả kho</option>
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
                                <Th className="text-right">Đang tồn</Th>
                                <Th className="text-right">Chờ xuất</Th>
                                <Th>Ngừng bán</Th>
                                <Th />
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => (
                                <tr key={r.id}>
                                    <Td>{r.id}</Td>
                                    <Td>{r.warehouseName}</Td>
                                    <Td className="font-medium">
                                        {r.productName}
                                        {r.sku && (
                                            <span className="font-normal text-muted-foreground">
                                                {' '}
                                                ({r.sku})
                                            </span>
                                        )}
                                    </Td>
                                    <Td>{r.batchCode ?? '—'}</Td>
                                    <Td>{r.locationCode ?? '—'}</Td>
                                    <Td className="text-right font-semibold tabular-nums">
                                        {formatNumber(r.stockQuantity)}
                                    </Td>
                                    <Td className="text-right tabular-nums">
                                        {formatNumber(r.pendingSalesQuantity)}
                                    </Td>
                                    <Td>{r.isDiscontinued ? '✓' : ''}</Td>
                                    <Td>
                                        <DeleteRowButton
                                            url={`/admin/warehouse-inventories/${r.id}`}
                                            label={r.productName}
                                        />
                                    </Td>
                                </tr>
                            ))}
                            {!rows.length && (
                                <tr>
                                    <Td colSpan={9} className="py-8 text-center text-muted-foreground">
                                        Chưa có hàng trong kho
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
