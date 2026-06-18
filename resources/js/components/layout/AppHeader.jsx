import { usePage } from '@inertiajs/react';

import { SidebarTrigger } from '@/components/ui/sidebar';
import { useRoleLabel } from '@/hooks/use-labels';
import { PageInfoButton } from '@/components/layout/PageInfoButton';
import { LanguageToggle } from '@/components/layout/LanguageToggle';
import { ThemeToggle } from '@/components/layout/ThemeToggle';
import { NotificationBell } from '@/components/layout/NotificationBell';
import { UserMenu } from '@/components/layout/UserMenu';

export function AppHeader() {
    const { auth } = usePage().props;
    const roleLabel = useRoleLabel(auth.user?.role);

    return (
        <header className="sticky top-0 z-10 flex h-12 shrink-0 items-center gap-2 border-b border-border/80 bg-background/85 px-3 backdrop-blur-md sm:px-4">
            <SidebarTrigger />
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-foreground sm:hidden">
                    {auth.user?.name}
                </p>
                <p className="hidden truncate text-sm text-muted-foreground sm:block">
                    <span className="font-medium text-foreground">{auth.user?.name}</span>
                    {roleLabel && (
                        <span className="text-muted-foreground"> · {roleLabel}</span>
                    )}
                </p>
            </div>
            <div className="flex items-center gap-0.5">
                <PageInfoButton />
                <LanguageToggle />
                <ThemeToggle />
                <NotificationBell />
                <UserMenu />
            </div>
        </header>
    );
}
