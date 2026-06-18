import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

export default function UserForm({ user, roles, teams, managers, orgLevels }) {
    const t = useT();
    const isEdit = Boolean(user?.id);
    const { data, setData, post, put, processing, errors } = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        role: user?.role ?? 'sales',
        team_id: user?.team_id ?? '',
        manager_user_id: user?.manager_user_id ?? '',
        is_team_leader: user?.is_team_leader ?? false,
        org_level: user?.org_level ?? '',
        phone: user?.phone ?? '',
        job_title: user?.job_title ?? '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(`/admin/users/${user.id}`);
        } else {
            post('/admin/users');
        }
    };

    return (
        <AppLayout>
            <Head title={isEdit ? t('pages.users.edit') : t('pages.users.form_create')} />

            <div className="mx-auto max-w-2xl space-y-6">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon-sm" asChild>
                        <Link href="/admin/users">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {isEdit ? t('pages.users.edit') : t('pages.users.form_create')}
                    </h1>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('pages.users.form_account')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">{t('pages.users.name')}</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                />
                                {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="email">{t('pages.users.email')}</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                />
                                {errors.email && <p className="text-xs text-destructive">{errors.email}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="role">{t('pages.users.role')}</Label>
                                <select
                                    id="role"
                                    className="input-soft flex h-9 w-full px-3"
                                    value={data.role}
                                    onChange={(e) => setData('role', e.target.value)}
                                >
                                    {roles.map((r) => (
                                        <option key={r.value} value={r.value}>
                                            {r.label}
                                        </option>
                                    ))}
                                </select>
                                {errors.role && <p className="text-xs text-destructive">{errors.role}</p>}
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="phone">{t('pages.users.phone')}</Label>
                                    <Input
                                        id="phone"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="job_title">{t('pages.users.job_title')}</Label>
                                    <Input
                                        id="job_title"
                                        value={data.job_title}
                                        onChange={(e) => setData('job_title', e.target.value)}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="team_id">{t('pages.users.col_team')}</Label>
                                    <select
                                        id="team_id"
                                        className="input-soft flex h-9 w-full px-3"
                                        value={data.team_id}
                                        onChange={(e) => setData('team_id', e.target.value || '')}
                                    >
                                        <option value="">{t('pages.users.no_select')}</option>
                                        {teams.map((team) => (
                                            <option key={team.id} value={team.id}>
                                                {team.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="manager_user_id">{t('pages.users.direct_manager')}</Label>
                                    <select
                                        id="manager_user_id"
                                        className="input-soft flex h-9 w-full px-3"
                                        value={data.manager_user_id}
                                        onChange={(e) => setData('manager_user_id', e.target.value || '')}
                                    >
                                        <option value="">{t('pages.users.no_select')}</option>
                                        {managers.map((m) => (
                                            <option key={m.id} value={m.id}>
                                                {m.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="org_level">{t('pages.users.org_level')}</Label>
                                <select
                                    id="org_level"
                                    className="input-soft flex h-9 w-full px-3"
                                    value={data.org_level}
                                    onChange={(e) => setData('org_level', e.target.value || '')}
                                >
                                    <option value="">{t('pages.users.no_select')}</option>
                                    {(orgLevels ?? []).map((l) => (
                                        <option key={l.value} value={l.value}>
                                            {l.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="flex items-center justify-between rounded-lg border px-4 py-3">
                                <Label htmlFor="is_team_leader">{t('pages.users.team_lead_legacy')}</Label>
                                <Switch
                                    id="is_team_leader"
                                    checked={data.is_team_leader}
                                    onCheckedChange={(v) => setData('is_team_leader', v)}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="password">
                                    {t('pages.users.password')}
                                    {isEdit && ` (${t('pages.users.password_hint')})`}
                                </Label>
                                <Input
                                    id="password"
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                />
                                {errors.password && (
                                    <p className="text-xs text-destructive">{errors.password}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="password_confirmation">{t('pages.users.password_confirm')}</Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    value={data.password_confirmation}
                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                />
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    <Save className="size-4" />
                                    {processing ? t('common.saving') : t('pages.save')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
