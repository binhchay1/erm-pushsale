import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { PageHeader } from '@/components/layout/PageHeader';
import { StatusBadge } from '@/components/ui/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

export default function SystemMonitorShow({ event }) {
    const t = useT();

    return (
        <AppLayout>
            <Head title={`${t('system_monitor.payload_title')} #${event.id}`} />

            <div className="space-y-6">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href="/admin/system-monitor">
                            <ArrowLeft className="size-4" />
                            {t('system_monitor.back')}
                        </Link>
                    </Button>
                </div>

                <PageHeader title={`${t('system_monitor.payload_title')} #${event.id}`} />

                <Card>
                    <CardHeader>
                        <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                            <StatusBadge tone="info" label={event.source_label} />
                            <StatusBadge tone="muted" label={event.status_label} />
                            {event.channel && <span className="text-sm text-muted-foreground">{event.channel}</span>}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm">
                        <dl className="grid gap-2 sm:grid-cols-2">
                            <div><dt className="text-muted-foreground">{t('system_monitor.col_time')}</dt><dd>{event.created_at}</dd></div>
                            <div><dt className="text-muted-foreground">{t('system_monitor.col_company')}</dt><dd>{event.company ?? '—'}</dd></div>
                            <div><dt className="text-muted-foreground">{t('system_monitor.col_ip')}</dt><dd className="font-mono">{event.ip_address ?? '—'}</dd></div>
                            <div><dt className="text-muted-foreground">{t('system_monitor.col_correlation')}</dt><dd className="font-mono text-xs">{event.correlation_id}</dd></div>
                            {event.error_message && (
                                <div className="sm:col-span-2">
                                    <dt className="text-destructive">{t('system_monitor.col_error')}</dt>
                                    <dd className="text-destructive">{event.error_message}</dd>
                                </div>
                            )}
                        </dl>

                        <div>
                            <h3 className="mb-2 font-medium">{t('system_monitor.headers')}</h3>
                            <pre className="max-h-48 overflow-auto rounded-lg border bg-muted/30 p-3 font-mono text-xs">
                                {JSON.stringify(event.headers, null, 2)}
                            </pre>
                        </div>

                        <div>
                            <h3 className="mb-2 font-medium">{t('system_monitor.payload')}</h3>
                            <pre className="max-h-[28rem] overflow-auto rounded-lg border bg-muted/30 p-3 font-mono text-xs">
                                {JSON.stringify(event.payload, null, 2)}
                            </pre>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
