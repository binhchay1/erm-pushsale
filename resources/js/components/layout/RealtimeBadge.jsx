import { Radio } from 'lucide-react';

import { cn } from '@/lib/utils';
import { useT } from '@/providers/I18nProvider';

export function RealtimeBadge({ connected }) {
    const t = useT();

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide',
                connected
                    ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                    : 'border-border bg-muted text-muted-foreground'
            )}
        >
            <Radio className={cn('size-3', connected && 'animate-pulse text-emerald-500')} />
            {connected ? t('dashboard.realtime.live') : t('dashboard.realtime.offline')}
        </span>
    );
}
