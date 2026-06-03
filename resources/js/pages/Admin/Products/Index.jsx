import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';

import { DeleteRowButton } from '@/components/ui/delete-row-button';
import { PageHeader } from '@/components/layout/PageHeader';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/format';
import AppLayout from '@/layouts/AppLayout';

export default function ProductsIndex({ products }) {
    return (
        <AppLayout>
            <Head title="Sản phẩm" />

            <div className="space-y-6">
                <PageHeader
                    title="Sản phẩm"
                    description="Danh mục sản phẩm dùng cho đơn hàng, kho và chiến dịch marketing."
                    actions={
                        <Button asChild>
                            <Link href="/admin/products/create">
                                <Plus className="size-4" />
                                Thêm sản phẩm
                            </Link>
                        </Button>
                    }
                />

                <ScrollDataTable>
                    <table className="w-full min-w-[900px] border-collapse text-xs">
                        <thead>
                            <tr>
                                <Th>Tên</Th>
                                <Th>SKU</Th>
                                <Th>Giá</Th>
                                <Th>Nhóm cha</Th>
                                <Th>Biến thể</Th>
                                <Th>Trạng thái</Th>
                                <Th>Thao tác</Th>
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
                                        <Td>{row.is_active ? 'Đang bán' : 'Ngừng'}</Td>
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
                                                    confirmMessage={`Xóa sản phẩm "${row.name}"?`}
                                                />
                                            </div>
                                        </Td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <Td colSpan={7} className="py-8 text-center text-muted-foreground">
                                        Chưa có sản phẩm
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
