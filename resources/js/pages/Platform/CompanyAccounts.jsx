import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Copy } from 'lucide-react';
import { useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { StatusBadge } from '@/components/ui/status-badge';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { useLabels, useRoleLabel } from '@/hooks/use-labels';
import { useTableSort } from '@/hooks/use-table-sort';
import { useT } from '@/providers/I18nProvider';

function CopyBtn({ value, t }) {
    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            className="size-7"
            onClick={() => navigator.clipboard.writeText(value)}
            title={t('common.copy')}
        >
            <Copy className="size-3.5" />
        </Button>
    );
}

function AccountRow({ row, t }) {
    const roleLabel = useRoleLabel(row.role);

    return (
        <tr>
            <Td>{roleLabel}</Td>
            <Td>
                <code className="text-xs">{row.email}</code>
            </Td>
            <Td>
                <CopyBtn value={row.email} t={t} />
            </Td>
        </tr>
    );
}

export default function CompanyAccounts({ company, suggested_accounts = [], default_password = 'password' }) {
    const t = useT();
    const labels = useLabels();
    const [copied, setCopied] = useState(false);
    const { sortedRows, sort, toggleSort } = useTableSort(suggested_accounts, { defaultKey: 'role' });

    const copyAll = () => {
        const lines = suggested_accounts
            .map((r) => `${labels.user_role?.[r.role] ?? r.role}: ${r.email}`)
            .join('\n');
        const text = `${company.name}\nAdmin: ${suggested_accounts.find((r) => r.role === 'admin')?.email ?? ''}\n${t('pages.platform.provision_password')}: ${default_password}\n\n${lines}`;
        navigator.clipboard.writeText(text);
        setCopied(true);
        setTimeout(() => setCopied(false), 1500);
    };

    return (
        <AppLayout>
            <Head title={t('pages.platform.accounts_title')} />

            <div className="mx-auto max-w-2xl space-y-6">
                <Button variant="ghost" size="sm" asChild>
                    <Link href="/platform/companies">
                        <ArrowLeft className="mr-1 size-4" /> {t('common.back')}
                    </Link>
                </Button>

                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-center gap-2">
                            <CardTitle>{t('pages.platform.accounts_title')}</CardTitle>
                            {company.is_internal ? (
                                <StatusBadge tone="muted">{t('pages.platform.internal_badge')}</StatusBadge>
                            ) : null}
                        </div>
                        <CardDescription>{t('pages.platform.accounts_desc')}</CardDescription>
                        <p className="text-sm font-medium">{company.name}</p>
                        <p className="text-xs text-muted-foreground">{company.slug}</p>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="text-sm text-muted-foreground">
                            {t('pages.platform.provision_password')}: <code className="rounded bg-muted px-1">{default_password}</code>
                        </p>
                        <ScrollDataTable>
                            <table className="w-full text-sm">
                                <thead>
                                    <tr>
                                        <Th sortable sortKey="role" sort={sort} onSort={toggleSort}>{t('pages.platform.provision_role')}</Th>
                                        <Th sortable sortKey="email" sort={sort} onSort={toggleSort}>{t('pages.platform.provision_email')}</Th>
                                        <Th className="w-8" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {sortedRows.map((row) => (
                                        <AccountRow key={row.role} row={row} t={t} />
                                    ))}
                                </tbody>
                            </table>
                        </ScrollDataTable>
                        <Button type="button" variant="outline" onClick={copyAll}>
                            {copied ? t('common.copied') : t('common.copy')} — {t('pages.platform.view_accounts')}
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
