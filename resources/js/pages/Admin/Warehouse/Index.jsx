import { Head, Link, router } from '@inertiajs/react';
import { Eye, Pencil, Plus, Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import AppLayout from '@/layouts/AppLayout';

export default function WarehouseIndex({ warehouses }) {
    const removeWarehouse = (id, name) => {
        if (!window.confirm(`Xóa kho "${name}"?`)) return;
        router.delete(`/admin/warehouses/${id}`);
    };

    return (
        <AppLayout>
            <Head title="Danh sách kho" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold tracking-tight">Danh sách kho</h1>
                    <Button asChild>
                        <Link href="/admin/warehouses/create">
                            <Plus className="size-4" />
                            Tạo kho
                        </Link>
                    </Button>
                </div>

                <ScrollDataTable>
                    <table className="w-full min-w-[980px] border-collapse text-xs">
                        <thead>
                            <tr>
                                <Th>Tên kho</Th>
                                <Th>Số điện thoại</Th>
                                <Th>Địa chỉ</Th>
                                <Th>Quản kho</Th>
                                <Th>Mã VTP</Th>
                                <Th>Sản phẩm</Th>
                                <Th>Thao tác</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {warehouses.length ? (
                                warehouses.map((row) => (
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
                                        Chưa có kho nào
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
