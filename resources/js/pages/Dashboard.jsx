import AdminLayout from '@/layouts/AdminLayout';
import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

export default function Dashboard() {
    return (
        <AdminLayout>
            <Head title="Tổng quan hệ thống" />

            <div className="space-y-6">
                <h1 className="text-2xl font-bold text-zinc-900">Tổng quan hệ thống</h1>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm text-zinc-500">Số mới về trong ngày</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold">128</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm text-zinc-500">Đơn đã chốt</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold text-green-600">45</p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}