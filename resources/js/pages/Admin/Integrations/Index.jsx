import { Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

import { PlatformCard } from '@/components/integrations/PlatformCard';
import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout';

export default function IntegrationsIndex({ hub, categories, platforms, stats }) {
    const grouped = Object.keys(categories).map((key) => ({
        key,
        label: categories[key],
        items: platforms.filter((p) => p.category === key),
    }));

    return (
        <AppLayout>
            <Head title="Tích hợp nền tảng" />

            <div className="space-y-8">
                <PageHeader
                    title={hub.title}
                    description={hub.summary}
                    actions={
                        <Button variant="outline" asChild>
                            <Link href="/admin/leads">
                                Nhật ký lead
                                <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Lead hôm nay</CardDescription>
                            <CardTitle className="text-3xl">{stats.leads_today}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Đang chờ xử lý</CardDescription>
                            <CardTitle className="text-3xl">{stats.leads_pending}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Nền tảng đang bật</CardDescription>
                            <CardTitle className="text-3xl">{stats.platforms_enabled}</CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                {grouped.map(
                    (group) =>
                        group.items.length > 0 && (
                            <section key={group.key} className="space-y-4">
                                <h2 className="text-lg font-semibold">{group.label}</h2>
                                <div className="grid gap-6 xl:grid-cols-2">
                                    {group.items.map((platform) => (
                                        <PlatformCard key={platform.platform} platform={platform} />
                                    ))}
                                </div>
                            </section>
                        ),
                )}
            </div>
        </AppLayout>
    );
}
