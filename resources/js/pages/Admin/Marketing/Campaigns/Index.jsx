import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Target, Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import AppLayout from '@/layouts/AppLayout';

const formatVnd = (value) =>
    new Intl.NumberFormat('vi-VN').format(Number(value || 0)) + ' đ';

export default function CampaignIndex({ baseUrl, campaigns }) {
    const remove = (id, name) => {
        if (!window.confirm(`Xóa chiến dịch "${name}"?`)) return;
        router.delete(`${baseUrl}/${id}`);
    };

    return (
        <AppLayout>
            <Head title="Chiến dịch marketing" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Chiến dịch marketing</h1>
                        <p className="text-sm text-muted-foreground">
                            Mỗi chiến dịch gắn 1 sản phẩm trong kho + 1 marketer phụ trách. Lead từ Ladipage khớp
                            theo <span className="font-mono">utm_campaign</span> sẽ tự tính doanh thu cho marketer này.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={`${baseUrl}/create`}>
                            <Plus className="size-4" />
                            Tạo chiến dịch
                        </Link>
                    </Button>
                </div>

                <ScrollDataTable>
                    <table className="w-full min-w-[1040px] border-collapse text-xs">
                        <thead>
                            <tr>
                                <Th>Chiến dịch</Th>
                                <Th>Sản phẩm</Th>
                                <Th>Marketer</Th>
                                <Th>Kênh</Th>
                                <Th>utm_campaign</Th>
                                <Th>Ngân sách</Th>
                                <Th>Đơn</Th>
                                <Th>Doanh thu</Th>
                                <Th>Trạng thái</Th>
                                <Th>Thao tác</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {campaigns.length ? (
                                campaigns.map((row) => (
                                    <tr key={row.id} className="hover:bg-muted/30">
                                        <Td className="font-medium">{row.name}</Td>
                                        <Td>{row.product ?? '—'}</Td>
                                        <Td>{row.marketer ?? <span className="text-destructive">Chưa gán</span>}</Td>
                                        <Td>{row.ad_channel ?? '—'}</Td>
                                        <Td className="font-mono">{row.utm_campaign ?? '—'}</Td>
                                        <Td className="text-right">{formatVnd(row.budget)}</Td>
                                        <Td className="text-right">{row.orders_count}</Td>
                                        <Td className="text-right font-semibold">{formatVnd(row.revenue)}</Td>
                                        <Td>
                                            <span
                                                className={
                                                    row.is_active
                                                        ? 'rounded-full bg-emerald-500/10 px-2 py-0.5 text-emerald-600'
                                                        : 'rounded-full bg-muted px-2 py-0.5 text-muted-foreground'
                                                }
                                            >
                                                {row.is_active ? 'Đang chạy' : 'Tạm dừng'}
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
                                        Chưa có chiến dịch nào
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
