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
import { useOrgLevelLabel, useRoleLabel } from '@/hooks/use-labels';
import { useT } from '@/providers/I18nProvider';

export function UserMenu({ variant = 'avatar' }) {
    const { auth } = usePage().props;
    const t = useT();
    const user = auth.user;
    const orgLevelLabel = useOrgLevelLabel(user?.org_level);
    const roleLabel = useRoleLabel(user?.role);

    if (!user) {
        return null;
    }

    const username = user.email ? user.email.split('@')[0] : user.name;
    const badgeLabel = roleLabel || orgLevelLabel;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                {variant === 'header' ? (
                    <button type="button" className="user-menu-header-trigger">
                        <span className="user-avatar-circle">
                            <span className="user-avatar-initials">{user.initials}</span>
                        </span>
                        <span className="user-block hidden-xs">
                            <span className="user-name">
                                {username}
                                {badgeLabel ? (
                                    <>
                                        <br />
                                        <span className="user-role">{badgeLabel}</span>
                                    </>
                                ) : null}
                            </span>
                        </span>
                    </button>
                ) : (
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        className="rounded-full p-0 transition-transform hover:scale-[1.02] active:scale-[0.98]"
                        aria-label={t('common.account')}
                    >
                        <Avatar className="size-8">
                            {user.avatar_url ? (
                                <AvatarImage src={user.avatar_url} alt={user.name} />
                            ) : null}
                            <AvatarFallback>{user.initials}</AvatarFallback>
                        </Avatar>
                    </Button>
                )}
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel className="font-normal">
                    <p className="truncate text-sm font-medium text-foreground">{user.name}</p>
                    <p className="truncate text-xs text-muted-foreground">{username}</p>
                    {badgeLabel && (
                        <p className="mt-0.5 truncate text-xs text-muted-foreground">{badgeLabel}</p>
                    )}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href="/profile" className="cursor-pointer">
                        <User className="size-4" />
                        {t('common.profile')}
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link href="/settings" className="cursor-pointer">
                        <Settings className="size-4" />
                        {t('common.settings')}
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
                        {t('common.logout')}
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
