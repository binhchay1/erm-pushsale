import { useState } from 'react';
import { FlaskConical, Loader2 } from 'lucide-react';
import { toast } from 'sonner';

import { ConnectionTestResult } from '@/components/connections/ConnectionTestResult';
import { Button } from '@/components/ui/button';
import { apiPost } from '@/lib/api';
import { useT } from '@/providers/I18nProvider';

export function CarrierApiTestPanel({ provider, label, testActions = [] }) {
    const t = useT();
    const [running, setRunning] = useState(null);
    const [lastResult, setLastResult] = useState(null);

    const run = async (action, actionLabel) => {
        setRunning(action);
        try {
            const data = await apiPost(`/admin/shipping-partners/${provider}/test/${action}`);
            setLastResult({ action, actionLabel, data });
            toast.success(data.message ?? t('integrations.test_success'));
        } catch (e) {
            setLastResult({ action, actionLabel, error: e.message });
            toast.error(e.message);
        } finally {
            setRunning(null);
        }
    };

    if (!testActions.length) return null;

    return (
        <div className="rounded-lg border border-dashed border-primary/30 bg-primary/5 p-4">
            <div className="mb-3 flex items-center gap-2">
                <FlaskConical className="size-4 text-primary" />
                <p className="text-sm font-semibold">{t('shipping.test_api_panel', { label })}</p>
            </div>
            <div className="flex flex-wrap gap-2">
                {testActions.map((item) => (
                    <Button
                        key={item.key}
                        type="button"
                        size="sm"
                        variant="outline"
                        disabled={!!running}
                        onClick={() => run(item.key, item.label)}
                    >
                        {running === item.key && <Loader2 className="mr-1 size-3.5 animate-spin" />}
                        {item.label}
                    </Button>
                ))}
            </div>
            <ConnectionTestResult result={lastResult} actionLabel={lastResult?.actionLabel} />
        </div>
    );
}
