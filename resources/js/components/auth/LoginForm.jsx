import { useForm } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/providers/I18nProvider';

export function LoginForm() {
    const t = useT();
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post('/login');
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="space-y-2">
                <Label htmlFor="email">{t('auth.email')}</Label>
                <Input
                    id="email"
                    type="email"
                    autoComplete="username"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    placeholder="admin@saleops.local"
                    aria-invalid={!!errors.email}
                />
                {errors.email && (
                    <p className="text-sm text-destructive">{errors.email}</p>
                )}
            </div>

            <div className="space-y-2">
                <Label htmlFor="password">{t('auth.password')}</Label>
                <Input
                    id="password"
                    type="password"
                    autoComplete="current-password"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    aria-invalid={!!errors.password}
                />
                {errors.password && (
                    <p className="text-sm text-destructive">{errors.password}</p>
                )}
            </div>

            <label className="flex cursor-pointer items-center gap-2 text-sm text-muted-foreground">
                <input
                    type="checkbox"
                    className="size-4 rounded border-input accent-primary"
                    checked={data.remember}
                    onChange={(e) => setData('remember', e.target.checked)}
                />
                {t('auth.remember')}
            </label>

            <Button type="submit" className="w-full" size="lg" disabled={processing}>
                {processing ? t('auth.logging_in') : t('auth.login')}
            </Button>
        </form>
    );
}
