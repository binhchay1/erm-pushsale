import { Head, router } from '@inertiajs/react';
import { FileSpreadsheet, MapPin, PackageMinus, PackagePlus, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

import { PageHeader } from '@/components/layout/PageHeader';
import { ReportPagination } from '@/components/reports/ReportPagination';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { Button } from '@/components/ui/button';
import { DeleteRowButton } from '@/components/ui/delete-row-button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { StatusBadge } from '@/components/ui/status-badge';
import { useTableSort } from '@/hooks/use-table-sort';
import AppLayout from '@/layouts/AppLayout';
import { formatNumber } from '@/lib/format';
import { movementTone } from '@/lib/status-tones';
import { cn } from '@/lib/utils';
import { useT } from '@/providers/I18nProvider';

function csvCell(value) {
    return `"${String(value ?? '').replaceAll('"', '""')}"`;
}

export default function Inventory({
    report,
    filterOptions,
    intakeUrl = '/admin/warehouse/inventory/intake',
    exportUrl = '/admin/warehouse/inventory/export',
    approverOptions = [],
}) {
    const t = useT();
    const routeUrl = typeof window !== 'undefined' ? window.location.pathname : '/admin/warehouse/inventory';
    const f = report.filters ?? {};
    const rows = report.rows?.data ?? [];
    const recentMovements = report.recentIntakes ?? [];
    const { sortedRows, sort, toggleSort } = useTableSort(rows, { defaultKey: 'productName' });

    const [query, setQuery] = useState(f.search ?? '');
    const [warehouseFilter, setWarehouseFilter] = useState(String(f.warehouse_id ?? ''));
    const [productFilter, setProductFilter] = useState(String(f.product_id ?? ''));
    const [locationFilter, setLocationFilter] = useState(f.location_code ?? '');
    const [batchFilter, setBatchFilter] = useState(f.batch_code ?? '');
    const [businessStatusFilter, setBusinessStatusFilter] = useState(f.business_status ?? '');
    const [movementOpen, setMovementOpen] = useState(false);
    const [mode, setMode] = useState('intake');
    const [warehouseId, setWarehouseId] = useState('');
    const [productId, setProductId] = useState('');
    const [quantity, setQuantity] = useState('');
    const [approverId, setApproverId] = useState('');
    const [note, setNote] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const isIntake = mode === 'intake';
    const canSubmitMovement =
        !!warehouseId && !!productId && Number(quantity) > 0 && !!approverId && !submitting;

    useEffect(() => {
        setQuery(f.search ?? '');
        setWarehouseFilter(String(f.warehouse_id ?? ''));
        setProductFilter(String(f.product_id ?? ''));
        setLocationFilter(f.location_code ?? '');
        setBatchFilter(f.batch_code ?? '');
        setBusinessStatusFilter(f.business_status ?? '');
    }, [f.search, f.warehouse_id, f.product_id, f.location_code, f.batch_code, f.business_status]);

    const search = (overrides = {}) => {
        router.get(
            routeUrl,
            {
                ...f,
                search: query || null,
                warehouse_id: warehouseFilter || null,
                product_id: productFilter || null,
                location_code: locationFilter || null,
                batch_code: batchFilter || null,
                business_status: businessStatusFilter || null,
                page: 1,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const openMovement = (nextMode = 'intake') => {
        setMode(nextMode);
        setMovementOpen(true);
    };

    const submitMovement = () => {
        if (!warehouseId || !productId || !quantity) {
            toast.error(t('operations.inventory.validation'));
            return;
        }
        if (!approverId) {
            toast.error(t('operations.inventory.approver_required'));
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
                    setMovementOpen(false);
                    toast.success(isIntake ? t('operations.inventory.intake_success') : t('operations.inventory.export_success'));
                },
                onError: (errors) => {
                    toast.error(
                        errors.quantity ??
                            errors.approved_by_user_id ??
                            errors.warehouse_id ??
                            (isIntake ? t('operations.inventory.intake_failed') : t('operations.inventory.export_failed')),
                    );
                },
                onFinish: () => setSubmitting(false),
            },
        );
    };

    const exportCsv = () => {
        const headers = [
            '#', 'Kho', 'Sản phẩm', 'SKU', 'Đơn vị tính', 'Mã lô', 'Ngày hết hạn', 'Vị trí',
            'Tồn kho', 'Chờ xuất bán hàng', 'Ngừng KD',
        ];
        const lines = sortedRows.map((row) => [
            row.id,
            row.warehouseName,
            row.productName,
            row.sku,
            row.uom,
            row.batchCode,
            row.expiryDate,
            row.locationCode,
            row.stockQuantity,
            row.pendingSalesQuantity,
            row.isDiscontinued ? 'Có' : 'Không',
        ].map(csvCell).join(','));
        const blob = new Blob([`\uFEFF${headers.map(csvCell).join(',')}\r\n${lines.join('\r\n')}`], {
            type: 'text/csv;charset=utf-8',
        });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `danh-sach-san-pham-kho-${new Date().toISOString().slice(0, 10)}.csv`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    };

    return (
        <AppLayout>
            <Head title={t('operations.inventory.inventory_title')} />

            <div className="pushsale-inventory-page">
                <PageHeader
                    title={t('operations.inventory.inventory_title')}
                    actions={(
                        <div className="pushsale-title-search">
                            <Input
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                onKeyDown={(event) => event.key === 'Enter' && search()}
                                placeholder="Tên sản phẩm"
                            />
                            <Button type="button" onClick={() => search()}>
                                <Search /> Tìm kiếm
                            </Button>
                        </div>
                    )}
                />

                <div className="pushsale-inventory-filters">
                    <select
                        value={warehouseFilter}
                        onChange={(event) => setWarehouseFilter(event.target.value)}
                    >
                        <option value="">--Chọn kho--</option>
                        {filterOptions?.warehouses?.map((warehouse) => (
                            <option key={warehouse.id} value={warehouse.id}>{warehouse.name}</option>
                        ))}
                    </select>
                    <select
                        value={productFilter}
                        onChange={(event) => setProductFilter(event.target.value)}
                    >
                        <option value="">--Chọn sản phẩm--</option>
                        {filterOptions?.products?.map((product) => (
                            <option key={product.id} value={product.id}>
                                {product.name}{product.sku ? ` (${product.sku})` : ''}
                            </option>
                        ))}
                    </select>
                    <Input
                        placeholder="Mã vị trí"
                        value={locationFilter}
                        onChange={(event) => setLocationFilter(event.target.value)}
                        onKeyDown={(event) => event.key === 'Enter' && search()}
                    />
                    <Input
                        placeholder="Mã lô"
                        value={batchFilter}
                        onChange={(event) => setBatchFilter(event.target.value)}
                        onKeyDown={(event) => event.key === 'Enter' && search()}
                    />
                    <select
                        value={businessStatusFilter}
                        onChange={(event) => setBusinessStatusFilter(event.target.value)}
                    >
                        <option value="">--Trạng thái kinh doanh--</option>
                        <option value="active">Đang kinh doanh</option>
                        <option value="stopped">Ngừng kinh doanh</option>
                    </select>
                </div>

                <div className="pushsale-inventory-actions">
                    <Button type="button" onClick={() => openMovement('intake')}>
                        <PackagePlus /> Xuất / nhập kho
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        title="Vị trí được cập nhật trong phiếu nhập/xuất kho"
                        onClick={() => openMovement('intake')}
                    >
                        <MapPin /> Cập nhật vị trí
                    </Button>
                    <Button type="button" variant="outline" onClick={exportCsv}>
                        <FileSpreadsheet /> Xuất Excel
                    </Button>
                </div>

                <ScrollDataTable id="inventory-table">
                    <table className="w-full min-w-[1320px] border-collapse text-xs">
                        <thead>
                            <tr>
                                <Th>#</Th>
                                <Th sortable sortKey="warehouseName" sort={sort} onSort={toggleSort}>Kho</Th>
                                <Th sortable sortKey="productName" sort={sort} onSort={toggleSort}>Sản phẩm</Th>
                                <Th>Đơn vị tính</Th>
                                <Th sortable sortKey="batchCode" sort={sort} onSort={toggleSort}>Mã lô</Th>
                                <Th sortable sortKey="expiryDate" sort={sort} onSort={toggleSort}>Ngày hết hạn</Th>
                                <Th sortable sortKey="locationCode" sort={sort} onSort={toggleSort}>Vị trí</Th>
                                <Th sortable sortKey="stockQuantity" sort={sort} onSort={toggleSort} className="text-right">Tồn kho</Th>
                                <Th sortable sortKey="pendingSalesQuantity" sort={sort} onSort={toggleSort} className="text-right">Chờ xuất bán hàng</Th>
                                <Th sortable sortKey="isDiscontinued" sort={sort} onSort={toggleSort}>Ngừng KD</Th>
                                <Th>Cập nhật</Th>
                                <Th />
                            </tr>
                        </thead>
                        <tbody>
                            {sortedRows.map((row) => (
                                <tr key={row.id}>
                                    <Td>{row.id}</Td>
                                    <Td className="font-semibold">{row.warehouseName}</Td>
                                    <Td className="font-medium">
                                        {row.productName}
                                        {row.sku && <span className="pushsale-muted"> ({row.sku})</span>}
                                    </Td>
                                    <Td>{row.uom ?? ''}</Td>
                                    <Td>{row.batchCode ?? ''}</Td>
                                    <Td>{row.expiryDate ?? ''}</Td>
                                    <Td>{row.locationCode ?? ''}</Td>
                                    <Td className="text-right font-semibold tabular-nums">{formatNumber(row.stockQuantity)}</Td>
                                    <Td className="text-right font-semibold tabular-nums">{formatNumber(row.pendingSalesQuantity)}</Td>
                                    <Td className="text-center"><input type="checkbox" checked={!!row.isDiscontinued} readOnly /></Td>
                                    <Td />
                                    <Td>
                                        <DeleteRowButton
                                            url={`/admin/warehouse-inventories/${row.id}`}
                                            label={row.productName}
                                        />
                                    </Td>
                                </tr>
                            ))}
                            {!rows.length && (
                                <tr>
                                    <Td colSpan={12} className="py-8 text-center text-muted-foreground">
                                        {t('operations.inventory.no_stock')}
                                    </Td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </ScrollDataTable>

                <ReportPagination
                    routeUrl={routeUrl}
                    filters={f}
                    meta={report.rows}
                    scrollTargetId="inventory-table"
                />
            </div>

            <Dialog open={movementOpen} onOpenChange={setMovementOpen}>
                <DialogContent className="pushsale-inventory-movement-dialog">
                    <DialogHeader>
                        <DialogTitle>Xuất / nhập kho</DialogTitle>
                    </DialogHeader>

                    <div data-slot="dialog-body" className="pushsale-dialog-body">
                        <div className="pushsale-movement-tabs">
                            <button
                                type="button"
                                className={cn(isIntake && 'is-active')}
                                onClick={() => setMode('intake')}
                            >
                                <PackagePlus /> Nhập kho
                            </button>
                            <button
                                type="button"
                                className={cn(!isIntake && 'is-active')}
                                onClick={() => setMode('export')}
                            >
                                <PackageMinus /> Xuất kho
                            </button>
                        </div>

                        <div className="pushsale-movement-form">
                            <div>
                                <Label htmlFor="move-warehouse">Kho (*)</Label>
                                <select id="move-warehouse" value={warehouseId} onChange={(event) => setWarehouseId(event.target.value)}>
                                    <option value="">--Chọn kho--</option>
                                    {filterOptions?.warehouses?.map((warehouse) => (
                                        <option key={warehouse.id} value={warehouse.id}>{warehouse.name}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="move-product">Sản phẩm (*)</Label>
                                <select id="move-product" value={productId} onChange={(event) => setProductId(event.target.value)}>
                                    <option value="">--Chọn sản phẩm--</option>
                                    {filterOptions?.products?.map((product) => (
                                        <option key={product.id} value={product.id}>
                                            {product.name}{product.sku ? ` (${product.sku})` : ''}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="move-qty">Số lượng (*)</Label>
                                <Input id="move-qty" type="number" min={1} value={quantity} onChange={(event) => setQuantity(event.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="move-approver">Người duyệt (*)</Label>
                                <select id="move-approver" value={approverId} onChange={(event) => setApproverId(event.target.value)}>
                                    <option value="">--Chọn người duyệt--</option>
                                    {approverOptions.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
                                </select>
                            </div>
                            <div className="pushsale-movement-note">
                                <Label htmlFor="move-note">Ghi chú</Label>
                                <Input
                                    id="move-note"
                                    value={note}
                                    onChange={(event) => setNote(event.target.value)}
                                    placeholder={isIntake ? 'Lô hàng, nhà cung cấp…' : 'Lý do xuất, nơi nhận…'}
                                />
                            </div>
                        </div>

                        {recentMovements.length > 0 && (
                            <div className="pushsale-recent-movements">
                                <strong>Phiếu nhập / xuất gần đây</strong>
                                <ScrollDataTable>
                                    <table className="w-full min-w-[760px] border-collapse text-xs">
                                        <thead>
                                            <tr>
                                                <Th>Thời gian</Th><Th>Loại</Th><Th>Kho</Th><Th>Sản phẩm</Th>
                                                <Th className="text-right">Số lượng</Th><Th className="text-right">Tồn sau</Th><Th>Người duyệt</Th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {recentMovements.map((movement) => (
                                                <tr key={movement.id}>
                                                    <Td>{movement.createdAt}</Td>
                                                    <Td><StatusBadge tone={movementTone(movement.type)}>{movement.typeLabel}</StatusBadge></Td>
                                                    <Td>{movement.warehouseName}</Td>
                                                    <Td>{movement.productName}</Td>
                                                    <Td className="text-right">{movement.quantity >= 0 ? '+' : ''}{formatNumber(movement.quantity)}</Td>
                                                    <Td className="text-right">{formatNumber(movement.stockAfter)}</Td>
                                                    <Td>{movement.approverName ?? '—'}</Td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </ScrollDataTable>
                            </div>
                        )}
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setMovementOpen(false)}>Đóng</Button>
                        <Button type="button" onClick={submitMovement} disabled={!canSubmitMovement}>
                            {isIntake ? 'Nhập kho' : 'Xuất kho'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
