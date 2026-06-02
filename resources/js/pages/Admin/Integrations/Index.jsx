import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Plug } from 'lucide-react';

import { PlatformCard } from '@/components/integrations/PlatformCard';
import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout';

export default function IntegrationsIndex({ hub, categories, platforms, leadRouting, stats }) {
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

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-lg">
                            <Plug className="size-5" />
                            Mục đích & giải pháp
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-6 lg:grid-cols-2">
                        <div>
                            <p className="mb-2 text-sm font-medium text-destructive">Nỗi đau thường gặp</p>
                            <ul className="list-inside list-disc space-y-1 text-sm text-muted-foreground">
                                {hub.problems?.map((p) => (
                                    <li key={p}>{p}</li>
                                ))}
                            </ul>
                        </div>
                        <div>
                            <p className="mb-2 text-sm font-medium text-primary">Giải pháp phễu SaleOps</p>
                            <ul className="space-y-1 text-sm text-muted-foreground">
                                {Object.entries(hub.solutions ?? {}).map(([k, v]) => (
                                    <li key={k}>
                                        <span className="font-medium text-foreground">{k}:</span> {v}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Luồng vận hành lõi</CardTitle>
                        <CardDescription>
                            Chia số: <strong>{leadRouting.strategy}</strong> · Chống trùng{' '}
                            {leadRouting.duplicate_window_days} ngày
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ol className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            {hub.workflow?.map((step, i) => (
                                <li key={step} className="rounded-lg border bg-muted/20 px-3 py-2 text-sm">
                                    <span className="mr-2 font-bold text-primary">{i + 1}.</span>
                                    {step}
                                </li>
                            ))}
                        </ol>
                    </CardContent>
                </Card>

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
