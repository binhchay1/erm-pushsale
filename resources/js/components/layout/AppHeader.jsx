import { Link, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';

import { SidebarTrigger } from '@/components/ui/sidebar';
import { Button } from '@/components/ui/button';
import { ThemeToggle } from '@/components/layout/ThemeToggle';
import { NotificationBell, SettingsLink } from '@/components/layout/NotificationBell';

export function AppHeader() {
    const { auth } = usePage().props;

    return (
        <header className="sticky top-0 z-10 flex h-14 shrink-0 items-center gap-2 border-b border-border bg-background/90 px-4 backdrop-blur-sm">
            <SidebarTrigger />
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-foreground">
                    Xin chào, {auth.user?.name}
                </p>
                <p className="truncate text-xs text-muted-foreground">{auth.user?.role_label}</p>
            </div>
            <ThemeToggle />
            <NotificationBell />
            <SettingsLink />
            <Button variant="ghost" size="sm" asChild>
                <Link href="/logout" method="post" as="button">
                    <LogOut className="size-4" />
                    <span className="hidden sm:inline">Đăng xuất</span>
                </Link>
            </Button>
        </header>
    );
}
