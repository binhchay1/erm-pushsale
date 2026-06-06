import { Head, Link } from '@inertiajs/react';
import { Network, Plus } from 'lucide-react';

import { DepartmentTree } from '@/components/org/DepartmentTree';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout';

export default function TeamsIndex({ tree }) {
    return (
        <AppLayout>
            <Head title="Phòng ban" />

            <div className="space-y-6 animate-in fade-in-0 duration-300">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight">
                            <Network className="size-6 text-primary" />
                            Phòng ban & cơ cấu
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Khối kinh doanh → trưởng ban → giám sát → nhân viên (nhánh con)
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/admin/teams/create">
                            <Plus className="size-4" />
                            Thêm phòng ban
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Sơ đồ phòng ban</CardTitle>
                        <CardDescription>
                            Mỗi phòng ban gắn loại (Marketing, Telesale, …) và có thể có phòng ban con
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <DepartmentTree tree={tree} />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
