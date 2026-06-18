import { Head, Link } from '@inertiajs/react';
import { Network, Plus } from 'lucide-react';

import { DepartmentTree } from '@/components/org/DepartmentTree';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

export default function TeamsIndex({ tree }) {
    const t = useT();

    return (
        <AppLayout>
            <Head title={t('pages.teams.title')} />

            <div className="space-y-6 animate-in fade-in-0 duration-300">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight">
                            <Network className="size-6 text-primary" />
                            {t('pages.teams.title')}
                        </h1>
                        <p className="text-sm text-muted-foreground">{t('org.teams_desc_detail')}</p>
                    </div>
                    <Button asChild>
                        <Link href="/admin/teams/create">
                            <Plus className="size-4" />
                            {t('pages.teams.create')}
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('org.org_chart_card')}</CardTitle>
                        <CardDescription>{t('org.org_chart_desc')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <DepartmentTree tree={tree} />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
