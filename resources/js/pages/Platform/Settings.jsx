import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, AtSign, Building2, KeyRound, Settings2 } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/providers/I18nProvider';

export default function PlatformSettings({ tenant }) {
    const t = useT();
    const { data, setData, put, processing, errors, recentlySuccessful } = useForm({
        internal_name: tenant.internal_name ?? '',
        email_domain: tenant.email_domain ?? 'saleops.local',
        default_password: '',
    });

    const submit = (e) => {
        e.preventDefault();
        put('/platform/settings', { preserveScroll: true });
    };

    const domain = data.email_domain || 'saleops.local';
    const slug = tenant.internal_slug ?? 'internal';

    return (
        <AppLayout activeMenuCode="10.1.5">
            <Head title={t('pages.platform.settings_title')} />

            <div className="mx-auto max-w-2xl space-y-6">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon-sm" asChild>
                        <Link href="/admin/system/settings">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div className="flex items-center gap-2">
                        <Settings2 className="size-5 text-primary" />
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">{t('pages.platform.settings_title')}</h1>
                            <p className="text-sm text-muted-foreground">{t('pages.platform.settings_desc')}</p>
                        </div>
                    </div>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <AtSign className="size-4" /> {t('pages.platform.settings_identity_title')}
                            </CardTitle>
                            <CardDescription>{t('pages.platform.settings_identity_desc')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-1.5">
                                <Label htmlFor="email_domain">{t('pages.platform.settings_email_domain')}</Label>
                                <Input
                                    id="email_domain"
                                    value={data.email_domain}
                                    onChange={(e) => setData('email_domain', e.target.value)}
                                    placeholder="saleops.local"
                                    required
                                />
                                {errors.email_domain && <p className="text-xs text-destructive">{errors.email_domain}</p>}
                            </div>

                            <div className="rounded-lg border bg-muted/30 p-3 text-sm">
                                <p className="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    {t('pages.platform.settings_preview')}
                                </p>
                                <div className="space-y-1.5">
                                    <div className="flex items-center gap-2">
                                        <Building2 className="size-3.5 text-primary" />
                                        <span className="text-muted-foreground">{t('pages.platform.settings_preview_internal')}:</span>
                                        <code className="rounded bg-background px-1.5 py-0.5 font-mono text-xs">
                                            admin@{domain}
                                        </code>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Building2 className="size-3.5 text-muted-foreground" />
                                        <span className="text-muted-foreground">{t('pages.platform.settings_preview_company')}:</span>
                                        <code className="rounded bg-background px-1.5 py-0.5 font-mono text-xs">
                                            admin@acme.{domain}
                                        </code>
                                    </div>
                                </div>
                                <p className="mt-2 text-xs text-muted-foreground">
                                    {t('pages.platform.settings_identity_hint')}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Building2 className="size-4" /> {t('pages.platform.settings_internal_title')}
                            </CardTitle>
                            <CardDescription>{t('pages.platform.settings_internal_desc')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-1.5">
                                <Label htmlFor="internal_name">{t('pages.platform.settings_internal_name')}</Label>
                                <Input
                                    id="internal_name"
                                    value={data.internal_name}
                                    onChange={(e) => setData('internal_name', e.target.value)}
                                    required
                                />
                                {errors.internal_name && <p className="text-xs text-destructive">{errors.internal_name}</p>}
                                <p className="text-xs text-muted-foreground">
                                    {t('pages.platform.settings_internal_slug_fixed')}: <code className="font-mono">{slug}</code>
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <KeyRound className="size-4" /> {t('pages.platform.settings_default_password')}
                            </CardTitle>
                            <CardDescription>{t('pages.platform.settings_password_desc')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-1.5">
                                <Label htmlFor="default_password">{t('pages.platform.settings_default_password')}</Label>
                                <Input
                                    id="default_password"
                                    type="text"
                                    value={data.default_password}
                                    onChange={(e) => setData('default_password', e.target.value)}
                                    placeholder={tenant.default_password ? '••••••••' : 'password'}
                                />
                                {errors.default_password && <p className="text-xs text-destructive">{errors.default_password}</p>}
                                <p className="text-xs text-muted-foreground">{t('pages.platform.settings_password_hint')}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex items-center justify-end gap-3">
                        {recentlySuccessful && <span className="text-sm text-emerald-600">{t('common.saved_success')}</span>}
                        <Button type="submit" disabled={processing}>{processing ? t('common.saving') : t('common.save')}</Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
