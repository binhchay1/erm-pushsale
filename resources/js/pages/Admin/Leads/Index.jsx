import { Head, Link, router } from '@inertiajs/react';
import { Plug, Search, UserPlus } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import { DeleteRowButton } from '@/components/ui/delete-row-button';
import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';

const statusClass = {
    processed: 'text-emerald-600',
    pending: 'text-amber-600',
    duplicate: 'text-orange-600',
    failed: 'text-destructive',
};

export default function LeadsIndex({
    leads,
    filters,
    platforms,
    statuses,
    salesUsers = [],
    allocateUrl = '/admin/leads/allocate',
    deleteUrlPrefix = '/admin/leads',
    listUrl = '/admin/leads',
    canDelete = true,
}) {
    const [selected, setSelected] = useState([]);
    const [saleUserId, setSaleUserId] = useState('');
    const [allocating, setAllocating] = useState(false);

    const search = (overrides) => {
        router.get(listUrl, { ...filters, ...overrides }, { preserveState: true });
    };

    const toggleRow = (id) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
    };

    const toggleAllPending = () => {
        const pendingOnPage = (leads.data ?? []).filter((r) => r.status === 'pending').map((r) => r.id);
        const allSelected = pendingOnPage.length > 0 && pendingOnPage.every((id) => selected.includes(id));
        setSelected(allSelected ? selected.filter((id) => !pendingOnPage.includes(id)) : [...new Set([...selected, ...pendingOnPage])]);
    };

    const allocate = () => {
        if (!selected.length) {
            toast.error('Chọn ít nhất một lead ở trạng thái Chờ xử lý.');
            return;
        }
        if (!saleUserId) {
            toast.error('Chọn nhân viên telesale.');
            return;
        }

        setAllocating(true);
        router.post(
            allocateUrl,
            { lead_ids: selected, sale_user_id: saleUserId },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelected([]);
                    setSaleUserId('');
                    toast.success('Đã phân bổ lead.');
                },
                onError: (errors) => {
                    toast.error(errors.lead_ids ?? errors.sale_user_id ?? 'Không phân bổ được lead.');
                },
                onFinish: () => setAllocating(false),
            },
        );
    };

    const pendingOnPage = (leads.data ?? []).filter((r) => r.status === 'pending');
    const allPendingSelected =
        pendingOnPage.length > 0 && pendingOnPage.every((r) => selected.includes(r.id));

    return (
        <AppLayout>
            <Head title="Nhật ký lead" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Nhật ký thu lead</h1>
                        <p className="text-sm text-muted-foreground">
                            Webhook / API → phân số telesale · Chia số thủ công khi hệ thống chưa tự phân
                        </p>
                    </div>
                    {canDelete && (
                        <Button variant="outline" asChild>
                            <Link href="/admin/integrations">
                                <Plug className="size-4" />
                                Cấu hình nền tảng
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="flex flex-wrap items-end gap-3 rounded-xl border border-amber-200/80 bg-amber-50/50 p-4 dark:border-amber-900/40 dark:bg-amber-950/20">
                    <div className="min-w-[200px] flex-1 space-y-1">
                        <p className="text-xs font-medium text-muted-foreground">Chia số thủ công</p>
                        <select
                            className="h-9 w-full max-w-xs rounded-lg border bg-background px-2 text-sm"
                            value={saleUserId}
                            onChange={(e) => setSaleUserId(e.target.value)}
                        >
                            <option value="">— Chọn telesale —</option>
                            {salesUsers.map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <Button size="sm" onClick={allocate} disabled={allocating || !selected.length}>
                        <UserPlus className="size-4" />
                        Phân bổ ({selected.length})
                    </Button>
                    <p className="text-xs text-muted-foreground">
                        Chọn lead <span className="font-medium text-amber-600">Chờ xử lý</span> rồi bấm Phân bổ
                    </p>
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
                                <Th className="w-10">
                                    <input
                                        type="checkbox"
                                        checked={allPendingSelected}
                                        onChange={toggleAllPending}
                                        disabled={!pendingOnPage.length}
                                        title="Chọn tất cả Chờ xử lý trên trang"
                                    />
                                </Th>
                                <Th>ID</Th>
                                <Th>Thời gian</Th>
                                <Th>Nền tảng</Th>
                                <Th>Khách hàng</Th>
                                <Th>SĐT</Th>
                                <Th>Trạng thái</Th>
                                <Th>Mã đơn</Th>
                                <Th>Ghi chú</Th>
                                {canDelete && <Th />}
                            </tr>
                        </thead>
                        <tbody>
                            {leads.data?.length ? (
                                leads.data.map((row) => (
                                    <tr key={row.id} className="hover:bg-muted/30">
                                        <Td>
                                            {row.status === 'pending' ? (
                                                <input
                                                    type="checkbox"
                                                    checked={selected.includes(row.id)}
                                                    onChange={() => toggleRow(row.id)}
                                                />
                                            ) : null}
                                        </Td>
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
                                        {canDelete && (
                                            <Td>
                                                <DeleteRowButton
                                                    url={`${deleteUrlPrefix}/${row.id}`}
                                                    label={`#${row.id}`}
                                                />
                                            </Td>
                                        )}
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <Td
                                        colSpan={canDelete ? 10 : 9}
                                        className="py-8 text-center text-muted-foreground"
                                    >
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
