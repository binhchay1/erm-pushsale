import { Head } from '@inertiajs/react';
import { Network } from 'lucide-react';

import { OrgChartBoard } from '@/components/org/OrgChartBoard';
import { StatusBadge } from '@/components/ui/status-badge';
import AppLayout from '@/layouts/AppLayout';

export default function OrgChartIndex({ chart }) {
    return (
        <AppLayout>
            <Head title="Sơ đồ nhân sự" />

            <div className="space-y-6 animate-in fade-in-0 duration-300">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight">
                            <Network className="size-6 text-primary" />
                            Sơ đồ nhân sự
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Từng bộ phận, từng team và trưởng nhóm — bạn chỉ thấy phạm vi thuộc quyền của
                            mình
                        </p>
                    </div>
                    <StatusBadge tone="info">{chart.scope_label}</StatusBadge>
                </div>

                <OrgChartBoard admins={chart.admins} departments={chart.departments} />
            </div>
        </AppLayout>
    );
}
