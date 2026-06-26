import { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { CheckCircle2, Clock, Copy, Eye } from 'lucide-react';
import { toast } from 'sonner';

import { CampaignApprovalDetailModal } from '@/components/marketing/CampaignApprovalDetailModal';
import { PageHeader } from '@/components/layout/PageHeader';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/ui/status-badge';
import { useConfirm } from '@/hooks/use-confirm';
import { copyToClipboard } from '@/lib/clipboard';
import { cn } from '@/lib/utils';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

export default function LandingApprovals({ campaigns, highlightCampaignId, fieldMapping }) {
    const t = useT();
    const rowRefs = useRef({});
    const { ask, ConfirmDialogPortal } = useConfirm();
    const [selectedCampaign, setSelectedCampaign] = useState(null);
    const [modalOpen, setModalOpen] = useState(false);
    const [approving, setApproving] = useState(false);

    const openDetail = (campaign) => {
        setSelectedCampaign(campaign);
        setModalOpen(true);
    };

    const approve = async (campaign) => {
        const ok = await ask({
            title: t('pages.landing.approve_title'),
            description: t('pages.landing.approve_desc', { name: campaign.name }),
            confirmLabel: t('pages.landing.approve'),
        });
        if (!ok) return;

        setApproving(true);
        router.post(
            `/admin/landing-approvals/${campaign.id}/approve`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setModalOpen(false);
                    setSelectedCampaign(null);
                },
                onFinish: () => setApproving(false),
            },
        );
    };

    const copyUrl = async (url, e) => {
        e?.stopPropagation();
        const ok = await copyToClipboard(url);
        ok ? toast.success(t('pages.landing.copy_url_success')) : toast.error(t('pages.landing.copy_url_failed'));
    };

    const pending = campaigns.filter((c) => !c.is_approved);

    useEffect(() => {
        if (!highlightCampaignId) return;
        const el = rowRefs.current[highlightCampaignId];
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        const campaign = campaigns.find((c) => c.id === highlightCampaignId);
        if (campaign) {
            openDetail(campaign);
        }
    }, [highlightCampaignId, campaigns]);

    return (
        <AppLayout>
            <Head title={t('pages.landing.title')} />

            <div className="space-y-6">
                <PageHeader
                    title={t('pages.landing.title')}
                    description={
                        <>
                            {t('pages.landing.desc_detail', { count: pending.length })}
                            <span className="mt-1 block text-muted-foreground">{t('pages.landing.click_row_hint')}</span>
                            {highlightCampaignId && (
                                <span className="mt-1 block text-primary">{t('pages.landing.highlight_hint')}</span>
                            )}
                        </>
                    }
                />

                <ScrollDataTable>
                    <table className="w-full min-w-[1000px] border-collapse text-xs">
                        <thead>
                            <tr>
                                <Th>{t('pages.landing.col_campaign')}</Th>
                                <Th>{t('pages.landing.col_creator')}</Th>
                                <Th>{t('pages.landing.col_marketer')}</Th>
                                <Th>utm_campaign</Th>
                                <Th>{t('pages.landing.col_created')}</Th>
                                <Th>{t('pages.landing.col_status')}</Th>
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
                                        onClick={() => openDetail(row)}
                                        className={cn(
                                            'cursor-pointer hover:bg-muted/30',
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
                                                <StatusBadge tone="success">{t('pages.approved')}</StatusBadge>
                                            ) : (
                                                <StatusBadge tone="warning">{t('pages.pending_approval')}</StatusBadge>
                                            )}
                                        </Td>
                                        <Td>
                                            <div className="flex gap-1" onClick={(e) => e.stopPropagation()}>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => openDetail(row)}
                                                >
                                                    <Eye className="size-3.5" />
                                                    {t('pages.landing.view_detail')}
                                                </Button>
                                                {row.webhook_url && (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={(e) => copyUrl(row.webhook_url, e)}
                                                    >
                                                        <Copy className="size-3.5" />
                                                        URL
                                                    </Button>
                                                )}
                                                {!row.is_approved && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        onClick={() => approve(row)}
                                                    >
                                                        <CheckCircle2 className="size-3.5" />
                                                        {t('pages.landing.approve')}
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
                                        {t('pages.landing.empty_landing')}
                                    </Td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </ScrollDataTable>
            </div>

            <CampaignApprovalDetailModal
                campaign={selectedCampaign}
                open={modalOpen}
                onOpenChange={setModalOpen}
                fieldMapping={fieldMapping}
                onApprove={approve}
                approving={approving}
            />

            <ConfirmDialogPortal />
        </AppLayout>
    );
}
