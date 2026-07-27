import { useForm, usePage } from '@inertiajs/react';
import { AlertCircle, LockKeyhole, LogIn, Mail } from 'lucide-react';
import { useMemo } from 'react';

import { useT } from '@/providers/I18nProvider';

function resolveFieldError(...sources) {
    for (const source of sources) {
        if (!source) continue;
        if (Array.isArray(source)) {
            const first = String(source[0] ?? '').trim();
            if (first) return first;
            continue;
        }
        const text = String(source).trim();
        if (text) return text;
    }
    return null;
}

export function LoginForm() {
    const t = useT();
    const page = usePage();
    const pageErrors = page.props.errors ?? {};
    const flashError = page.props.flash?.error ?? null;
    const oldEmail = page.props.old?.email ?? '';

    const { data, setData, post, processing, errors, clearErrors } = useForm({
        email: oldEmail,
        password: '',
        remember: false,
    });

    const fieldErrors = useMemo(() => ({
        email: resolveFieldError(errors.email, pageErrors.email),
        password: resolveFieldError(errors.password, pageErrors.password),
    }), [errors.email, errors.password, pageErrors.email, pageErrors.password]);

    const bannerError = fieldErrors.email
        || fieldErrors.password
        || resolveFieldError(flashError);

    const submit = (event) => {
        event.preventDefault();
        post('/login', {
            preserveScroll: true,
            onError: () => {
                // Giữ mật khẩu trống sau lỗi; email giữ nguyên trong state useForm.
            },
        });
    };

    return (
        <form onSubmit={submit} className="public-login-form" noValidate>
            {bannerError ? (
                <div className="public-login-alert" role="alert">
                    <AlertCircle aria-hidden="true" />
                    <span>{bannerError}</span>
                </div>
            ) : null}

            <label htmlFor="email">{t('auth.email')}</label>
            <div className={`public-login-input ${fieldErrors.email ? 'has-error' : ''}`}>
                <Mail />
                <input
                    id="email"
                    type="email"
                    autoComplete="username"
                    value={data.email}
                    onChange={(event) => {
                        setData('email', event.target.value);
                        clearErrors('email');
                    }}
                    placeholder="email@cong-ty.cua-ban"
                    aria-invalid={!!fieldErrors.email}
                    required
                />
            </div>
            {fieldErrors.email ? <p className="public-login-error">{fieldErrors.email}</p> : null}

            <label htmlFor="password">{t('auth.password')}</label>
            <div className={`public-login-input ${fieldErrors.password ? 'has-error' : ''}`}>
                <LockKeyhole />
                <input
                    id="password"
                    type="password"
                    autoComplete="current-password"
                    value={data.password}
                    onChange={(event) => {
                        setData('password', event.target.value);
                        clearErrors('password');
                    }}
                    aria-invalid={!!fieldErrors.password}
                    required
                />
            </div>
            {fieldErrors.password ? <p className="public-login-error">{fieldErrors.password}</p> : null}

            <label className="public-login-remember">
                <input
                    type="checkbox"
                    checked={data.remember}
                    onChange={(event) => setData('remember', event.target.checked)}
                />
                <span>{t('auth.remember')}</span>
            </label>

            <button type="submit" className="public-login-submit" disabled={processing}>
                <LogIn />
                {processing ? t('auth.logging_in') : t('auth.login')}
            </button>
        </form>
    );
}
