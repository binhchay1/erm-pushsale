import { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { Copy, LayoutDashboard, Users } from 'lucide-react';
import { toast } from 'sonner';

import GuestLayout from '@/layouts/GuestLayout';
import { LoginForm } from '@/components/auth/LoginForm';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
    const tagline = brand?.tagline ?? '';
    const [open, setOpen] = useState(false);

    const copy = async (value) => {
        try {
            await navigator.clipboard.writeText(value);
            toast.success(t('auth.copied'));
        } catch {
            // Trình duyệt chặn clipboard — bỏ qua.
        }
    };

    return (
        <GuestLayout>
            <Head title={t('auth.login_title')} />

            <div className="relative z-10 w-full max-w-md">
                <div className="mb-6 flex flex-col items-center gap-2 text-center">
                    <div className="flex size-12 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-md">
                        <LayoutDashboard className="size-6" />
                    </div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">{name}</h1>
                    {tagline && <p className="text-sm text-muted-foreground">{tagline}</p>}
                </div>

                <Card className="border-border/80 shadow-lg shadow-blue-500/5">
                    <CardHeader>
                        <CardTitle>{t('auth.login_title')}</CardTitle>
                        <CardDescription>{t('auth.login_desc')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <LoginForm />
                        <p className="mt-4 text-center text-xs text-muted-foreground">{t('auth.contact_hint')}</p>
                    </CardContent>
                </Card>

                <div className="mt-4 flex flex-col items-center gap-2 text-center text-xs text-muted-foreground">
                    <p>
                        {t('auth.demo_password')}{' '}
                        <code className="rounded bg-muted px-1 py-0.5">{demoPassword}</code>
                    </p>
                    {demoAccounts.length > 0 && (
                        <Dialog open={open} onOpenChange={setOpen}>
                            <DialogTrigger asChild>
                                <Button type="button" variant="outline" size="sm" className="gap-1.5">
                                    <Users className="size-3.5" />
                                    {t('auth.demo_accounts_note')}
                                </Button>
                            </DialogTrigger>
                            <DialogContent className="max-h-[85vh] max-w-2xl overflow-y-auto">
                                <DialogHeader>
                                    <DialogTitle>{t('auth.demo_accounts_title')}</DialogTitle>
                                    <DialogDescription>{t('auth.demo_accounts_desc')}</DialogDescription>
                                </DialogHeader>

                                <div className="flex items-center justify-between rounded-lg border bg-muted/40 px-3 py-2 text-sm">
                                    <span className="text-muted-foreground">{t('auth.demo_shared_password')}</span>
                                    <button
                                        type="button"
                                        onClick={() => copy(demoPassword)}
                                        className="flex items-center gap-1.5 font-mono font-medium hover:text-primary"
                                    >
                                        {demoPassword}
                                        <Copy className="size-3.5" />
                                    </button>
                                </div>

                                <div className="space-y-4">
                                    {demoAccounts.map((group) => (
                                        <div key={group.key} className="space-y-2">
                                            <h3 className="text-sm font-semibold text-foreground">{group.label}</h3>
                                            <div className="overflow-hidden rounded-lg border">
                                                {group.accounts.map((acc) => (
                                                    <div
                                                        key={acc.email}
                                                        className="flex flex-col gap-1 border-b border-border/60 px-3 py-2 last:border-0 sm:flex-row sm:items-center sm:justify-between"
                                                    >
                                                        <div className="min-w-0">
                                                            <p className="text-sm font-medium">{acc.position}</p>
                                                            <p className="text-xs text-muted-foreground">{acc.desc}</p>
                                                        </div>
                                                        <button
                                                            type="button"
                                                            onClick={() => copy(acc.email)}
                                                            className="flex shrink-0 items-center gap-1.5 self-start rounded-md bg-muted px-2 py-1 font-mono text-xs hover:text-primary sm:self-center"
                                                        >
                                                            {acc.email}
                                                            <Copy className="size-3" />
                                                        </button>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </DialogContent>
                        </Dialog>
                    )}
                </div>
            </div>
        </GuestLayout>
    );
}
