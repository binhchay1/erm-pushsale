import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';

import { DeleteRowButton } from '@/components/ui/delete-row-button';
import { PageHeader } from '@/components/layout/PageHeader';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/format';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

export default function ProductsIndex({ products }) {
    const t = useT();

    return (
        <AppLayout>
            <Head title={t('pages.products.title')} />

            <div className="space-y-6">
                <PageHeader
                    title={t('pages.products.title')}
                    description={t('pages.products.desc_index')}
                    actions={
                        <Button asChild>
                            <Link href="/admin/products/create">
                                <Plus className="size-4" />
                                {t('pages.products.create')}
                            </Link>
                        </Button>
                    }
                />

                <ScrollDataTable>
                    <table className="w-full min-w-[900px] border-collapse text-xs">
                        <thead>
                            <tr>
                                <Th>{t('pages.products.col_name')}</Th>
                                <Th>{t('pages.products.col_sku')}</Th>
                                <Th>{t('pages.products.col_price')}</Th>
                                <Th>{t('pages.products.col_parent')}</Th>
                                <Th>{t('pages.products.col_variants')}</Th>
                                <Th>{t('pages.products.col_status')}</Th>
                                <Th>{t('pages.actions')}</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {products.length ? (
                                products.map((row) => (
                                    <tr key={row.id} className="hover:bg-muted/30">
                                        <Td className="font-medium">{row.name}</Td>
                                        <Td className="font-mono">{row.sku ?? '—'}</Td>
                                        <Td className="tabular-nums">{formatCurrency(row.unit_price)}</Td>
                                        <Td>{row.parent_name ?? '—'}</Td>
                                        <Td>{row.variants_count || '—'}</Td>
                                        <Td>{row.is_active ? t('pages.selling') : t('pages.stopped_short')}</Td>
                                        <Td>
                                            <div className="flex gap-1">
                                                <Button variant="outline" size="icon-sm" asChild>
                                                    <Link href={`/admin/products/${row.id}/edit`}>
                                                        <Pencil className="size-4" />
                                                    </Link>
                                                </Button>
                                                <DeleteRowButton
                                                    url={`/admin/products/${row.id}`}
                                                    label={row.name}
                                                    confirmMessage={t('pages.products.delete_confirm', { name: row.name })}
                                                />
                                            </div>
                                        </Td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <Td colSpan={7} className="py-8 text-center text-muted-foreground">
                                        {t('pages.products.empty')}
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
