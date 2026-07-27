import { Head, router, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';

import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import { useConfirm } from '@/hooks/use-confirm';
import { useRoleLabel } from '@/hooks/use-labels';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

function FieldRow({ label, required = false, error, hint, children }) {
    return (
        <div className="ps-unit-form-row">
            <label className="ps-unit-label">
                {label} {required ? <span>(*)</span> : null}
            </label>
            <div className="ps-unit-control-wrap">
                {children}
                {hint ? <p className="ps-unit-field-hint">{hint}</p> : null}
                {error ? <div className="ps-unit-error">{error}</div> : null}
            </div>
        </div>
    );
}

function ReadOnlyValue({ value }) {
    return <p className="ps-unit-readonly-value">{value || '—'}</p>;
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
            toast.error(t('common.validation.fix_errors'));
            return;
        }
        if (data.password !== data.password_confirmation) {
            setError('password_confirmation', t('pages.users.password_mismatch'));
            toast.error(t('common.validation.fix_errors'));
            return;
        }

        put('/profile', {
            preserveScroll: true,
            onSuccess: () => reset('password', 'password_confirmation'),
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
                <section className="ps-unit-section">
                    <h3 className="ps-unit-section-title">{t('profile.avatar_title')}</h3>
                    <div className="ps-unit-avatar-row">
                        {avatarSrc ? (
                            <img src={avatarSrc} alt={profile.name} className="ps-unit-avatar" />
                        ) : (
                            <span className="ps-unit-avatar-fallback">{profile.initials}</span>
                        )}
                        <div className="ps-unit-control-wrap">
                            <input
                                ref={fileRef}
                                type="file"
                                accept="image/*"
                                className="hidden"
                                onChange={onPickAvatar}
                            />
                            <div className="ps-unit-actions" style={{ paddingLeft: 0 }}>
                                <button type="button" className="btn btn-sm btn-default" onClick={() => fileRef.current?.click()}>
                                    <i className="fa fa-upload" /> {t('profile.upload')}
                                </button>
                                {profile.avatar_url ? (
                                    <button type="button" className="btn btn-sm btn-default" onClick={removeAvatar}>
                                        <i className="fa fa-trash" /> {t('profile.remove')}
                                    </button>
                                ) : null}
                            </div>
                            <p className="ps-unit-field-hint">{t('profile.avatar_hint')}</p>
                        </div>
                    </div>
                </section>

                <section className="ps-unit-section">
                    <h3 className="ps-unit-section-title">{t('profile.account_title')}</h3>
                    <div className="ps-unit-form">
                        <FieldRow label={t('profile.name')}>
                            <ReadOnlyValue value={profile.name} />
                        </FieldRow>
                        <FieldRow label={t('profile.email')}>
                            <ReadOnlyValue value={profile.email} />
                        </FieldRow>
                        <FieldRow label={t('profile.phone')}>
                            <ReadOnlyValue value={profile.phone} />
                        </FieldRow>
                        <FieldRow label={t('profile.job_title')}>
                            <ReadOnlyValue value={profile.job_title} />
                        </FieldRow>
                        {profile.manager_name ? (
                            <FieldRow label={t('profile.manager')}>
                                <ReadOnlyValue value={profile.manager_name} />
                            </FieldRow>
                        ) : null}
                    </div>
                    <p className="ps-unit-field-hint">{t('profile.admin_contact')}</p>
                </section>

                <section className="ps-unit-section">
                    <h3 className="ps-unit-section-title">{t('profile.password_title')}</h3>
                    <form className="ps-unit-form" onSubmit={submitPassword}>
                        <FieldRow label={t('profile.new_password')} required error={errors.password}>
                            <input
                                type="password"
                                className="form-control ps-unit-control"
                                autoComplete="new-password"
                                value={data.password}
                                onChange={(event) => {
                                    setData('password', event.target.value);
                                    clearErrors('password');
                                }}
                            />
                        </FieldRow>
                        <FieldRow label={t('profile.confirm_password')} required error={errors.password_confirmation}>
                            <input
                                type="password"
                                className="form-control ps-unit-control"
                                autoComplete="new-password"
                                value={data.password_confirmation}
                                onChange={(event) => {
                                    setData('password_confirmation', event.target.value);
                                    clearErrors('password_confirmation');
                                }}
                            />
                        </FieldRow>
                        <div className="ps-unit-actions">
                            {recentlySuccessful ? (
                                <span className="ps-unit-field-hint" style={{ marginRight: 8 }}>{t('profile.password_saved')}</span>
                            ) : null}
                            <button type="submit" className="btn btn-sm btn-primary" disabled={processing}>
                                <i className={`fa ${processing ? 'fa-spinner fa-spin' : 'fa-save'}`} />
                                {' '}
                                {processing ? t('common.saving') : t('profile.save_password')}
                            </button>
                        </div>
                    </form>
                </section>
            </PushsalePageShell>

            <ConfirmDialogPortal />
        </AppLayout>
    );
}
