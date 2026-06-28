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
import { useTableSort } from '@/hooks/use-table-sort';
import { cn } from '@/lib/utils';
import { formatNumber } from '@/lib/format';
import { movementTone } from '@/lib/status-tones';
import { useT } from '@/providers/I18nProvider';

export default function Inventory({
    report,
    filterOptions,
    intakeUrl = '/admin/warehouse/inventory/intake',
    exportUrl = '/admin/warehouse/inventory/export',
    approverOptions = [],
}) {
    const t = useT();
    const f = report.filters ?? {};
    const rows = report.rows?.data ?? [];
    const recentMovements = report.recentIntakes ?? [];
    const { sortedRows, sort, toggleSort } = useTableSort(rows, { defaultKey: 'productName' });

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

    const search = (overrides) => {
        router.get(window.location.pathname, { ...f, ...overrides }, { preserveState: true });
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
                    toast.success(isIntake ? t('operations.inventory.intake_success') : t('operations.inventory.export_success'));
                },
                onError: (errors) => {
                    toast.error(
                        errors.quantity ??
                            errors.approved_by_user_id ??
                            errors.warehouse_id ??
                            (isIntake ? t('operations.inventory.intake_failed') : t('operations.inventory.export_failed'))
                    );
                },
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <AppLayout>
            <Head title={t('operations.inventory.inventory_title')} />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">{t('operations.inventory.inventory_title')}</h1>
                    <p className="text-sm text-muted-foreground">{t('operations.inventory.inventory_desc')}</p>
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
                                {isIntake ? t('operations.inventory.intake_form') : t('operations.inventory.export_form')}
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
                                {t('operations.inventory.intake')}
                            </button>
                            <button
                                type="button"
                                onClick={() => setMode('export')}
                                className={cn(
                                    'rounded-md px-3 py-1 text-xs font-medium transition-colors',
                                    !isIntake ? 'bg-card shadow-sm' : 'text-muted-foreground'
                                )}
                            >
                                {t('operations.inventory.export')}
                            </button>
                        </div>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                        <div className="space-y-1">
                            <Label htmlFor="move-warehouse">{t('operations.inventory.warehouse')}</Label>
                            <select
                                id="move-warehouse"
                                className="input-soft flex h-9 w-full px-2"
                                value={warehouseId}
                                onChange={(e) => setWarehouseId(e.target.value)}
                            >
                                <option value="">{t('operations.inventory.select_warehouse')}</option>
                                {filterOptions?.warehouses?.map((w) => (
                                    <option key={w.id} value={w.id}>
                                        {w.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="move-product">{t('operations.inventory.product')}</Label>
                            <select
                                id="move-product"
                                className="input-soft flex h-9 w-full px-2"
                                value={productId}
                                onChange={(e) => setProductId(e.target.value)}
                            >
                                <option value="">{t('operations.inventory.select_product')}</option>
                                {filterOptions?.products?.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                        {p.sku ? ` (${p.sku})` : ''}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="move-qty">{t('operations.inventory.quantity')}</Label>
                            <Input
                                id="move-qty"
                                type="number"
                                min={1}
                                value={quantity}
                                onChange={(e) => setQuantity(e.target.value)}
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="move-approver">{t('operations.inventory.approver')}</Label>
                            <select
                                id="move-approver"
                                className="input-soft flex h-9 w-full px-2"
                                value={approverId}
                                onChange={(e) => setApproverId(e.target.value)}
                            >
                                <option value="">{t('operations.inventory.select_approver')}</option>
                                {approverOptions.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="space-y-1 lg:col-span-2">
                            <Label htmlFor="move-note">{t('operations.inventory.note')}</Label>
                            <Input
                                id="move-note"
                                value={note}
                                onChange={(e) => setNote(e.target.value)}
                                placeholder={
                                    isIntake ? t('operations.inventory.note_intake_ph') : t('operations.inventory.note_export_ph')
                                }
                            />
                        </div>
                    </div>
                    <div className="mt-3 flex flex-wrap items-center gap-3">
                        <Button
                            size="sm"
                            onClick={submitMovement}
                            disabled={!canSubmitMovement}
                            title={canSubmitMovement ? undefined : t('operations.inventory.fill_required')}
                        >
                            {isIntake ? t('operations.inventory.submit_intake') : t('operations.inventory.submit_export')}
                        </Button>
                        {!canSubmitMovement && (
                            <span className="text-xs text-muted-foreground">
                                {t('operations.inventory.fill_required')}
                            </span>
                        )}
                    </div>
                </div>

                {recentMovements.length > 0 && (
                    <div className="rounded-xl border bg-card p-4">
                        <h2 className="mb-3 text-sm font-semibold">{t('operations.inventory.recent_movements')}</h2>
                        <ScrollDataTable>
                            <table className="w-full border-collapse text-xs">
                                <thead>
                                    <tr>
                                        <Th>{t('operations.movement_history.col_time')}</Th>
                                        <Th>{t('operations.movement_history.col_type')}</Th>
                                        <Th>{t('operations.inventory.warehouse')}</Th>
                                        <Th>{t('operations.inventory.product')}</Th>
                                        <Th className="text-right">{t('operations.inventory.quantity')}</Th>
                                        <Th className="text-right">{t('operations.inventory.col_stock_after')}</Th>
                                        <Th>{t('operations.movement_history.col_actor')}</Th>
                                        <Th>{t('operations.movement_history.col_approver')}</Th>
                                        <Th>{t('operations.movement_history.col_note')}</Th>
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
                        placeholder={t('operations.inventory.search_product')}
                        value={f.search ?? ''}
                        onChange={(e) => search({ search: e.target.value })}
                        className="max-w-xs"
                    />
                    <select
                        className="input-soft h-9 px-2"
                        value={f.warehouse_id ?? ''}
                        onChange={(e) => search({ warehouse_id: e.target.value || null })}
                    >
                        <option value="">{t('operations.inventory.all_warehouses')}</option>
                        {filterOptions?.warehouses?.map((w) => (
                            <option key={w.id} value={w.id}>
                                {w.name}
                            </option>
                        ))}
                    </select>
                    <Button size="sm" onClick={() => search()}>
                        <Search className="size-4" />
                        {t('common.search')}
                    </Button>
                </div>

                <ScrollDataTable>
                    <table className="w-full min-w-[900px] border-collapse text-xs">
                        <thead>
                            <tr>
                                <Th>#</Th>
                                <Th sortable sortKey="warehouseName" sort={sort} onSort={toggleSort}>{t('operations.inventory.warehouse')}</Th>
                                <Th sortable sortKey="productName" sort={sort} onSort={toggleSort}>{t('operations.inventory.product')}</Th>
                                <Th sortable sortKey="batchCode" sort={sort} onSort={toggleSort}>{t('operations.inventory.col_batch')}</Th>
                                <Th sortable sortKey="locationCode" sort={sort} onSort={toggleSort}>{t('operations.inventory.col_location')}</Th>
                                <Th sortable sortKey="stockQuantity" sort={sort} onSort={toggleSort} className="text-right">{t('operations.inventory.col_on_hand')}</Th>
                                <Th sortable sortKey="pendingSalesQuantity" sort={sort} onSort={toggleSort} className="text-right">{t('operations.inventory.col_pending')}</Th>
                                <Th sortable sortKey="isDiscontinued" sort={sort} onSort={toggleSort}>{t('operations.inventory.col_stopped')}</Th>
                                <Th />
                            </tr>
                        </thead>
                        <tbody>
                            {sortedRows.map((r) => (
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
                                        {t('operations.inventory.no_stock')}
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
