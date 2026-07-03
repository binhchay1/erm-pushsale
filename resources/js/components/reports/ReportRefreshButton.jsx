import { router } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { useT } from '@/providers/I18nProvider';

export function ReportRefreshButton({ routeUrl, filters, cachedAt }) {
    const t = useT();

    const refresh = () => {
        router.get(routeUrl, { ...filters, refresh: 1 }, { preserveScroll: true });
    };

    return (
        <div className="flex flex-col items-end gap-1">
            <Button type="button" variant="outline" size="sm" onClick={refresh}>
                <RefreshCw className="size-4" />
                {t('reports.refresh_now')}
            </Button>
            {cachedAt && (
                <span className="text-[10px] text-muted-foreground">
                    {t('reports.cached_at', { time: new Date(cachedAt).toLocaleString('vi-VN') })}
                </span>
            )}
        </div>
    );
}
