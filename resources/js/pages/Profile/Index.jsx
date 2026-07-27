import { Head, router, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';

import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import { useConfirm } from '@/hooks/use-confirm';
import { useRoleLabel } from '@/hooks/use-labels';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

function InfoItem({ label, value }) {
    return (
        <div className="ps-profile-info-item">
            <dt>{label}</dt>
            <dd>{value || '—'}</dd>
        </div>
    );
}

export default function ProfileIndex({ profile }) {
    const t = useT();
    const roleLabel = useRoleLabel(profile.role);
    const fileRef = useRef(null);
    const [preview, setPreview] = useState(null);
    const { ask, ConfirmDialogPortal } = useConfirm();

    const { data, setData, put, processing, errors, recentlySuccessful, reset, setError, clearErrors } = useForm({
        password: '',
        password_confirmation: '',
    });

    const avatarSrc = preview ?? profile.avatar_url;

    const onPickAvatar = (event) => {
        const file = event.target.files?.[0];
        if (!file) return;
        setPreview(URL.createObjectURL(file));
        router.post(
            '/profile/avatar',
            { avatar: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onError: (errs) => toast.error(errs.avatar ?? t('common.request_failed')),
                onFinish: () => {
                    if (preview) URL.revokeObjectURL(preview);
                    setPreview(null);
                    if (fileRef.current) fileRef.current.value = '';
                },
            },
        );
    };

    const removeAvatar = async () => {
        const ok = await ask({
            title: t('profile.remove_confirm_title'),
            description: t('profile.remove_confirm_desc'),
            confirmLabel: t('common.delete'),
            variant: 'destructive',
        });
        if (!ok) return;
        router.delete('/profile/avatar', { preserveScroll: true });
    };

    const submitPassword = (event) => {
        event.preventDefault();

        if (!data.password) {
            setError('password', t('common.validation.required'));
            toast.error(t('common.validation.form_errors'));
            return;
        }
        if (data.password !== data.password_confirmation) {
            setError('password_confirmation', t('pages.users.password_mismatch'));
            toast.error(t('common.validation.form_errors'));
            return;
        }

        put('/profile', {
            preserveScroll: true,
            onSuccess: () => {
                reset('password', 'password_confirmation');
                toast.success(t('profile.password_saved'));
            },
            onError: (errs) => toast.error(errs.password ?? t('common.request_failed')),
        });
    };

    const accountSubtitle = [
        roleLabel,
        profile.team_name,
        profile.org_level_label,
    ].filter(Boolean).join(' · ');

    return (
        <AppLayout>
            <Head title={t('profile.title')} />

            <PushsalePageShell
                title={t('profile.title')}
                subtitle={accountSubtitle || t('profile.desc')}
                className="ps-account-profile-page"
                collapsible={false}
            >
                <div className="ps-profile-layout">
                    <section className="ps-profile-card">
                        <h3 className="ps-profile-card__title">{t('profile.avatar_title')}</h3>
                        <div className="ps-profile-avatar-row">
                            {avatarSrc ? (
                                <img src={avatarSrc} alt={profile.name} className="ps-profile-avatar" />
                            ) : (
                                <span className="ps-profile-avatar-fallback">{profile.initials}</span>
                            )}
                            <div className="ps-profile-avatar-meta">
                                <input
                                    ref={fileRef}
                                    type="file"
                                    accept="image/*"
                                    className="hidden"
                                    onChange={onPickAvatar}
                                />
                                <div className="ps-profile-avatar-actions">
                                    <button type="button" className="btn btn-sm btn-default" onClick={() => fileRef.current?.click()}>
                                        <i className="fa fa-upload" /> {t('profile.upload')}
                                    </button>
                                    {profile.avatar_url ? (
                                        <button type="button" className="btn btn-sm btn-default" onClick={removeAvatar}>
                                            <i className="fa fa-trash" /> {t('profile.remove')}
                                        </button>
                                    ) : null}
                                </div>
                                <p className="ps-profile-hint">{t('profile.avatar_hint')}</p>
                            </div>
                        </div>
                    </section>

                    <section className="ps-profile-card">
                        <h3 className="ps-profile-card__title">{t('profile.account_title')}</h3>
                        <dl className="ps-profile-info-grid">
                            <InfoItem label={t('profile.name')} value={profile.name} />
                            <InfoItem label={t('profile.email')} value={profile.email} />
                            <InfoItem label={t('profile.phone')} value={profile.phone} />
                            <InfoItem label={t('profile.job_title')} value={profile.job_title} />
                            {profile.manager_name ? (
                                <InfoItem label={t('profile.manager')} value={profile.manager_name} />
                            ) : null}
                        </dl>
                        <p className="ps-profile-hint">{t('profile.admin_contact')}</p>
                    </section>

                    <section className="ps-profile-card">
                        <h3 className="ps-profile-card__title">{t('profile.password_title')}</h3>
                        <form className="ps-profile-password-form" onSubmit={submitPassword}>
                            <div className="ps-profile-field">
                                <label htmlFor="profile-password">
                                    {t('profile.new_password')} <span>(*)</span>
                                </label>
                                <input
                                    id="profile-password"
                                    type="password"
                                    className="form-control"
                                    autoComplete="new-password"
                                    value={data.password}
                                    onChange={(event) => {
                                        setData('password', event.target.value);
                                        clearErrors('password');
                                    }}
                                />
                                {errors.password ? <div className="ps-profile-error">{errors.password}</div> : null}
                            </div>
                            <div className="ps-profile-field">
                                <label htmlFor="profile-password-confirm">
                                    {t('profile.confirm_password')} <span>(*)</span>
                                </label>
                                <input
                                    id="profile-password-confirm"
                                    type="password"
                                    className="form-control"
                                    autoComplete="new-password"
                                    value={data.password_confirmation}
                                    onChange={(event) => {
                                        setData('password_confirmation', event.target.value);
                                        clearErrors('password_confirmation');
                                    }}
                                />
                                {errors.password_confirmation ? (
                                    <div className="ps-profile-error">{errors.password_confirmation}</div>
                                ) : null}
                            </div>
                            <div className="ps-profile-actions">
                                {recentlySuccessful ? (
                                    <span className="ps-profile-hint">{t('profile.password_saved')}</span>
                                ) : null}
                                <button type="submit" className="btn btn-sm btn-primary" disabled={processing}>
                                    <i className={`fa ${processing ? 'fa-spinner fa-spin' : 'fa-save'}`} />
                                    {' '}
                                    {processing ? t('common.saving') : t('profile.save_password')}
                                </button>
                            </div>
                        </form>
                    </section>
                </div>
            </PushsalePageShell>

            <ConfirmDialogPortal />
        </AppLayout>
    );
}
