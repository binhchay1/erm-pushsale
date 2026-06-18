import { Head } from '@inertiajs/react';
import { Network } from 'lucide-react';

import { OrgChartBoard } from '@/components/org/OrgChartBoard';
import { StatusBadge } from '@/components/ui/status-badge';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

export default function OrgChartIndex({ chart }) {
    const t = useT();

    return (
        <AppLayout>
            <Head title={t('org.title')} />

            <div className="space-y-6 animate-in fade-in-0 duration-300">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight">
                            <Network className="size-6 text-primary" />
                            {t('org.title')}
                        </h1>
                        <p className="text-sm text-muted-foreground">{t('org.desc')}</p>
                    </div>
                    <StatusBadge tone="info">{chart.scope_label}</StatusBadge>
                </div>

                <OrgChartBoard admins={chart.admins} departments={chart.departments} />
            </div>
        </AppLayout>
    );
}
