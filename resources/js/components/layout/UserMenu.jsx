import { Link, usePage } from '@inertiajs/react';
import { LogOut, Settings, User } from 'lucide-react';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';

export function UserMenu() {
    const { auth } = usePage().props;
    const user = auth.user;

    if (!user) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    className="rounded-full p-0 transition-transform hover:scale-[1.02] active:scale-[0.98]"
                    aria-label="Tài khoản"
                >
                    <Avatar className="size-8">
                        {user.avatar_url ? (
                            <AvatarImage src={user.avatar_url} alt={user.name} />
                        ) : null}
                        <AvatarFallback>{user.initials}</AvatarFallback>
                    </Avatar>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel className="font-normal">
                    <p className="truncate text-sm font-medium text-foreground">{user.name}</p>
                    <p className="truncate text-xs text-muted-foreground">{user.email}</p>
                    {user.org_level_label && (
                        <p className="mt-0.5 truncate text-xs text-muted-foreground">{user.org_level_label}</p>
                    )}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href="/profile" className="cursor-pointer">
                        <User className="size-4" />
                        Hồ sơ cá nhân
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link href="/settings" className="cursor-pointer">
                        <Settings className="size-4" />
                        Cài đặt
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        className="w-full cursor-pointer text-destructive focus:text-destructive"
                    >
                        <LogOut className="size-4" />
                        Đăng xuất
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
