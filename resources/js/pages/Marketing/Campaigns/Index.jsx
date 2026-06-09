import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, Clock, Copy, Pencil, Plus, Target, Trash2 } from 'lucide-react';
import { toast } from 'sonner';

import { PageHeader } from '@/components/layout/PageHeader';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/hooks/use-confirm';
import { formatCurrency } from '@/lib/format';
import { copyToClipboard } from '@/lib/clipboard';
import AppLayout from '@/layouts/AppLayout';

export default function CampaignIndex({ campaigns }) {
    const { ask, ConfirmDialogPortal } = useConfirm();

    const remove = async (id, name) => {
        const ok = await ask({
            title: 'Xóa kết nối Landing',
            description: `Xóa kết nối Landing "${name}"?`,
            confirmLabel: 'Xóa',
            variant: 'destructive',
        });
        if (!ok) return;
        router.delete(`/marketing/campaigns/${id}`);
    };

    const copyUrl = async (url) => {
        const ok = await copyToClipboard(url);
        ok ? toast.success('Đã copy đường dẫn nhận lead') : toast.error('Không copy được');
    };

    return (
        <AppLayout>
            <Head title="Trang Landing" />

            <div className="space-y-6">
                <PageHeader
                    title="Trang Landing (Ladipage)"
                    description={
                        <>
                            Tạo kết nối → hệ thống cấp <strong>đường dẫn nhận lead riêng</strong> → dán vào
                            Ladipage → Admin duyệt → lead tự chia cho Sale.
                        </>
                    }
                    actions={
                        <Button asChild>
                            <Link href="/marketing/campaigns/create">
                                <Plus className="size-4" />
                                Thêm kết nối
                            </Link>
                        </Button>
                    }
                />

                <ScrollDataTable>
                    <table className="w-full min-w-[1100px] border-collapse text-xs">
                        <thead>
                            <tr>
                                <Th>Chiến dịch</Th>
                                <Th>Đường dẫn nhận lead</Th>
                                <Th>Mã chiến dịch</Th>
                                <Th>Sản phẩm</Th>
                                <Th>Đơn / DT</Th>
                                <Th>Duyệt</Th>
                                <Th>Thao tác</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {campaigns.length ? (
                                campaigns.map((row) => (
                                    <tr key={row.id} className="hover:bg-muted/30">
                                        <Td className="font-medium">{row.name}</Td>
                                        <Td>
                                            {row.webhook_url ? (
                                                <div className="flex max-w-xs items-center gap-1">
                                                    <span className="truncate font-mono text-[10px] text-primary">
                                                        {row.webhook_url}
                                                    </span>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        onClick={() => copyUrl(row.webhook_url)}
                                                    >
                                                        <Copy className="size-3.5" />
                                                    </Button>
                                                </div>
                                            ) : (
                                                '—'
                                            )}
                                        </Td>
                                        <Td className="font-mono">{row.utm_campaign}</Td>
                                        <Td>{row.product ?? '—'}</Td>
                                        <Td className="text-right">
                                            {row.orders_count} / {formatCurrency(row.revenue)}
                                        </Td>
                                        <Td>
                                            {row.is_approved ? (
                                                <span className="inline-flex items-center gap-1 text-emerald-600">
                                                    <CheckCircle2 className="size-3.5" />
                                                    Đã duyệt
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center gap-1 text-amber-600">
                                                    <Clock className="size-3.5" />
                                                    Chờ duyệt
                                                </span>
                                            )}
                                        </Td>
                                        <Td>
                                            <div className="flex gap-1">
                                                <Button variant="outline" size="icon-sm" asChild>
                                                    <Link href={`/marketing/campaigns/${row.id}/edit`}>
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
                                    <Td colSpan={7} className="py-10 text-center text-muted-foreground">
                                        <Target className="mx-auto mb-2 size-6 opacity-50" />
                                        Chưa có kết nối Landing — bấm Thêm kết nối
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
