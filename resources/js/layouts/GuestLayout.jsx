import { Link, usePage } from '@inertiajs/react';
import { BarChart3, CheckCircle2, ShieldCheck, UsersRound } from 'lucide-react';

import { LanguageToggle } from '@/components/layout/LanguageToggle';
import { LocaleSync } from '@/components/layout/LocaleSync';

export default function GuestLayout({ children }) {
    const { brand } = usePage().props;
    const name = brand?.name ?? 'ERM SaleOps';

    return (
        <div className="public-auth-shell">
            <LocaleSync />
            <aside className="public-auth-intro">
                <Link href="/" className="public-auth-brand">
                    <span><BarChart3 /></span>
                    <strong>{name}</strong>
                </Link>
                <div className="public-auth-message">
                    <p className="public-auth-eyebrow">HỆ THỐNG ĐIỀU HÀNH BÁN HÀNG &amp; VẬN HÀNH</p>
                    <h1>Quản trị dữ liệu, đơn hàng và hiệu suất trên một nền tảng.</h1>
                    <p>Theo dõi toàn bộ quy trình từ marketing, telesale, kho, giao hàng đến kế toán theo thời gian thực.</p>
                    <ul>
                        <li><CheckCircle2 /> Phân quyền theo đơn vị và vai trò</li>
                        <li><UsersRound /> Quản lý khách hàng và lịch sử tác nghiệp</li>
                        <li><ShieldCheck /> Nhật ký và bảo mật dữ liệu tập trung</li>
                    </ul>
                </div>
                <small>© {new Date().getFullYear()} {name}</small>
            </aside>

            <main className="public-auth-main">
                <div className="public-auth-language"><LanguageToggle /></div>
                {children}
            </main>
        </div>
    );
}
