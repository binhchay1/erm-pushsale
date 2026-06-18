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
import { useT } from '@/providers/I18nProvider';

export default function LandingApprovals({ campaigns, highlightCampaignId }) {
    const t = useT();
    const rowRefs = useRef({});
    const { ask, ConfirmDialogPortal } = useConfirm();

    const approve = async (id, name) => {
        const ok = await ask({
            title: t('pages.landing.approve_title'),
            description: t('pages.landing.approve_desc', { name }),
            confirmLabel: t('pages.landing.approve'),
        });
        if (!ok) return;
        router.post(`/admin/landing-approvals/${id}/approve`, {}, { preserveScroll: true });
    };

    const copyUrl = async (url) => {
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
                                                <StatusBadge tone="success">{t('pages.approved')}</StatusBadge>
                                            ) : (
                                                <StatusBadge tone="warning">{t('pages.pending_approval')}</StatusBadge>
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

            <ConfirmDialogPortal />
        </AppLayout>
    );
}
