import { useState } from 'react';
import { FlaskConical, Loader2 } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { apiPost } from '@/lib/api';

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
            {lastResult && (
                <pre className="mt-3 max-h-48 overflow-auto rounded border bg-background p-2 text-[11px] text-muted-foreground">
                    {JSON.stringify(lastResult, null, 2)}
                </pre>
            )}
        </div>
    );
}
