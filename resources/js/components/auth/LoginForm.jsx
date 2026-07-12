import { useForm } from '@inertiajs/react';
import { LockKeyhole, LogIn, Mail } from 'lucide-react';

import { useT } from '@/providers/I18nProvider';

export function LoginForm() {
    const t = useT();
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (event) => {
        event.preventDefault();
        post('/login');
    };

    return (
        <form onSubmit={submit} className="public-login-form">
            <label htmlFor="email">{t('auth.email')}</label>
            <div className={`public-login-input ${errors.email ? 'has-error' : ''}`}>
                <Mail />
                <input
                    id="email"
                    type="email"
                    autoComplete="username"
                    value={data.email}
                    onChange={(event) => setData('email', event.target.value)}
                    placeholder="admin@saleops.local"
                    aria-invalid={!!errors.email}
                />
            </div>
            {errors.email && <p className="public-login-error">{errors.email}</p>}

            <label htmlFor="password">{t('auth.password')}</label>
            <div className={`public-login-input ${errors.password ? 'has-error' : ''}`}>
                <LockKeyhole />
                <input
                    id="password"
                    type="password"
                    autoComplete="current-password"
                    value={data.password}
                    onChange={(event) => setData('password', event.target.value)}
                    aria-invalid={!!errors.password}
                />
            </div>
            {errors.password && <p className="public-login-error">{errors.password}</p>}

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
