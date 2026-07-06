import { Head, Link, router } from '@inertiajs/react';
import { Activity, AlertTriangle, Clock, Inbox } from 'lucide-react';

import { StatCard } from '@/components/charts/StatCard';
import { PageHeader } from '@/components/layout/PageHeader';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { StatusBadge } from '@/components/ui/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout';
import { useTableSort } from '@/hooks/use-table-sort';
import { useT } from '@/providers/I18nProvider';
import { cn } from '@/lib/utils';

function eventTone(status) {
    if (status === 'processed') return 'success';
    if (status === 'failed' || status === 'rejected') return 'destructive';
    if (status === 'queued') return 'info';
    return 'warning';
}

function logTone(level) {
    if (level === 'ERROR' || level === 'CRITICAL') return 'destructive';
    if (level === 'WARNING') return 'warning';
    return 'muted';
}

export default function SystemMonitorIndex({ tab, events, logs, stats, filters, sources, statuses, logLevels }) {
    const t = useT();
    const pageRows = events.data ?? [];
    const { sortedRows, sort, toggleSort } = useTableSort(pageRows, { defaultKey: 'id', defaultDir: 'desc' });

    const setTab = (next) => {
        router.get('/admin/system-monitor', { ...filters, tab: next }, { preserveState: true });
    };

    const search = (overrides) => {
        router.get('/admin/system-monitor', { tab, ...filters, ...overrides }, { preserveState: true });
    };

    return (
        <AppLayout>
            <Head title={t('system_monitor.title')} />

            <div className="space-y-6">
                <PageHeader title={t('system_monitor.title')} description={t('system_monitor.desc')} />

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <StatCard icon={Inbox} title={t('system_monitor.stats_received_today')} value={stats.received_today} />
                    <StatCard icon={Activity} title={t('system_monitor.stats_processed_today')} value={stats.processed_today ?? 0} />
                    <StatCard icon={AlertTriangle} title={t('system_monitor.stats_failed_today')} value={stats.failed_today} />
                    <StatCard icon={Clock} title={t('system_monitor.stats_pending')} value={stats.pending} />
                </div>

                {stats.top_errors?.length > 0 && (
                    <Card className="border-destructive/30">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-base">{t('system_monitor.top_errors_title')}</CardTitle>
                            <CardDescription>{t('system_monitor.top_errors_desc')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            {stats.top_errors.map((row) => (
                                <div key={row.message} className="flex items-start justify-between gap-4 border-b border-border/60 pb-2 last:border-0">
                                    <span className="text-muted-foreground">{row.message}</span>
                                    <span className="shrink-0 font-semibold text-destructive">{row.count}</span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <div className="flex flex-wrap gap-1 rounded-lg border bg-muted/40 p-1">
                    {['events', 'logs'].map((id) => (
                        <button
                            key={id}
                            type="button"
                            onClick={() => setTab(id)}
                            className={cn(
                                'rounded-md px-4 py-2 text-sm font-medium transition-colors',
                                tab === id ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {t(`system_monitor.tab_${id}`)}
                        </button>
                    ))}
                </div>

                {tab === 'events' ? (
                    <Card>
                        <CardHeader className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <CardTitle>{t('system_monitor.tab_events')}</CardTitle>
                                <CardDescription>{t('system_monitor.desc')}</CardDescription>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <select
                                    className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                                    value={filters.source ?? ''}
                                    onChange={(e) => search({ source: e.target.value || undefined })}
                                >
                                    <option value="">{t('system_monitor.filter_source')}: {t('common.all')}</option>
                                    {sources.map((s) => (
                                        <option key={s.value} value={s.value}>{s.label}</option>
                                    ))}
                                </select>
                                <select
                                    className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                                    value={filters.status ?? ''}
                                    onChange={(e) => search({ status: e.target.value || undefined })}
                                >
                                    <option value="">{t('system_monitor.filter_status')}: {t('common.all')}</option>
                                    {statuses.map((s) => (
                                        <option key={s.value} value={s.value}>{s.label}</option>
                                    ))}
                                </select>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {sortedRows.length === 0 ? (
                                <p className="py-8 text-center text-sm text-muted-foreground">{t('system_monitor.no_events')}</p>
                            ) : (
                                <ScrollDataTable>
                                    <table className="w-full border-collapse text-xs">
                                        <thead>
                                            <tr>
                                                <Th sortable sortKey="id" sort={sort} onSort={toggleSort}>{t('system_monitor.col_id')}</Th>
                                                <Th sortable sortKey="created_at" sort={sort} onSort={toggleSort}>{t('system_monitor.col_time')}</Th>
                                                <Th sortable sortKey="source_label" sort={sort} onSort={toggleSort}>{t('system_monitor.col_source')}</Th>
                                                <Th sortable sortKey="channel" sort={sort} onSort={toggleSort}>{t('system_monitor.col_channel')}</Th>
                                                <Th sortable sortKey="status" sort={sort} onSort={toggleSort}>{t('system_monitor.col_status')}</Th>
                                                <Th sortable sortKey="company" sort={sort} onSort={toggleSort}>{t('system_monitor.col_company')}</Th>
                                                <Th sortable sortKey="ip_address" sort={sort} onSort={toggleSort}>{t('system_monitor.col_ip')}</Th>
                                                <Th>{t('system_monitor.col_error')}</Th>
                                                <Th />
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {sortedRows.map((row) => (
                                                <tr key={row.id} className="border-b border-border/60 hover:bg-muted/30">
                                                    <Td>{row.id}</Td>
                                                    <Td>{row.created_at}</Td>
                                                    <Td>{row.source_label}</Td>
                                                    <Td>{row.channel ?? '—'}</Td>
                                                    <Td>
                                                        <StatusBadge tone={eventTone(row.status)} label={row.status_label} />
                                                    </Td>
                                                    <Td>{row.company ?? '—'}</Td>
                                                    <Td className="font-mono text-[11px]">{row.ip_address ?? '—'}</Td>
                                                    <Td className="max-w-[12rem] truncate text-destructive">{row.error_message ?? '—'}</Td>
                                                    <Td>
                                                        <Button variant="ghost" size="sm" asChild>
                                                            <Link href={`/admin/system-monitor/events/${row.id}`}>{t('system_monitor.view_payload')}</Link>
                                                        </Button>
                                                    </Td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </ScrollDataTable>
                            )}
                            {events.links?.length > 3 && (
                                <div className="mt-4 flex flex-wrap gap-2">
                                    {events.links.map((link) => (
                                        <Button
                                            key={link.label}
                                            variant={link.active ? 'default' : 'outline'}
                                            size="sm"
                                            disabled={!link.url}
                                            onClick={() => link.url && router.get(link.url)}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardHeader className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <Activity className="size-4" />
                                    {t('system_monitor.tab_logs')}
                                </CardTitle>
                            </div>
                            <select
                                className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                                value={filters.level ?? ''}
                                onChange={(e) => search({ level: e.target.value || undefined })}
                            >
                                <option value="">{t('system_monitor.filter_level')}: {t('common.all')}</option>
                                {logLevels.map((lvl) => (
                                    <option key={lvl} value={lvl}>{lvl}</option>
                                ))}
                            </select>
                        </CardHeader>
                        <CardContent>
                            {logs.length === 0 ? (
                                <p className="py-8 text-center text-sm text-muted-foreground">{t('system_monitor.no_logs')}</p>
                            ) : (
                                <div className="max-h-[32rem] space-y-2 overflow-auto rounded-lg border bg-muted/20 p-3 font-mono text-[11px] leading-relaxed">
                                    {logs.map((row, i) => (
                                        <div key={i} className="border-b border-border/40 pb-2 last:border-0">
                                            <div className="mb-1 flex flex-wrap items-center gap-2">
                                                <span className="text-muted-foreground">{row.at}</span>
                                                <StatusBadge tone={logTone(row.level)} label={row.level} />
                                            </div>
                                            <pre className="whitespace-pre-wrap break-all text-foreground">{row.message}</pre>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
