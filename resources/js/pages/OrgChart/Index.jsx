import { Head } from '@inertiajs/react';
import { GitBranch } from 'lucide-react';
import { useEffect } from 'react';

import { OrgChartTree } from '@/components/org/OrgChartTree';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { StatusBadge } from '@/components/ui/status-badge';
import AppLayout from '@/layouts/AppLayout';

export default function OrgChartIndex({ chart }) {
    useEffect(() => {
        console.info(
            '[ERM SaleOps] Sơ đồ tổ chức — URL: /org-chart\n' +
                '• Admin (admin@saleops.local): toàn bộ cây công ty\n' +
                '• Quản lý (org_level head/supervisor hoặc Trưởng nhóm): nhánh từ Giám đốc bộ phận trở xuống\n' +
                '• Nhân viên (org_level staff): quản lý trực tiếp, đồng cấp và cấp dưới của quản lý\n' +
                'Menu: sidebar → "Sơ đồ tổ chức" (mọi role đã đăng nhập)'
        );
    }, []);

    return (
        <AppLayout>
            <Head title="Sơ đồ tổ chức" />

            <div className="space-y-6 animate-in fade-in-0 duration-300">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight">
                            <GitBranch className="size-6 text-primary" />
                            Sơ đồ tổ chức
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Cơ cấu báo cáo từ trên xuống — phạm vi hiển thị theo quyền của bạn
                        </p>
                    </div>
                    <StatusBadge tone="info">{chart.scope_label}</StatusBadge>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Sơ đồ nhân sự</CardTitle>
                        <CardDescription>
                            Cây tổ chức từ trên xuống — màu theo vai trò; Sale/MKT hiển thị % chốt và doanh thu 30 ngày.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <OrgChartTree roots={chart.roots} />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
