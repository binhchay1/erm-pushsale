import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';

import { DeleteRowButton } from '@/components/ui/delete-row-button';
import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { useT } from '@/providers/I18nProvider';

export default function FailedOrders({ report, filterOptions }) {
    const t = useT();
    const f = report.filters ?? {};
    const rows = report.rows?.data ?? [];

    const search = (overrides) => {
        router.get('/admin/orders/failed', { ...f, ...overrides }, { preserveState: true });
    };

    return (
        <AppLayout>
            <Head title={t('pages.failed_orders.title')} />

            <div className="space-y-6">
                <h1 className="text-2xl font-bold tracking-tight">{t('pages.failed_orders.list_title')}</h1>

                <div className="flex flex-wrap gap-3 rounded-xl border bg-card p-4">
                    <select
                        className="input-soft h-8 px-2"
                        value={f.platform ?? ''}
                        onChange={(e) => search({ platform: e.target.value || null })}
                    >
                        <option value="">{t('pages.failed_orders.filter_platform')}</option>
                        {report.platforms?.map((p) => (
                            <option key={p} value={p}>
                                {p}
                            </option>
                        ))}
                    </select>
                    <select
                        className="input-soft h-8 px-2"
                        value={f.warehouse_id ?? ''}
                        onChange={(e) => search({ warehouse_id: e.target.value || null })}
                    >
                        <option value="">{t('pages.failed_orders.filter_warehouse')}</option>
                        {filterOptions?.warehouses?.map((w) => (
                            <option key={w.id} value={w.id}>
                                {w.name}
                            </option>
                        ))}
                    </select>
                    <Input
                        placeholder={t('pages.failed_orders.partner_order_ph')}
                        value={f.partner_order_id ?? ''}
                        onChange={(e) => search({ partner_order_id: e.target.value })}
                        className="max-w-xs"
                    />
                    <Button size="sm" onClick={() => search()}>
                        <Search className="size-4" />
                        {t('common.search')}
                    </Button>
                </div>

                <ScrollDataTable>
                    <table className="w-full border-collapse text-xs">
                        <thead>
                            <tr>
                                <Th>{t('pages.stt')}</Th>
                                <Th>{t('pages.failed_orders.col_partner_order')}</Th>
                                <Th>{t('pages.failed_orders.col_platform')}</Th>
                                <Th>{t('pages.failed_orders.col_warehouse')}</Th>
                                <Th>{t('pages.failed_orders.col_error_desc')}</Th>
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
                                        {t('pages.failed_orders.empty')}
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
