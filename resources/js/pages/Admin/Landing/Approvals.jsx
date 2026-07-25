import { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { CheckCircle2, Clock, Copy, Eye, XCircle } from 'lucide-react';
import { toast } from 'sonner';

import { CampaignApprovalDetailDialog } from '@/components/marketing/CampaignApprovalDetailDialog';
import { PageHeader } from '@/components/layout/PageHeader';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/ui/status-badge';
import { useConfirm } from '@/hooks/use-confirm';
import { useTableSort } from '@/hooks/use-table-sort';
import { copyToClipboard } from '@/lib/clipboard';
import { cn } from '@/lib/utils';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

export default function LandingApprovals({
    campaigns,
    products = [],
    highlightCampaignId,
    fieldMapping,
    approveBaseUrl = '/admin/marketing/landing-approvals',
}) {
    const t = useT();
    const rowRefs = useRef({});
    const { ask, ConfirmDialogPortal } = useConfirm();
    const [selectedCampaign, setSelectedCampaign] = useState(null);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [approving, setApproving] = useState(false);
    const [rejecting, setRejecting] = useState(false);

    const openDetail = (campaign) => {
        setSelectedCampaign(campaign);
        setDialogOpen(true);
    };

    const approve = async (campaign, productId = null) => {
        const ok = await ask({
            title: t('pages.landing.approve_title'),
            description: t('pages.landing.approve_desc', { name: campaign.name }),
            confirmLabel: t('pages.landing.approve'),
        });
        if (!ok) return;

        setApproving(true);
        router.post(
            `${approveBaseUrl}/${campaign.id}/approve`,
            productId ? { product_id: productId } : {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDialogOpen(false);
                    setSelectedCampaign(null);
                    toast.success(t('pages.landing.approve_success', { name: campaign.name }));
                },
                onError: (errors) => {
                    toast.error(errors.campaign ?? errors.message ?? t('pages.landing.approve_failed'));
                },
                onFinish: () => setApproving(false),
            },
        );
    };

    const reject = async (campaign, reason) => {
        setRejecting(true);
        router.post(
            `${approveBaseUrl}/${campaign.id}/reject`,
            { reason },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDialogOpen(false);
                    setSelectedCampaign(null);
                    toast.success(t('pages.landing.reject_success'));
                },
                onError: (errors) => {
                    toast.error(errors.reason ?? errors.campaign ?? errors.message ?? t('pages.landing.reject_failed'));
                },
                onFinish: () => setRejecting(false),
            },
        );
    };

    const copyUrl = async (url, e) => {
        e?.stopPropagation();
        const ok = await copyToClipboard(url);
        ok ? toast.success(t('pages.landing.copy_url_success')) : toast.error(t('pages.landing.copy_url_failed'));
    };

    const pending = campaigns.filter((c) => !c.is_approved && !c.rejected_at);
    const { sortedRows, sort, toggleSort } = useTableSort(campaigns, { defaultKey: 'created_at', defaultDir: 'desc' });

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
        <AppLayout activeMenuCode="2.4.3">
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
                    <table className="w-full min-w-[1100px] border-collapse text-xs">
                        <thead>
                            <tr>
                                <Th sortable sortKey="name" sort={sort} onSort={toggleSort}>{t('pages.landing.col_campaign')}</Th>
                                <Th sortable sortKey="creator" sort={sort} onSort={toggleSort}>{t('pages.landing.col_creator')}</Th>
                                <Th sortable sortKey="marketer" sort={sort} onSort={toggleSort}>{t('pages.landing.col_marketer')}</Th>
                                <Th sortable sortKey="utm_campaign" sort={sort} onSort={toggleSort}>utm_campaign</Th>
                                <Th sortable sortKey="created_at" sort={sort} onSort={toggleSort}>{t('pages.landing.col_created')}</Th>
                                <Th sortable sortKey="is_approved" sort={sort} onSort={toggleSort}>{t('pages.landing.col_status')}</Th>
                                <Th />
                            </tr>
                        </thead>
                        <tbody>
                            {sortedRows.length ? (
                                sortedRows.map((row) => (
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
                                            <div className="flex flex-wrap items-center gap-1">
                                                {row.is_approved ? (
                                                    <StatusBadge tone="success">{t('pages.approved')}</StatusBadge>
                                                ) : row.rejected_at ? (
                                                    <StatusBadge tone="destructive">{t('pages.landing.reject')}</StatusBadge>
                                                ) : (
                                                    <StatusBadge tone="warning">{t('pages.pending_approval')}</StatusBadge>
                                                )}
                                                {!row.is_approved && row.missing_product && (
                                                    <StatusBadge tone="danger" title={t('pages.landing.incomplete_hint')}>
                                                        {t('pages.landing.incomplete_badge')}
                                                    </StatusBadge>
                                                )}
                                            </div>
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
                                                {!row.is_approved && !row.rejected_at && (
                                                    <>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            onClick={() => (row.missing_product ? openDetail(row) : approve(row))}
                                                        >
                                                            <CheckCircle2 className="size-3.5" />
                                                            {t('pages.landing.approve')}
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="destructive"
                                                            onClick={() => openDetail(row)}
                                                        >
                                                            <XCircle className="size-3.5" />
                                                            {t('pages.landing.reject')}
                                                        </Button>
                                                    </>
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

            <CampaignApprovalDetailDialog
                campaign={selectedCampaign}
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                fieldMapping={fieldMapping}
                products={products}
                onApprove={approve}
                onReject={reject}
                approving={approving}
                rejecting={rejecting}
            />

            <ConfirmDialogPortal />
        </AppLayout>
    );
}
