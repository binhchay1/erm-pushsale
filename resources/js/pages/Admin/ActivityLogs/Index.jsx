import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Eye, ScrollText } from 'lucide-react';

import { PageHeader } from '@/components/layout/PageHeader';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTableSort } from '@/hooks/use-table-sort';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';
import { cn } from '@/lib/utils';

function DetailsBlock({ rows, emptyText }) {
    if (!rows || rows.length === 0) {
        return <p className="text-sm text-muted-foreground">{emptyText}</p>;
    }

    return (
        <dl className="divide-y divide-border/60 rounded-lg border">
            {rows.map((row, index) => (
                <div key={index} className="flex items-start justify-between gap-4 px-3 py-2">
                    <dt className="text-xs font-medium text-muted-foreground">{row.label}</dt>
                    <dd className="text-right text-sm font-medium">{row.value}</dd>
                </div>
            ))}
        </dl>
    );
}

export default function ActivityLogsIndex({ logs, filters, actionOptions, subjectTypeOptions, users }) {
    const t = useT();
    const rows = logs?.data ?? [];
    const meta = logs?.meta ?? {};
    const { sortedRows, sort, toggleSort } = useTableSort(rows, { defaultKey: 'id', defaultDir: 'desc' });
    const [selected, setSelected] = useState(null);

    const search = (overrides = {}) => {
        router.get('/admin/activity-logs', { ...filters, ...overrides }, { preserveState: true });
    };

    const goPage = (page) => search({ page });

    return (
        <AppLayout>
            <Head title={t('activity.title')} />

            <div className="space-y-6">
                <PageHeader title={t('activity.title')} description={t('activity.desc')} />

                <div className="grid gap-3 rounded-lg border p-4 md:grid-cols-3 xl:grid-cols-6">
                    <label className="space-y-1 text-xs">
                        <span className="font-medium text-muted-foreground">{t('activity.filter_action')}</span>
                        <select
                            className="h-9 w-full rounded-md border border-input bg-background px-2 text-sm"
                            value={filters.action ?? ''}
                            onChange={(e) => search({ action: e.target.value || undefined, page: 1 })}
                        >
                            <option value="">{t('activity.all')}</option>
                            {actionOptions.map((opt) => (
                                <option key={opt.value} value={opt.value}>{opt.label}</option>
                            ))}
                        </select>
                    </label>
                    <label className="space-y-1 text-xs">
                        <span className="font-medium text-muted-foreground">{t('activity.filter_user')}</span>
                        <select
                            className="h-9 w-full rounded-md border border-input bg-background px-2 text-sm"
                            value={filters.user_id ?? ''}
                            onChange={(e) => search({ user_id: e.target.value || undefined, page: 1 })}
                        >
                            <option value="">{t('activity.all')}</option>
                            {users.map((user) => (
                                <option key={user.id} value={user.id}>{user.name}</option>
                            ))}
                        </select>
                    </label>
                    <label className="space-y-1 text-xs">
                        <span className="font-medium text-muted-foreground">{t('activity.filter_subject')}</span>
                        <select
                            className="h-9 w-full rounded-md border border-input bg-background px-2 text-sm"
                            value={filters.subject_type ?? ''}
                            onChange={(e) => search({ subject_type: e.target.value || undefined, page: 1 })}
                        >
                            <option value="">{t('activity.all')}</option>
                            {subjectTypeOptions.map((opt) => (
                                <option key={opt.value} value={opt.value}>{opt.label}</option>
                            ))}
                        </select>
                    </label>
                    <label className="space-y-1 text-xs">
                        <span className="font-medium text-muted-foreground">{t('activity.filter_search')}</span>
                        <input
                            className="h-9 w-full rounded-md border border-input bg-background px-2 text-sm"
                            defaultValue={filters.search ?? ''}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    search({ search: e.currentTarget.value || undefined, page: 1 });
                                }
                            }}
                        />
                    </label>
                    <label className="space-y-1 text-xs">
                        <span className="font-medium text-muted-foreground">{t('activity.filter_date_from')}</span>
                        <input
                            type="date"
                            className="h-9 w-full rounded-md border border-input bg-background px-2 text-sm"
                            value={filters.date_from ?? ''}
                            onChange={(e) => search({ date_from: e.target.value || undefined, page: 1 })}
                        />
                    </label>
                    <label className="space-y-1 text-xs">
                        <span className="font-medium text-muted-foreground">{t('activity.filter_date_to')}</span>
                        <input
                            type="date"
                            className="h-9 w-full rounded-md border border-input bg-background px-2 text-sm"
                            value={filters.date_to ?? ''}
                            onChange={(e) => search({ date_to: e.target.value || undefined, page: 1 })}
                        />
                    </label>
                </div>

                <ScrollDataTable>
                    <table className="w-full min-w-[980px] border-collapse text-xs">
                        <thead>
                            <tr>
                                <Th sortable sortKey="created_at" sort={sort} onSort={toggleSort}>{t('activity.col_time')}</Th>
                                <Th sortable sortKey="action_label" sort={sort} onSort={toggleSort}>{t('activity.col_action')}</Th>
                                <Th>{t('activity.col_summary')}</Th>
                                <Th sortable sortKey="actor_name" sort={sort} onSort={toggleSort}>{t('activity.col_actor')}</Th>
                                <Th sortable sortKey="ip_address" sort={sort} onSort={toggleSort}>{t('activity.col_ip')}</Th>
                                <Th />
                            </tr>
                        </thead>
                        <tbody>
                            {sortedRows.length ? (
                                sortedRows.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="cursor-pointer hover:bg-muted/30"
                                        onClick={() => setSelected(row)}
                                    >
                                        <Td className="whitespace-nowrap">{row.created_at}</Td>
                                        <Td className="font-medium">{row.action_label}</Td>
                                        <Td className="max-w-[380px] whitespace-normal text-muted-foreground">
                                            {row.summary || row.subject_label || '—'}
                                        </Td>
                                        <Td>{row.actor_name}</Td>
                                        <Td className="font-mono text-[10px]">{row.ip_address ?? '—'}</Td>
                                        <Td>
                                            <Button type="button" variant="outline" size="sm" onClick={(e) => { e.stopPropagation(); setSelected(row); }}>
                                                <Eye className="size-3.5" />
                                                {t('activity.view_detail')}
                                            </Button>
                                        </Td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <Td colSpan={6} className="py-10 text-center text-muted-foreground">
                                        <ScrollText className="mx-auto mb-2 size-6 opacity-50" />
                                        {t('activity.empty')}
                                    </Td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </ScrollDataTable>

                {meta && (
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            {meta.total} · {meta.current_page}/{meta.last_page}
                        </span>
                        <div className="flex gap-2">
                            <Button type="button" variant="outline" size="sm" disabled={meta.current_page <= 1} onClick={() => goPage(meta.current_page - 1)}>
                                ←
                            </Button>
                            <Button type="button" variant="outline" size="sm" disabled={meta.current_page >= meta.last_page} onClick={() => goPage(meta.current_page + 1)}>
                                →
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            <Dialog open={!!selected} onOpenChange={(open) => !open && setSelected(null)}>
                <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
                    {selected && (
                        <>
                            <DialogHeader>
                                <DialogTitle>{selected.action_label}</DialogTitle>
                                <DialogDescription>{selected.summary || '—'}</DialogDescription>
                            </DialogHeader>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground">{t('activity.col_time')}</p>
                                    <p className="text-sm">{selected.created_at}</p>
                                </div>
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground">{t('activity.col_actor')}</p>
                                    <p className="text-sm">{selected.actor_name}</p>
                                </div>
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground">{t('activity.col_subject')}</p>
                                    <p className="text-sm">{selected.subject_label ?? '—'}</p>
                                </div>
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground">{t('activity.col_ip')}</p>
                                    <p className="text-sm font-mono">{selected.ip_address ?? '—'}</p>
                                </div>
                            </div>
                            <div className="space-y-2">
                                <p className="text-sm font-semibold">{t('activity.detail_properties')}</p>
                                <DetailsBlock rows={selected.details} emptyText={t('activity.detail_empty')} />
                            </div>
                        </>
                    )}
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setSelected(null)}>
                            {t('common.close')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
