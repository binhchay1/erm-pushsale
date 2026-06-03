import { Head, router } from '@inertiajs/react';
import { CheckCircle2, Clock, Copy } from 'lucide-react';
import { toast } from 'sonner';

import { PageHeader } from '@/components/layout/PageHeader';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { Button } from '@/components/ui/button';
import { copyToClipboard } from '@/lib/clipboard';
import AppLayout from '@/layouts/AppLayout';

export default function LandingApprovals({ campaigns }) {
    const approve = (id, name) => {
        if (!window.confirm(`Duyệt nguồn Landing "${name}"? Lead mới sẽ được chia số cho Sale.`)) return;
        router.post(`/admin/landing-approvals/${id}/approve`, {}, { preserveScroll: true });
    };

    const copyUrl = async (url) => {
        const ok = await copyToClipboard(url);
        ok ? toast.success('Đã copy URL') : toast.error('Không copy được');
    };

    const pending = campaigns.filter((c) => !c.is_approved);

    return (
        <AppLayout>
            <Head title="Duyệt kết nối Landing" />

            <div className="space-y-6">
                <PageHeader
                    title="Duyệt kết nối Landing"
                    description={
                        <>
                            Marketing tạo nguồn trên Ladipage → copy URL API. <strong>Chưa duyệt</strong> thì lead
                            test chỉ về Admin, <strong>không chia số Sale</strong> ({pending.length} chờ duyệt).
                        </>
                    }
                />

                <ScrollDataTable>
                    <table className="w-full min-w-[1000px] border-collapse text-xs">
                        <thead>
                            <tr>
                                <Th>Chiến dịch</Th>
                                <Th>Người tạo</Th>
                                <Th>Marketer</Th>
                                <Th>utm_campaign</Th>
                                <Th>Tạo lúc</Th>
                                <Th>Trạng thái</Th>
                                <Th />
                            </tr>
                        </thead>
                        <tbody>
                            {campaigns.length ? (
                                campaigns.map((row) => (
                                    <tr key={row.id} className="hover:bg-muted/30">
                                        <Td className="font-medium">{row.name}</Td>
                                        <Td>{row.creator ?? '—'}</Td>
                                        <Td>{row.marketer ?? '—'}</Td>
                                        <Td className="font-mono">{row.utm_campaign}</Td>
                                        <Td>{row.created_at}</Td>
                                        <Td>
                                            {row.is_approved ? (
                                                <span className="text-emerald-600">Đã duyệt</span>
                                            ) : (
                                                <span className="text-amber-600">Chờ duyệt</span>
                                            )}
                                        </Td>
                                        <Td>
                                            <div className="flex gap-1">
                                                {row.webhook_url && (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => copyUrl(row.webhook_url)}
                                                    >
                                                        <Copy className="size-3.5" />
                                                        URL
                                                    </Button>
                                                )}
                                                {!row.is_approved && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        onClick={() => approve(row.id, row.name)}
                                                    >
                                                        <CheckCircle2 className="size-3.5" />
                                                        Duyệt
                                                    </Button>
                                                )}
                                            </div>
                                        </Td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <Td colSpan={7} className="py-10 text-center text-muted-foreground">
                                        <Clock className="mx-auto mb-2 size-6 opacity-50" />
                                        Chưa có kết nối Landing nào
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
