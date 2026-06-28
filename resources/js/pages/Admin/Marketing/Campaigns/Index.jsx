import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Target, Trash2 } from 'lucide-react';

import { PageHeader } from '@/components/layout/PageHeader';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/hooks/use-confirm';
import { useTableSort } from '@/hooks/use-table-sort';
import { formatCurrency } from '@/lib/format';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

export default function CampaignIndex({ baseUrl, campaigns }) {
    const t = useT();
    const { ask, ConfirmDialogPortal } = useConfirm();
    const { sortedRows, sort, toggleSort } = useTableSort(campaigns, { defaultKey: 'name' });

    const remove = async (id, name) => {
        const ok = await ask({
            title: t('pages.campaigns.delete_title'),
            description: t('pages.campaigns.delete_desc', { name }),
            confirmLabel: t('common.delete'),
            variant: 'destructive',
        });
        if (!ok) return;
        router.delete(`${baseUrl}/${id}`);
    };

    return (
        <AppLayout>
            <Head title={t('pages.campaigns.admin_title')} />

            <div className="space-y-6">
                <PageHeader
                    title={t('pages.campaigns.admin_title')}
                    description={t('pages.campaigns.admin_desc_detail')}
                    actions={
                        <Button asChild>
                            <Link href={`${baseUrl}/create`}>
                                <Plus className="size-4" />
                                {t('pages.campaigns.admin_create')}
                            </Link>
                        </Button>
                    }
                />

                <ScrollDataTable>
                    <table className="w-full min-w-[1040px] border-collapse text-xs">
                        <thead>
                            <tr>
                                <Th sortable sortKey="name" sort={sort} onSort={toggleSort}>{t('pages.campaigns.col_campaign')}</Th>
                                <Th sortable sortKey="product" sort={sort} onSort={toggleSort}>{t('pages.campaigns.col_product')}</Th>
                                <Th sortable sortKey="marketer" sort={sort} onSort={toggleSort}>{t('pages.campaigns.col_marketer')}</Th>
                                <Th sortable sortKey="ad_channel" sort={sort} onSort={toggleSort}>{t('pages.campaigns.col_channel')}</Th>
                                <Th sortable sortKey="utm_campaign" sort={sort} onSort={toggleSort}>{t('pages.campaigns.col_utm')}</Th>
                                <Th sortable sortKey="budget" sort={sort} onSort={toggleSort}>{t('pages.campaigns.budget')}</Th>
                                <Th sortable sortKey="orders_count" sort={sort} onSort={toggleSort}>{t('pages.campaigns.col_orders')}</Th>
                                <Th sortable sortKey="revenue" sort={sort} onSort={toggleSort}>{t('pages.campaigns.col_revenue')}</Th>
                                <Th sortable sortKey="is_active" sort={sort} onSort={toggleSort}>{t('pages.campaigns.col_status')}</Th>
                                <Th>{t('pages.actions')}</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {sortedRows.length ? (
                                sortedRows.map((row) => (
                                    <tr key={row.id} className="hover:bg-muted/30">
                                        <Td className="font-medium">{row.name}</Td>
                                        <Td>{row.product ?? '—'}</Td>
                                        <Td>{row.marketer ?? <span className="text-destructive">{t('pages.unassigned')}</span>}</Td>
                                        <Td>{row.ad_channel ?? '—'}</Td>
                                        <Td className="font-mono">{row.utm_campaign ?? '—'}</Td>
                                        <Td className="text-right">{formatCurrency(row.budget)}</Td>
                                        <Td className="text-right">{row.orders_count}</Td>
                                        <Td className="text-right font-semibold">{formatCurrency(row.revenue)}</Td>
                                        <Td>
                                            <span
                                                className={
                                                    row.is_active
                                                        ? 'rounded-full bg-emerald-500/10 px-2 py-0.5 text-emerald-600'
                                                        : 'rounded-full bg-muted px-2 py-0.5 text-muted-foreground'
                                                }
                                            >
                                                {row.is_active ? t('pages.active') : t('pages.paused')}
                                            </span>
                                        </Td>
                                        <Td>
                                            <div className="flex gap-1">
                                                <Button variant="outline" size="icon-sm" asChild>
                                                    <Link href={`${baseUrl}/${row.id}/edit`}>
                                                        <Pencil className="size-4" />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    size="icon-sm"
                                                    className="text-destructive"
                                                    onClick={() => remove(row.id, row.name)}
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </div>
                                        </Td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <Td colSpan={10} className="py-10 text-center text-muted-foreground">
                                        <Target className="mx-auto mb-2 size-6 opacity-50" />
                                        {t('pages.campaigns.empty')}
                                    </Td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </ScrollDataTable>
            </div>

            <ConfirmDialogPortal />
        </AppLayout>
    );
}
