import { Head, Link } from '@inertiajs/react';
import { Network, Plus } from 'lucide-react';

import { DepartmentTree } from '@/components/org/DepartmentTree';
import { PageHeader } from '@/components/layout/PageHeader';
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
                <PageHeader
                    icon={Network}
                    title={t('pages.teams.title')}
                    description={t('org.teams_desc_detail')}
                    actions={
                        <Button asChild>
                            <Link href="/admin/teams/create">
                                <Plus className="size-4" />
                                {t('pages.teams.create')}
                            </Link>
                        </Button>
                    }
                />

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
