import { Link } from '@inertiajs/react';
import { Bell, Settings } from 'lucide-react';

import { Button } from '@/components/ui/button';

export function NotificationBell() {
    return (
        <Button variant="ghost" size="icon-sm" asChild title="Cài đặt thông báo">
            <Link href="/settings">
                <Bell className="size-4" />
            </Link>
        </Button>
    );
}

export function SettingsLink() {
    return (
        <Button variant="ghost" size="sm" asChild>
            <Link href="/settings">
                <Settings className="size-4" />
                <span className="hidden md:inline">Cài đặt</span>
            </Link>
        </Button>
    );
}
