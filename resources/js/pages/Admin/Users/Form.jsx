import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { useMemo } from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CurrencyInput } from '@/components/ui/currency-input';
import { FieldError, RequiredMark } from '@/components/ui/field-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import PermissionEditor, { capMap } from '@/components/permissions/PermissionEditor';
import { useLabels } from '@/hooks/use-labels';
import { validate } from '@/lib/validate';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

export default function UserForm({
    user,
    roles,
    teams,
    managers,
    managerPool = [],
    orgLevels,
    emailIdentity = {},
    permissionConfig = {},
    workShifts = [],
    activeMenuCode = '1.2.1',
}) {
    const t = useT();
    const labels = useLabels();
    const isEdit = Boolean(user?.id);
    const suffix = emailIdentity.suffix ?? '@saleops.local';
    const host = emailIdentity.host ?? 'saleops.local';
    const roleLocalParts = emailIdentity.roleLocalParts ?? {};

    const permAreas = permissionConfig.areas ?? [];
    const grantable = permissionConfig.grantable ?? {};
    const defaultsByRole = permissionConfig.defaultsByRole ?? {};

    const initialRole = user?.role ?? 'sales';

    const { data, setData, post, put, processing, errors, setError, clearErrors } = useForm({
        name: user?.name ?? '',
        email_local: user?.email_local ?? roleLocalParts.sales ?? 'sales',
        role: initialRole,
        team_id: user?.team_id ?? '',
        manager_user_id: user?.manager_user_id ?? '',
        is_team_leader: user?.is_team_leader ?? false,
        org_level: user?.org_level ?? '',
        phone: user?.phone ?? '',
        job_title: user?.job_title ?? '',
        employee_code: user?.employee_code ?? '',
        base_salary: user?.base_salary ?? 0,
        receive_data: user?.receive_data ?? true,
        work_shift_id: user?.work_shift_id ?? '',
        is_locked: user?.is_locked ?? false,
        password: '',
        password_confirmation: '',
        permissions: capMap(user?.permissions ?? defaultsByRole[initialRole] ?? {}, grantable),
    });

    const previewEmail = useMemo(() => {
        const local = (data.email_local || '').trim().toLowerCase();
        return local ? `${local}${suffix}` : suffix;
    }, [data.email_local, suffix]);

    const hierarchyRoles = ['sales', 'marketing', 'warehouse'];
    const usesHierarchy = hierarchyRoles.includes(data.role);
    const isAdmin = data.role === 'admin';
    const isHead = data.org_level === 'head';
    // Quản lý trực tiếp luôn tùy chọn — quyền hiển thị theo role/team.

    const filteredManagers = useMemo(() => {
        const pool = managerPool.length ? managerPool : managers;
        if (isAdmin || !usesHierarchy) {
            return [];
        }
        if (isHead) {
            return pool.filter((m) => m.role === 'admin');
        }
        return pool.filter(
            (m) =>
                m.role === 'admin' ||
                (m.role === data.role &&
                    (m.org_level === 'head' || m.org_level === 'supervisor' || m.is_team_leader)),
        );
    }, [managerPool, managers, data.role, data.org_level, isAdmin, usesHierarchy, isHead]);

    const onRoleChange = (role) => {
        const suggested = roleLocalParts[role];
        setData((prev) => ({
            ...prev,
            role,
            ...(role === 'admin' ? { manager_user_id: '', org_level: '', team_id: '' } : {}),
            ...(!isEdit && suggested ? { email_local: suggested } : {}),
            permissions: capMap(defaultsByRole[role] ?? {}, grantable),
        }));
    };

    // Chọn phòng ban -> auto-tick quyền đã setup cho team (gộp với quyền hiện tại).
    const onTeamChange = (teamId) => {
        const team = teams.find((tm) => String(tm.id) === String(teamId));
        setData((prev) => {
            const next = { ...prev, team_id: teamId || '' };
            const teamPerms = team?.permissions ?? {};
            if (teamId && Object.keys(teamPerms).length) {
                next.permissions = capMap({ ...prev.permissions, ...teamPerms }, grantable);
            }
            return next;
        });
    };

    const onOrgLevelChange = (orgLevel) => {
        setData((prev) => ({
            ...prev,
            org_level: orgLevel,
            ...(orgLevel === 'head' ? { manager_user_id: '' } : {}),
        }));
    };

    const submit = (e) => {
        e.preventDefault();

        const clientErrors = validate(data, {
            name: [{ required: true, label: t('pages.users.name') }],
            email_local: [{ required: true, label: t('pages.users.email_local') }],
            role: [{ required: true, label: t('pages.users.role') }],
            ...(isEdit ? {} : { password: [{ required: true, label: t('pages.users.password') }] }),
        });

        if (data.password && data.password !== data.password_confirmation) {
            clientErrors.password_confirmation = t('pages.users.password_mismatch');
        }

        if (Object.keys(clientErrors).length > 0) {
            clearErrors();
            Object.entries(clientErrors).forEach(([field, message]) => setError(field, message));
            toast.error(t('common.validation.fix_errors'));
            return;
        }

        if (isEdit) {
            put(`/admin/users/${user.id}`);
        } else {
            post('/admin/users');
        }
    };

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
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
                                <Label htmlFor="name">
                                    {t('pages.users.name')}
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
                                />
                                <FieldError message={errors.name} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="email_local">
                                    {t('pages.users.email_local')}
                                    <RequiredMark />
                                </Label>
                                <div className="flex max-w-md">
                                    <Input
                                        id="email_local"
                                        className="rounded-r-none font-mono text-sm"
                                        value={data.email_local}
                                        onChange={(e) => setData('email_local', e.target.value)}
                                        autoComplete="off"
                                        spellCheck={false}
                                    />
                                    <span className="flex h-9 shrink-0 items-center rounded-r-md border border-l-0 border-input bg-muted px-3 font-mono text-xs text-muted-foreground">
                                        {suffix}
                                    </span>
                                </div>
                                {(errors.email_local || errors.email) && (
                                    <p className="text-xs text-destructive">
                                        {errors.email_local || errors.email}
                                    </p>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    {emailIdentity.isInternal
                                        ? t('pages.users.email_identity_internal')
                                        : t('pages.users.email_identity_tenant', {
                                              name: emailIdentity.companyName ?? emailIdentity.companySlug,
                                              host,
                                          })}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {t('pages.users.email_identity_hint', { host })}
                                </p>
                                <p className="text-xs font-medium text-primary">
                                    {t('pages.users.email_preview')}:{' '}
                                    <span className="font-mono">{previewEmail}</span>
                                </p>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="role">
                                    {t('pages.users.role')}
                                    <RequiredMark />
                                </Label>
                                <select
                                    id="role"
                                    className="input-soft flex h-9 w-full px-3"
                                    value={data.role}
                                    onChange={(e) => onRoleChange(e.target.value)}
                                >
                                    {roles.map((r) => (
                                        <option key={r.value} value={r.value}>
                                            {labels.user_role?.[r.value] ?? r.label}
                                        </option>
                                    ))}
                                </select>
                                {roleLocalParts[data.role] && (
                                    <p className="text-xs text-muted-foreground">
                                        {t('pages.users.email_role_suggest', {
                                            local: roleLocalParts[data.role],
                                        })}
                                    </p>
                                )}
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
                                    <Label htmlFor="employee_code">Mã nhân viên</Label>
                                    <Input
                                        id="employee_code"
                                        value={data.employee_code}
                                        onChange={(e) => setData('employee_code', e.target.value)}
                                    />
                                    <FieldError message={errors.employee_code} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="base_salary">Mức lương cơ bản</Label>
                                    <CurrencyInput
                                        id="base_salary"
                                        value={data.base_salary === '' || data.base_salary == null ? '' : Number(data.base_salary)}
                                        onChange={(amount) => setData('base_salary', amount)}
                                    />
                                    <FieldError message={errors.base_salary} />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="work_shift_id">Ca làm việc</Label>
                                <select
                                    id="work_shift_id"
                                    className="input-soft flex h-9 w-full px-3"
                                    value={data.work_shift_id}
                                    onChange={(e) => setData('work_shift_id', e.target.value)}
                                >
                                    <option value="">-- Chọn ca làm việc --</option>
                                    {workShifts.map((shift) => (
                                        <option key={shift.id} value={shift.id}>
                                            {shift.name}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={errors.work_shift_id} />
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="flex items-center justify-between rounded-lg border px-4 py-3">
                                    <Label htmlFor="receive_data">Nhận phân bổ dữ liệu</Label>
                                    <Switch
                                        id="receive_data"
                                        checked={Boolean(data.receive_data)}
                                        onCheckedChange={(value) => setData('receive_data', value)}
                                    />
                                </div>
                                <div className="flex items-center justify-between rounded-lg border px-4 py-3">
                                    <Label htmlFor="is_locked">Khóa tài khoản</Label>
                                    <Switch
                                        id="is_locked"
                                        checked={Boolean(data.is_locked)}
                                        onCheckedChange={(value) => setData('is_locked', value)}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                {!isAdmin && (
                                    <div className="space-y-2">
                                        <Label htmlFor="team_id">{t('pages.users.col_team')}</Label>
                                        <select
                                            id="team_id"
                                            className="input-soft flex h-9 w-full px-3"
                                            value={data.team_id}
                                            onChange={(e) => onTeamChange(e.target.value)}
                                        >
                                            <option value="">{t('pages.users.no_select')}</option>
                                            {teams.map((team) => (
                                                <option key={team.id} value={team.id}>
                                                    {team.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                )}
                                {usesHierarchy && (
                                    <div className="space-y-2">
                                        <Label htmlFor="manager_user_id">
                                            {t('pages.users.direct_manager')}
                                        </Label>
                                        <select
                                            id="manager_user_id"
                                            className="input-soft flex h-9 w-full px-3"
                                            value={data.manager_user_id}
                                            onChange={(e) => setData('manager_user_id', e.target.value || '')}
                                        >
                                            <option value="">
                                                {t('pages.users.no_select') || '--Chọn quản lý--'}
                                            </option>
                                            {filteredManagers.map((m) => (
                                                <option key={m.id} value={m.id}>
                                                    {m.name}
                                                </option>
                                            ))}
                                        </select>
                                        {errors.manager_user_id && (
                                            <p className="text-xs text-destructive">{errors.manager_user_id}</p>
                                        )}
                                        {isHead && (
                                            <p className="text-xs text-muted-foreground">
                                                {t('pages.users.head_manager_hint')}
                                            </p>
                                        )}
                                    </div>
                                )}
                            </div>

                            {usesHierarchy && (
                                <div className="space-y-2">
                                    <Label htmlFor="org_level">{t('pages.users.org_level')}</Label>
                                    <select
                                        id="org_level"
                                        className="input-soft flex h-9 w-full px-3"
                                        value={data.org_level}
                                        onChange={(e) => onOrgLevelChange(e.target.value || '')}
                                    >
                                        <option value="">{t('pages.users.no_select')}</option>
                                        {(orgLevels ?? []).map((l) => (
                                            <option key={l.value} value={l.value}>
                                                {l.label}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.org_level && (
                                        <p className="text-xs text-destructive">{errors.org_level}</p>
                                    )}
                                </div>
                            )}

                            {usesHierarchy && !isHead && (
                                <div className="flex items-center justify-between rounded-lg border px-4 py-3">
                                    <Label htmlFor="is_team_leader">{t('pages.users.team_lead_legacy')}</Label>
                                    <Switch
                                        id="is_team_leader"
                                        checked={data.is_team_leader}
                                        onCheckedChange={(v) => setData('is_team_leader', v)}
                                    />
                                </div>
                            )}

                            <div className="space-y-2">
                                <Label htmlFor="password">
                                    {t('pages.users.password')}
                                    {isEdit ? ` (${t('pages.users.password_hint')})` : <RequiredMark />}
                                </Label>
                                <Input
                                    id="password"
                                    type="password"
                                    value={data.password}
                                    aria-invalid={!!errors.password}
                                    onChange={(e) => {
                                        setData('password', e.target.value);
                                        clearErrors('password');
                                    }}
                                />
                                <FieldError message={errors.password} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="password_confirmation">{t('pages.users.password_confirm')}</Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    value={data.password_confirmation}
                                    aria-invalid={!!errors.password_confirmation}
                                    onChange={(e) => {
                                        setData('password_confirmation', e.target.value);
                                        clearErrors('password_confirmation');
                                    }}
                                />
                                <FieldError message={errors.password_confirmation} />
                            </div>

                            {isAdmin ? (
                                <div className="rounded-lg border p-4">
                                    <p className="text-sm font-semibold">
                                        {t('pages.users.permissions_title')}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {t('pages.users.permissions_admin_note')}
                                    </p>
                                </div>
                            ) : (
                                <PermissionEditor
                                    areas={permAreas}
                                    value={data.permissions}
                                    grantable={grantable}
                                    onChange={(next) => setData('permissions', next)}
                                    title={t('pages.users.permissions_title')}
                                    hint={t('pages.users.permissions_hint')}
                                />
                            )}

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
