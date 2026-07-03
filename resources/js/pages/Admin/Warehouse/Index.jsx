import { Head, Link, router } from '@inertiajs/react';
import { Eye, Pencil, Plus, Trash2 } from 'lucide-react';

import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/button';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { useConfirm } from '@/hooks/use-confirm';
import { useTableSort } from '@/hooks/use-table-sort';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

export default function WarehouseIndex({ warehouses }) {
    const t = useT();
    const { ask, ConfirmDialogPortal } = useConfirm();
    const { sortedRows, sort, toggleSort } = useTableSort(warehouses, { defaultKey: 'name' });

    const removeWarehouse = async (id, name) => {
        const ok = await ask({
            title: t('pages.warehouse.delete_title'),
            description: t('pages.warehouse.delete_desc', { name }),
            confirmLabel: t('common.delete'),
            variant: 'destructive',
        });
        if (!ok) return;
        router.delete(`/admin/warehouses/${id}`);
    };

    return (
        <AppLayout>
            <Head title={t('pages.warehouse.title')} />

            <div className="space-y-6">
                <PageHeader
                    title={t('pages.warehouse.title')}
                    actions={
                        <Button asChild>
                            <Link href="/admin/warehouses/create">
                                <Plus className="size-4" />
                                {t('pages.warehouse.create')}
                            </Link>
                        </Button>
                    }
                />

                <ScrollDataTable>
                    <table className="w-full min-w-[980px] border-collapse text-xs">
                        <thead>
                            <tr>
                                <Th sortable sortKey="name" sort={sort} onSort={toggleSort}>{t('pages.warehouse.col_name')}</Th>
                                <Th sortable sortKey="phone" sort={sort} onSort={toggleSort}>{t('pages.warehouse.col_phone')}</Th>
                                <Th sortable sortKey="address" sort={sort} onSort={toggleSort}>{t('pages.warehouse.col_address')}</Th>
                                <Th sortable sortKey="manager_name" sort={sort} onSort={toggleSort}>{t('pages.warehouse.col_manager')}</Th>
                                <Th sortable sortKey="vtp_code" sort={sort} onSort={toggleSort}>{t('pages.warehouse.col_vtp')}</Th>
                                <Th sortable sortKey="products_count" sort={sort} onSort={toggleSort}>{t('pages.warehouse.col_products_count')}</Th>
                                <Th>{t('pages.actions')}</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {sortedRows.length ? (
                                sortedRows.map((row) => (
                                    <tr key={row.id} className="hover:bg-muted/30">
                                        <Td className="font-medium">{row.name}</Td>
                                        <Td>{row.phone ?? '—'}</Td>
                                        <Td>{row.address ?? '—'}</Td>
                                        <Td>{row.manager_name ?? '—'}</Td>
                                        <Td className="font-mono">{row.vtp_code ?? '—'}</Td>
                                        <Td>{row.products_count}</Td>
                                        <Td>
                                            <div className="flex gap-1">
                                                <Button variant="outline" size="icon-sm" asChild>
                                                    <Link href={`/admin/warehouses/${row.id}`}>
                                                        <Eye className="size-4" />
                                                    </Link>
                                                </Button>
                                                <Button variant="outline" size="icon-sm" asChild>
                                                    <Link href={`/admin/warehouses/${row.id}/edit`}>
                                                        <Pencil className="size-4" />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    size="icon-sm"
                                                    className="text-destructive"
                                                    onClick={() => removeWarehouse(row.id, row.name)}
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </div>
                                        </Td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <Td colSpan={7} className="py-8 text-center text-muted-foreground">
                                        {t('pages.warehouse.empty')}
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
