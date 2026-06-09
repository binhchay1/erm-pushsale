import { useEffect, useRef } from 'react';
import { Head, router } from '@inertiajs/react';
import { CheckCircle2, Clock, Copy } from 'lucide-react';
import { toast } from 'sonner';

import { PageHeader } from '@/components/layout/PageHeader';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/ui/status-badge';
import { useConfirm } from '@/hooks/use-confirm';
import { copyToClipboard } from '@/lib/clipboard';
import { cn } from '@/lib/utils';
import AppLayout from '@/layouts/AppLayout';

export default function LandingApprovals({ campaigns, highlightCampaignId }) {
    const rowRefs = useRef({});
    const { ask, ConfirmDialogPortal } = useConfirm();

    const approve = async (id, name) => {
        const ok = await ask({
            title: 'Duyệt trang Landing',
            description: `Duyệt nguồn Landing "${name}"? Lead mới sẽ được chia số cho Sale.`,
            confirmLabel: 'Duyệt',
        });
        if (!ok) return;
        router.post(`/admin/landing-approvals/${id}/approve`, {}, { preserveScroll: true });
    };

    const copyUrl = async (url) => {
        const ok = await copyToClipboard(url);
        ok ? toast.success('Đã copy URL') : toast.error('Không copy được');
    };

    const pending = campaigns.filter((c) => !c.is_approved);

    useEffect(() => {
        if (!highlightCampaignId) return;
        const el = rowRefs.current[highlightCampaignId];
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, [highlightCampaignId, campaigns]);

    return (
        <AppLayout>
            <Head title="Duyệt trang Landing" />

            <div className="space-y-6">
                <PageHeader
                    title="Duyệt trang Landing"
                    description={
                        <>
                            Marketing tạo kết nối Landing và chờ duyệt tại đây. <strong>Chưa duyệt</strong>{' '}
                            thì lead thử chỉ về Admin, <strong>chưa chia cho Sale</strong> ({pending.length}{' '}
                            chờ duyệt).
                            {highlightCampaignId && (
                                <span className="mt-1 block text-primary">
                                    Đang hiển thị chiến dịch cần xét duyệt từ thông báo.
                                </span>
                            )}
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
                                    <tr
                                        key={row.id}
                                        ref={(el) => {
                                            rowRefs.current[row.id] = el;
                                        }}
                                        className={cn(
                                            'hover:bg-muted/30',
                                            highlightCampaignId === row.id &&
                                                'bg-primary/10 ring-2 ring-inset ring-primary',
                                        )}
                                    >
                                        <Td className="font-medium">{row.name}</Td>
                                        <Td>{row.creator ?? '—'}</Td>
                                        <Td>{row.marketer ?? '—'}</Td>
                                        <Td className="font-mono">{row.utm_campaign}</Td>
                                        <Td>{row.created_at}</Td>
                                        <Td>
                                            {row.is_approved ? (
                                                <StatusBadge tone="success">Đã duyệt</StatusBadge>
                                            ) : (
                                                <StatusBadge tone="warning">Chờ duyệt</StatusBadge>
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

            <ConfirmDialogPortal />
        </AppLayout>
    );
}
