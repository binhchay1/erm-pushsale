import { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { Copy, Users } from 'lucide-react';
import { toast } from 'sonner';

import GuestLayout from '@/layouts/GuestLayout';
import { LoginForm } from '@/components/auth/LoginForm';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useT } from '@/providers/I18nProvider';

export default function Login() {
    const { brand, demoAccounts = [], demoPassword = 'password' } = usePage().props;
    const t = useT();
    const name = brand?.name ?? 'ERM SaleOps';
    const tagline = brand?.tagline ?? 'Hệ thống điều hành bán hàng & vận hành';
    const [open, setOpen] = useState(false);

    const copy = async (value) => {
        try {
            await navigator.clipboard.writeText(value);
            toast.success(t('auth.copied'));
        } catch {
            toast.error('Không thể sao chép trên trình duyệt này.');
        }
    };

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

                {demoAccounts.length > 0 && (
                    <div className="public-login-demo">
                        <p>{t('auth.demo_password')} <code>{demoPassword}</code></p>
                        <Dialog open={open} onOpenChange={setOpen}>
                            <DialogTrigger asChild>
                                <button type="button"><Users /> {t('auth.demo_accounts_note')}</button>
                            </DialogTrigger>
                            <DialogContent className="max-h-[85vh] max-w-2xl overflow-y-auto">
                                <DialogHeader>
                                    <DialogTitle>{t('auth.demo_accounts_title')}</DialogTitle>
                                    <DialogDescription>{t('auth.demo_accounts_desc')}</DialogDescription>
                                </DialogHeader>
                                <div className="space-y-4">
                                    <button type="button" onClick={() => copy(demoPassword)} className="flex w-full items-center justify-between rounded-lg border p-3 text-sm">
                                        <span>{t('auth.demo_shared_password')}</span>
                                        <span className="flex items-center gap-2 font-mono">{demoPassword}<Copy className="size-3.5" /></span>
                                    </button>
                                    {demoAccounts.map((group) => (
                                        <div key={group.key} className="space-y-2">
                                            <h3 className="text-sm font-semibold">{group.label}</h3>
                                            <div className="overflow-hidden rounded-lg border">
                                                {group.accounts.map((account) => (
                                                    <button
                                                        type="button"
                                                        key={account.email}
                                                        onClick={() => copy(account.email)}
                                                        className="flex w-full items-center justify-between gap-4 border-b px-3 py-2 text-left last:border-0 hover:bg-muted"
                                                    >
                                                        <span><b className="block text-sm">{account.position}</b><small className="text-muted-foreground">{account.desc}</small></span>
                                                        <span className="flex items-center gap-1 font-mono text-xs">{account.email}<Copy className="size-3" /></span>
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </DialogContent>
                        </Dialog>
                    </div>
                )}
            </section>
        </GuestLayout>
    );
}
