import { Head, usePage } from '@inertiajs/react';

import GuestLayout from '@/layouts/GuestLayout';
import { LoginForm } from '@/components/auth/LoginForm';
import { useT } from '@/providers/I18nProvider';

export default function Login() {
    const { brand } = usePage().props;
    const t = useT();
    const name = brand?.name ?? 'ERM SaleOps';
    const tagline = brand?.tagline ?? 'Hệ thống điều hành bán hàng & vận hành';

    return (
        <GuestLayout>
            <Head title={t('auth.login_title')} />

            <section className="public-login-panel">
                <div className="public-login-heading">
                    <span>ĐĂNG NHẬP HỆ THỐNG</span>
                    <h2>{name}</h2>
                    <p>{tagline}</p>
                </div>

                <LoginForm />
                <p className="public-login-contact">{t('auth.contact_hint')}</p>
            </section>
        </GuestLayout>
    );
}
