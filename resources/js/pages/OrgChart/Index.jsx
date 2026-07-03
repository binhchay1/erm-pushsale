import { Head } from '@inertiajs/react';
import { Network } from 'lucide-react';

import { OrgChartBoard } from '@/components/org/OrgChartBoard';
import { StatusBadge } from '@/components/ui/status-badge';
import { PageHeader } from '@/components/layout/PageHeader';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

export default function OrgChartIndex({ chart }) {
    const t = useT();

    return (
        <AppLayout>
            <Head title={t('org.title')} />

            <div className="space-y-6 animate-in fade-in-0 duration-300">
                <PageHeader
                    icon={Network}
                    title={t('org.title')}
                    description={t('org.desc')}
                    actions={<StatusBadge tone="info">{chart.scope_label}</StatusBadge>}
                />

                <OrgChartBoard admins={chart.admins} departments={chart.departments} />
            </div>
        </AppLayout>
    );
}
