import { useState } from 'react';
import { CheckCircle2, ChevronDown, FlaskConical, Loader2, XCircle } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { apiPost } from '@/lib/api';
import { cn } from '@/lib/utils';

const DEFAULT_ACTIONS = {
    ghtk: [
        { key: 'authenticate', label: 'Kiểm tra Token' },
        { key: 'pick-addresses', label: 'Danh sách kho' },
        { key: 'products', label: 'Sản phẩm' },
        { key: 'solutions', label: 'Giải pháp' },
        { key: 'fee', label: 'Tính phí mẫu' },
    ],
    ghn: [
        { key: 'shop', label: 'Thông tin shop' },
        { key: 'fee', label: 'Tính phí mẫu' },
    ],
    viettel_post: [
        { key: 'login', label: 'Kiểm tra token' },
        { key: 'fee', label: 'Tính phí mẫu' },
    ],
    jnt: [{ key: 'connection', label: 'Kiểm tra credentials' }],
};

export function CarrierApiTestPanel({ provider, label }) {
    const [running, setRunning] = useState(null);
    const [lastResult, setLastResult] = useState(null);
    const actions = DEFAULT_ACTIONS[provider] ?? [];

    const run = async (action) => {
        setRunning(action);
        try {
            const data = await apiPost(`/admin/shipping-partners/${provider}/test/${action}`);
            setLastResult({ action, data });
            toast.success(data.message ?? `OK — ${action}`);
        } catch (e) {
            toast.error(e.message);
            setLastResult({ action, error: e.message });
        } finally {
            setRunning(null);
        }
    };

    if (!actions.length) return null;

    return (
        <div className="rounded-lg border border-dashed border-primary/30 bg-primary/5 p-4">
            <div className="mb-3 flex items-center gap-2">
                <FlaskConical className="size-4 text-primary" />
                <p className="text-sm font-semibold">Kiểm thử API — {label}</p>
            </div>
            <div className="flex flex-wrap gap-2">
                {actions.map((item) => (
                    <Button
                        key={item.key}
                        type="button"
                        size="sm"
                        variant="outline"
                        disabled={!!running}
                        onClick={() => run(item.key)}
                    >
                        {running === item.key && <Loader2 className="mr-1 size-3.5 animate-spin" />}
                        {item.label}
                    </Button>
                ))}
            </div>
            {lastResult && <TestResultCard result={lastResult} />}
        </div>
    );
}

/** Lấy các cặp key/giá trị đơn giản từ payload để hiển thị dễ đọc. */
function scalarEntries(payload, limit = 8) {
    if (!payload || typeof payload !== 'object') return [];

    const source = payload.data && typeof payload.data === 'object' ? payload.data : payload;

    return Object.entries(source)
        .filter(([key, value]) =>
            !['success', 'message'].includes(key) &&
            (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean'))
        .slice(0, limit);
}

function TestResultCard({ result }) {
    const [showRaw, setShowRaw] = useState(false);

    const failed = Boolean(result.error) || result.data?.success === false;
    const message = result.error ?? result.data?.message ?? (failed ? 'Kiểm thử thất bại.' : 'Kiểm thử thành công.');
    const entries = scalarEntries(result.data);

    return (
        <div
            className={cn(
                'mt-3 rounded-lg border p-3 text-sm',
                failed
                    ? 'border-rose-300 bg-rose-50 dark:border-rose-500/40 dark:bg-rose-500/10'
                    : 'border-emerald-300 bg-emerald-50 dark:border-emerald-500/40 dark:bg-emerald-500/10',
            )}
        >
            <div className="flex items-start gap-2">
                {failed ? (
                    <XCircle className="mt-0.5 size-4 shrink-0 text-rose-600 dark:text-rose-400" />
                ) : (
                    <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                )}
                <div className="min-w-0 flex-1">
                    <p className={cn('font-medium', failed ? 'text-rose-800 dark:text-rose-300' : 'text-emerald-800 dark:text-emerald-300')}>
                        {message}
                    </p>
                    {entries.length > 0 && (
                        <dl className="mt-2 grid gap-x-4 gap-y-1 text-xs sm:grid-cols-2">
                            {entries.map(([key, value]) => (
                                <div key={key} className="flex gap-1.5">
                                    <dt className="shrink-0 text-muted-foreground">{key}:</dt>
                                    <dd className="truncate font-medium">{String(value)}</dd>
                                </div>
                            ))}
                        </dl>
                    )}
                    <button
                        type="button"
                        className="mt-2 inline-flex items-center gap-1 text-[11px] text-muted-foreground hover:text-foreground"
                        onClick={() => setShowRaw((v) => !v)}
                    >
                        Chi tiết kỹ thuật
                        <ChevronDown className={cn('size-3 transition-transform', showRaw && 'rotate-180')} />
                    </button>
                    {showRaw && (
                        <pre className="mt-2 max-h-48 overflow-auto rounded border bg-background p-2 text-[11px] text-muted-foreground">
                            {JSON.stringify(result, null, 2)}
                        </pre>
                    )}
                </div>
            </div>
        </div>
    );
}
