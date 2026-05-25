import { Head, Link, router } from '@inertiajs/react';
import { Plug, Search } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';

const statusClass = {
    processed: 'text-emerald-600',
    pending: 'text-amber-600',
    duplicate: 'text-orange-600',
    failed: 'text-destructive',
};

export default function LeadsIndex({ leads, filters, platforms, statuses }) {
    const search = (overrides) => {
        router.get('/admin/leads', { ...filters, ...overrides }, { preserveState: true });
    };

    return (
        <AppLayout>
            <Head title="Nhật ký lead" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Nhật ký thu lead</h1>
                        <p className="text-sm text-muted-foreground">
                            Webhook / API → phân số telesale · Real-time qua Reverb
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href="/admin/integrations">
                            <Plug className="size-4" />
                            Cấu hình nền tảng
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-wrap gap-3 rounded-xl border bg-card p-4">
                    <select
                        className="h-8 rounded-lg border px-2 text-sm"
                        value={filters.platform ?? ''}
                        onChange={(e) => search({ platform: e.target.value || null })}
                    >
                        <option value="">— Nền tảng —</option>
                        {platforms.map((p) => (
                            <option key={p} value={p}>
                                {p}
                            </option>
                        ))}
                    </select>
                    <select
                        className="h-8 rounded-lg border px-2 text-sm"
                        value={filters.status ?? ''}
                        onChange={(e) => search({ status: e.target.value || null })}
                    >
                        <option value="">— Trạng thái —</option>
                        {statuses.map((s) => (
                            <option key={s.value} value={s.value}>
                                {s.label}
                            </option>
                        ))}
                    </select>
                    <Button size="sm" onClick={() => search()}>
                        <Search className="size-4" />
                        Lọc
                    </Button>
                </div>

                <ScrollDataTable>
                    <table className="w-full border-collapse text-xs">
                        <thead>
                            <tr>
                                <Th>ID</Th>
                                <Th>Thời gian</Th>
                                <Th>Nền tảng</Th>
                                <Th>Khách hàng</Th>
                                <Th>SĐT</Th>
                                <Th>Trạng thái</Th>
                                <Th>Mã đơn</Th>
                                <Th>Ghi chú</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {leads.data?.length ? (
                                leads.data.map((row) => (
                                    <tr key={row.id} className="hover:bg-muted/30">
                                        <Td>{row.id}</Td>
                                        <Td>{row.created_at}</Td>
                                        <Td className="font-medium">{row.platform}</Td>
                                        <Td>{row.customer_name ?? '—'}</Td>
                                        <Td className="font-mono">{row.customer_phone ?? '—'}</Td>
                                        <Td className={statusClass[row.status] ?? ''}>
                                            {row.status_label}
                                        </Td>
                                        <Td className="font-mono">{row.order_code ?? '—'}</Td>
                                        <Td className="max-w-xs truncate text-muted-foreground">
                                            {row.error_message ?? row.product_interest ?? '—'}
                                        </Td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <Td colSpan={8} className="py-8 text-center text-muted-foreground">
                                        Chưa có lead nào — bật webhook tại Tích hợp nền tảng
                                    </Td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </ScrollDataTable>

                {leads.links?.length > 3 && (
                    <div className="flex flex-wrap gap-2">
                        {leads.links.map((link) => (
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
            </div>
        </AppLayout>
    );
}
