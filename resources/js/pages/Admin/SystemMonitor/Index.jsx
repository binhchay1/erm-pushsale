import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    Clock,
    Cpu,
    Database,
    HardDrive,
    Inbox,
    MemoryStick,
    RefreshCcw,
    Server,
    ShieldCheck,
    Terminal,
    XCircle,
} from 'lucide-react';

import { StatCard } from '@/components/charts/StatCard';
import { PageHeader } from '@/components/layout/PageHeader';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { StatusBadge } from '@/components/ui/status-badge';
import { useTableSort } from '@/hooks/use-table-sort';
import AppLayout from '@/layouts/AppLayout';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import { useT } from '@/providers/I18nProvider';

function eventTone(status) {
    if (status === 'processed') return 'success';
    if (status === 'failed' || status === 'rejected') return 'danger';
    if (status === 'queued') return 'info';
    return 'warning';
}

function healthTone(status) {
    if (status === 'ok' || status === 'pass') return 'success';
    if (status === 'critical' || status === 'fail') return 'danger';
    if (status === 'warning') return 'warning';
    return 'muted';
}

function logTone(level) {
    if (level === 'ERROR' || level === 'CRITICAL') return 'danger';
    if (level === 'WARNING') return 'warning';
    return 'muted';
}

function PercentBar({ value, status = 'ok' }) {
    const safe = Math.max(0, Math.min(100, Number(value ?? 0)));
    return (
        <div className="space-y-1">
            <div className="h-2 overflow-hidden rounded-full bg-muted">
                <div
                    className={cn(
                        'h-full rounded-full transition-all',
                        status === 'critical' ? 'bg-destructive' : status === 'warning' ? 'bg-amber-500' : 'bg-emerald-500',
                    )}
                    style={{ width: `${safe}%` }}
                />
            </div>
            <div className="text-xs text-muted-foreground">{value == null ? 'Đang lấy mẫu' : `${safe}%`}</div>
        </div>
    );
}

function HealthCard({ title, icon: Icon, children, status = 'ok', description }) {
    return (
        <Card className={cn(status === 'critical' && 'border-destructive/40', status === 'warning' && 'border-amber-400/50')}>
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center justify-between gap-3 text-base">
                    <span className="flex items-center gap-2">
                        <Icon className="size-4 text-primary" />
                        {title}
                    </span>
                    <StatusBadge tone={healthTone(status)}>{status?.toUpperCase?.() ?? 'OK'}</StatusBadge>
                </CardTitle>
                {description && <CardDescription>{description}</CardDescription>}
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

function InfoRow({ label, value }) {
    return (
        <div className="flex items-start justify-between gap-4 border-b border-border/50 py-2 text-sm last:border-0">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-right font-medium">{value ?? '—'}</span>
        </div>
    );
}

function OverviewTab({ system }) {
    if (!system) return null;

    return (
        <div className="space-y-6">
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <StatCard icon={Cpu} title="CPU" value={system.cpu?.usage_percent == null ? `${system.cpu?.cores ?? 0} core` : `${system.cpu.usage_percent}%`} />
                <StatCard icon={MemoryStick} title="RAM" value={`${system.memory?.percent ?? 0}%`} />
                <StatCard icon={HardDrive} title="Disk storage" value={`${system.disks?.[0]?.percent ?? 0}%`} />
                <StatCard icon={Activity} title="Queue pending" value={formatNumber(system.queues?.pending_total ?? 0)} />
            </div>

            <div className="grid gap-4 xl:grid-cols-3">
                <HealthCard title="CPU / Load" icon={Cpu} status={system.cpu?.status}>
                    <div className="space-y-3">
                        <InfoRow label="CPU" value={system.cpu?.model} />
                        <InfoRow label="Số core" value={system.cpu?.cores} />
                        <InfoRow label="Load 1/5/15 phút" value={`${system.cpu?.load_1} / ${system.cpu?.load_5} / ${system.cpu?.load_15}`} />
                        <PercentBar value={system.cpu?.usage_percent ?? system.cpu?.load_percent} status={system.cpu?.status} />
                    </div>
                </HealthCard>

                <HealthCard title="RAM / Swap" icon={MemoryStick} status={system.memory?.status}>
                    <div className="space-y-3">
                        <InfoRow label="Đã dùng" value={`${system.memory?.used_human} / ${system.memory?.total_human}`} />
                        <InfoRow label="Còn trống" value={system.memory?.available_human} />
                        <PercentBar value={system.memory?.percent} status={system.memory?.status} />
                        <InfoRow label="Swap" value={`${system.memory?.swap_used_human} / ${system.memory?.swap_total_human} (${system.memory?.swap_percent}%)`} />
                    </div>
                </HealthCard>

                <HealthCard title="Host / Runtime" icon={Server} status={system.summary?.status}>
                    <div className="space-y-1">
                        <InfoRow label="Host" value={system.host?.hostname} />
                        <InfoRow label="OS" value={system.host?.os} />
                        <InfoRow label="Uptime" value={system.host?.uptime_human} />
                        <InfoRow label="Laravel / PHP" value={`${system.runtime?.laravel} / ${system.runtime?.php}`} />
                        <InfoRow label="APP_ENV / DEBUG" value={`${system.runtime?.app_env} / ${system.runtime?.app_debug ? 'ON' : 'OFF'}`} />
                    </div>
                </HealthCard>
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base"><HardDrive className="size-4" /> Disk</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {system.disks?.map((disk) => (
                            <div key={disk.path} className="rounded-lg border p-3">
                                <div className="mb-2 flex items-center justify-between gap-4 text-sm">
                                    <div>
                                        <div className="font-semibold">{disk.label}</div>
                                        <div className="text-xs text-muted-foreground">{disk.path}</div>
                                    </div>
                                    <div className="text-right text-xs text-muted-foreground">{disk.used_human} / {disk.total_human}</div>
                                </div>
                                <PercentBar value={disk.percent} status={disk.status} />
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base"><ShieldCheck className="size-4" /> Health checks</CardTitle>
                        <CardDescription>Các kiểm tra runtime quan trọng, không cần SSH vào server.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {system.checks?.map((check) => (
                            <div key={check.key} className="flex items-start justify-between gap-4 rounded-lg border p-3 text-sm">
                                <div>
                                    <div className="font-semibold">{check.label}</div>
                                    <div className="mt-1 text-xs text-muted-foreground">{check.message}</div>
                                </div>
                                <StatusBadge tone={healthTone(check.status)}>{check.status.toUpperCase()}</StatusBadge>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2 text-base"><Terminal className="size-4" /> Services / Processes</CardTitle>
                    <CardDescription>Nhận diện theo process đang chạy: nginx, php-fpm, mysql, redis, supervisor, queue worker, reverb, node.</CardDescription>
                </CardHeader>
                <CardContent>
                    <ScrollDataTable>
                        <table className="w-full border-collapse text-xs">
                            <thead>
                                <tr>
                                    <Th>Dịch vụ</Th>
                                    <Th>Trạng thái</Th>
                                    <Th>Process</Th>
                                    <Th>Ví dụ command</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {system.services?.map((service) => (
                                    <tr key={service.key}>
                                        <Td className="font-medium">{service.label}</Td>
                                        <Td><StatusBadge tone={service.running ? 'success' : 'warning'}>{service.running ? 'RUNNING' : 'NOT FOUND'}</StatusBadge></Td>
                                        <Td>{service.count}</Td>
                                        <Td className="max-w-[34rem] whitespace-normal font-mono text-[11px] text-muted-foreground">{service.examples?.[0]?.cmd ?? '—'}</Td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </ScrollDataTable>
                </CardContent>
            </Card>
        </div>
    );
}

function QueueTab({ system }) {
    if (!system) return null;
    return (
        <div className="space-y-6">
            <div className="grid gap-4 md:grid-cols-3">
                <StatCard icon={Activity} title="Pending jobs" value={formatNumber(system.queues?.pending_total ?? 0)} />
                <StatCard icon={AlertTriangle} title="Failed jobs" value={system.queues?.failed_total ?? '—'} />
                <StatCard icon={Server} title="Queue workers" value={system.queues?.workers ?? 0} />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Queue lanes</CardTitle>
                    <CardDescription>Webhook, shipping, tin nhắn, notification và report nên chạy bằng worker riêng.</CardDescription>
                </CardHeader>
                <CardContent>
                    <ScrollDataTable>
                        <table className="w-full border-collapse text-xs">
                            <thead>
                                <tr>
                                    <Th>Queue</Th>
                                    <Th>Pending</Th>
                                    <Th>Job cũ nhất</Th>
                                    <Th>Gợi ý worker</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {(system.queues?.expected_queues ?? []).map((queue) => {
                                    const row = system.queues?.queues?.find((item) => item.queue === queue);
                                    return (
                                        <tr key={queue}>
                                            <Td className="font-mono font-semibold">{queue}</Td>
                                            <Td>{formatNumber(row?.count ?? 0)}</Td>
                                            <Td>{row?.oldest_human ?? '—'}</Td>
                                            <Td className="font-mono text-[11px] text-muted-foreground">php artisan queue:work --queue={queue} --tries=3</Td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </ScrollDataTable>
                </CardContent>
            </Card>
        </div>
    );
}

function ReportsAuditTab({ reportAudit }) {
    if (!reportAudit) return null;

    return (
        <Card>
            <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <CardTitle className="flex items-center gap-2">
                        {reportAudit.status === 'pass' ? <CheckCircle2 className="size-4 text-emerald-500" /> : <XCircle className="size-4 text-destructive" />}
                        Đối soát báo cáo theo bản ghi gốc
                    </CardTitle>
                    <CardDescription>
                        Kỳ kiểm tra {reportAudit.date_range?.from} → {reportAudit.date_range?.to}, generated {reportAudit.generated_at}.
                    </CardDescription>
                </div>
                <StatusBadge tone={healthTone(reportAudit.status)}>{reportAudit.status?.toUpperCase()}</StatusBadge>
            </CardHeader>
            <CardContent>
                <ScrollDataTable>
                    <table className="w-full border-collapse text-xs">
                        <thead>
                            <tr>
                                <Th>Role</Th>
                                <Th>Báo cáo / Dashboard</Th>
                                <Th className="text-right">Source-of-truth</Th>
                                <Th className="text-right">Đang hiển thị</Th>
                                <Th className="text-right">Lệch</Th>
                                <Th>Trạng thái</Th>
                                <Th>Ghi chú</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {reportAudit.rows?.map((row, index) => (
                                <tr key={`${row.role}-${row.report}-${index}`}>
                                    <Td className="font-semibold uppercase">{row.role}</Td>
                                    <Td>{row.report}</Td>
                                    <Td className="text-right">{formatNumber(row.expected)}</Td>
                                    <Td className="text-right">{formatNumber(row.actual)}</Td>
                                    <Td className={cn('text-right font-semibold', row.diff !== 0 && 'text-destructive')}>{formatNumber(row.diff)}</Td>
                                    <Td><StatusBadge tone={healthTone(row.status)}>{row.status.toUpperCase()}</StatusBadge></Td>
                                    <Td className="max-w-[26rem] whitespace-normal text-muted-foreground">{row.detail}</Td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </ScrollDataTable>
            </CardContent>
        </Card>
    );
}

export default function SystemMonitorIndex({ tab, events, logs, stats, filters, sources, statuses, logLevels, system, reportAudit }) {
    const t = useT();
    const pageRows = events.data ?? [];
    const { sortedRows, sort, toggleSort } = useTableSort(pageRows, { defaultKey: 'id', defaultDir: 'desc' });

    const setTab = (next) => {
        router.get('/admin/system-monitor', { ...filters, tab: next }, { preserveState: true });
    };

    const search = (overrides) => {
        router.get('/admin/system-monitor', { tab, ...filters, ...overrides }, { preserveState: true });
    };

    const tabs = [
        ['overview', 'Tổng quan hệ thống'],
        ['queues', 'Queue workers'],
        ['reports', 'Đối soát báo cáo'],
        ['events', t('system_monitor.tab_events')],
        ['logs', t('system_monitor.tab_logs')],
    ];

    return (
        <AppLayout>
            <Head title={t('system_monitor.title')} />

            <div className="space-y-6">
                <PageHeader
                    title={t('system_monitor.title')}
                    description="Giám sát CPU, RAM, disk, process, queue, webhook, log lỗi và độ nhất quán báo cáo."
                    actions={(
                        <Button variant="outline" size="sm" onClick={() => router.reload({ preserveScroll: true })}>
                            <RefreshCcw className="mr-2 size-4" /> Làm mới
                        </Button>
                    )}
                />

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
                    {tabs.map(([id, label]) => (
                        <button
                            key={id}
                            type="button"
                            onClick={() => setTab(id)}
                            className={cn(
                                'rounded-md px-4 py-2 text-sm font-medium transition-colors',
                                tab === id ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                {tab === 'overview' && <OverviewTab system={system} />}
                {tab === 'queues' && <QueueTab system={system} />}
                {tab === 'reports' && <ReportsAuditTab reportAudit={reportAudit} />}

                {tab === 'events' && (
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
                                                    <Td><StatusBadge tone={eventTone(row.status)}>{row.status_label}</StatusBadge></Td>
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
                )}

                {tab === 'logs' && (
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
                                                <StatusBadge tone={logTone(row.level)}>{row.level}</StatusBadge>
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
