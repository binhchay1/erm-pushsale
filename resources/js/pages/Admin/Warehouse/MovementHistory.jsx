import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { StatusBadge } from '@/components/ui/status-badge';
import { cn } from '@/lib/utils';
import { formatNumber } from '@/lib/format';
import { movementTone } from '@/lib/status-tones';
import { useT } from '@/providers/I18nProvider';

export default function MovementHistory({ rows, filters, warehouses, products, types }) {
    const t = useT();
    const data = rows?.data ?? [];
    const f = filters ?? {};

    const search = (overrides) => {
        router.get('/admin/warehouse/movements', { ...f, ...overrides }, { preserveState: true });
    };

    return (
        <AppLayout>
            <Head title={t('operations.movement_history.title_full')} />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">{t('operations.movement_history.title_full')}</h1>
                    <p className="text-sm text-muted-foreground">{t('operations.movement_history.desc_detail')}</p>
                </div>

                <div className="grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-2 lg:grid-cols-6">
                    <div className="space-y-1">
                        <Label htmlFor="mh-from">{t('operations.movement_history.from_date')}</Label>
                        <Input
                            id="mh-from"
                            type="date"
                            value={f.date_from ?? ''}
                            onChange={(e) => search({ date_from: e.target.value || null })}
                        />
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="mh-to">{t('operations.movement_history.to_date')}</Label>
                        <Input
                            id="mh-to"
                            type="date"
                            value={f.date_to ?? ''}
                            onChange={(e) => search({ date_to: e.target.value || null })}
                        />
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="mh-warehouse">{t('operations.inventory.warehouse')}</Label>
                        <select
                            id="mh-warehouse"
                            className="input-soft flex h-9 w-full px-2"
                            value={f.warehouse_id ?? ''}
                            onChange={(e) => search({ warehouse_id: e.target.value || null })}
                        >
                            <option value="">{t('operations.movement_history.all_warehouses')}</option>
                            {warehouses?.map((w) => (
                                <option key={w.id} value={w.id}>
                                    {w.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="mh-product">{t('operations.inventory.product')}</Label>
                        <select
                            id="mh-product"
                            className="input-soft flex h-9 w-full px-2"
                            value={f.product_id ?? ''}
                            onChange={(e) => search({ product_id: e.target.value || null })}
                        >
                            <option value="">{t('operations.movement_history.all_products')}</option>
                            {products?.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                    {p.sku ? ` (${p.sku})` : ''}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="mh-type">{t('operations.movement_history.type')}</Label>
                        <select
                            id="mh-type"
                            className="input-soft flex h-9 w-full px-2"
                            value={f.type ?? ''}
                            onChange={(e) => search({ type: e.target.value || null })}
                        >
                            <option value="">{t('common.all')}</option>
                            {types?.map((type) => (
                                <option key={type.value} value={type.value}>
                                    {type.label}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="flex items-end">
                        <Button size="sm" onClick={() => search()}>
                            <Search className="size-4" />
                            {t('operations.movement_history.filter')}
                        </Button>
                    </div>
                </div>

                <ScrollDataTable>
                    <table className="w-full min-w-[1000px] border-collapse text-xs">
                        <thead>
                            <tr>
                                <Th>{t('operations.movement_history.col_time')}</Th>
                                <Th>{t('operations.movement_history.col_type')}</Th>
                                <Th>{t('operations.inventory.warehouse')}</Th>
                                <Th>{t('operations.movement_history.col_product')}</Th>
                                <Th className="text-right">{t('operations.movement_history.col_qty')}</Th>
                                <Th className="text-right">{t('operations.inventory.col_stock_after')}</Th>
                                <Th>{t('operations.movement_history.col_actor')}</Th>
                                <Th>{t('operations.movement_history.col_approver')}</Th>
                                <Th>{t('operations.movement_history.col_note')}</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.length ? (
                                data.map((m) => (
                                    <tr key={m.id}>
                                        <Td>{m.createdAt}</Td>
                                        <Td>
                                            <StatusBadge tone={movementTone(m.type)}>
                                                {m.typeLabel}
                                            </StatusBadge>
                                        </Td>
                                        <Td>{m.warehouseName ?? '—'}</Td>
                                        <Td className="font-medium">
                                            {m.productName ?? '—'}
                                            {m.sku && (
                                                <span className="font-normal text-muted-foreground">
                                                    {' '}
                                                    ({m.sku})
                                                </span>
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
                                ))
                            ) : (
                                <tr>
                                    <Td colSpan={9} className="py-10 text-center text-muted-foreground">
                                        {t('operations.movement_history.empty_filtered')}
                                    </Td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </ScrollDataTable>

                {rows?.links?.length > 3 && (
                    <div className="flex flex-wrap gap-2">
                        {rows.links.map((link) => (
                            <Button
                                key={link.label}
                                variant={link.active ? 'default' : 'outline'}
                                size="sm"
                                disabled={!link.url}
                                onClick={() => link.url && router.get(link.url)}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
