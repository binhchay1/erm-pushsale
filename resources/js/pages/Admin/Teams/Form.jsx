import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';

import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FieldError, RequiredMark } from '@/components/ui/field-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PermissionEditor from '@/components/permissions/PermissionEditor';
import { validate } from '@/lib/validate';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

export default function TeamForm({ team, types, parents, leaders, permissionAreas = [] }) {
    const t = useT();
    const { url } = usePage();
    const isEdit = Boolean(team?.id);
    const parentFromQuery = new URLSearchParams(url.split('?')[1] ?? '').get('parent_id');

    const { data, setData, post, put, processing, errors, setError, clearErrors } = useForm({
        name: team?.name ?? '',
        type: team?.type ?? 'marketing',
        parent_id: team?.parent_id ?? parentFromQuery ?? '',
        leader_user_id: team?.leader_user_id ?? '',
        permissions: team?.permissions ?? {},
    });

    const submit = (e) => {
        e.preventDefault();

        const clientErrors = validate(data, {
            name: [{ required: true, label: t('org.name') }],
        });

        if (Object.keys(clientErrors).length > 0) {
            clearErrors();
            Object.entries(clientErrors).forEach(([field, message]) => setError(field, message));
            toast.error(t('common.validation.fix_errors'));
            return;
        }

        if (isEdit) {
            put(`/admin/teams/${team.id}`);
        } else {
            post('/admin/teams');
        }
    };

    return (
        <AppLayout>
            <Head title={isEdit ? t('pages.teams.edit') : t('pages.teams.form_create')} />

            <div className="mx-auto max-w-2xl space-y-6">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon-sm" asChild>
                        <Link href="/admin/teams">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {isEdit ? t('pages.teams.edit') : t('pages.teams.form_create')}
                    </h1>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('pages.teams.form_info')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">
                                    {t('org.name')}
                                    <RequiredMark />
                                </Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    aria-invalid={!!errors.name}
                                    onChange={(e) => {
                                        setData('name', e.target.value);
                                        clearErrors('name');
                                    }}
                                    placeholder={t('pages.teams.name_placeholder')}
                                />
                                <FieldError message={errors.name} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="type">
                                    {t('pages.teams.dept_type')}
                                    <RequiredMark />
                                </Label>
                                <select
                                    id="type"
                                    className="input-soft flex h-9 w-full px-3"
                                    value={data.type}
                                    onChange={(e) => setData('type', e.target.value)}
                                >
                                    {types.map((type) => (
                                        <option key={type.value} value={type.value}>
                                            {type.label}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={errors.type} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="parent_id">{t('pages.teams.parent_dept')}</Label>
                                <select
                                    id="parent_id"
                                    className="input-soft flex h-9 w-full px-3"
                                    value={data.parent_id}
                                    onChange={(e) => setData('parent_id', e.target.value || '')}
                                >
                                    <option value="">{t('pages.select_root')}</option>
                                    {parents.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="leader_user_id">{t('pages.teams.leader')}</Label>
                                <select
                                    id="leader_user_id"
                                    className="input-soft flex h-9 w-full px-3"
                                    value={data.leader_user_id}
                                    onChange={(e) => setData('leader_user_id', e.target.value || '')}
                                >
                                    <option value="">{t('pages.select_unassigned')}</option>
                                    {leaders.map((l) => (
                                        <option key={l.id} value={l.id}>
                                            {l.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <PermissionEditor
                                areas={permissionAreas}
                                value={data.permissions}
                                onChange={(next) => setData('permissions', next)}
                                title={t('pages.teams.permissions_title')}
                                hint={t('pages.teams.permissions_hint')}
                            />

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    <Save className="size-4" />
                                    {processing ? t('common.saving') : t('org.save')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
