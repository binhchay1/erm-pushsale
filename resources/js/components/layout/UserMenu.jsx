import { Link, usePage } from '@inertiajs/react';

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useOrgLevelLabel, useRoleLabel } from '@/hooks/use-labels';
import { useT } from '@/providers/I18nProvider';

export function UserMenu({ variant = 'avatar' }) {
    const { auth } = usePage().props;
    const t = useT();
    const user = auth.user;
    const orgLevelLabel = useOrgLevelLabel(user?.org_level);
    const roleLabel = useRoleLabel(user?.role);

    if (!user) return null;

    const username = user.email ? user.email.split('@')[0] : user.name;
    const badgeLabel = variant === 'pushsale' || variant === 'header' ? 'Premium' : (roleLabel || orgLevelLabel || 'Premium');

    if (variant === 'pushsale' || variant === 'header') {
        return (
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <button type="button" className="pushsale-user-trigger" aria-label="Mở menu tài khoản">
                        <span className="pushsale-user-copy">
                            <strong>{username}</strong>
                            <small><i className="fa fa-credit-card" aria-hidden="true" /> {badgeLabel}</small>
                        </span>
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    sideOffset={0}
                    className="pushsale-user-dropdown"
                >
                    <DropdownMenuItem asChild className="pushsale-user-dropdown-item">
                        <Link href="/profile">
                            <i className="fa fa-user" aria-hidden="true" />
                            <span>Thông tin cá nhân</span>
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild className="pushsale-user-dropdown-item">
                        <Link href="/profile#password">
                            <i className="fa fa-lock" aria-hidden="true" />
                            <span>Thay đổi mật khẩu</span>
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator className="pushsale-user-dropdown-separator" />
                    <DropdownMenuItem asChild className="pushsale-user-dropdown-item">
                        <Link href="/logout" method="post" as="button" className="w-full">
                            <i className="fa fa-power-off" aria-hidden="true" />
                            <span>Thoát</span>
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon-sm" className="rounded-full p-0" aria-label={t('common.account')}>
                    <Avatar className="size-8">
                        {user.avatar_url ? <AvatarImage src={user.avatar_url} alt={user.name} /> : null}
                        <AvatarFallback>{user.initials}</AvatarFallback>
                    </Avatar>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuItem asChild><Link href="/profile">{t('common.profile')}</Link></DropdownMenuItem>
                <DropdownMenuItem asChild><Link href="/settings">{t('common.settings')}</Link></DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href="/logout" method="post" as="button" className="w-full">{t('common.logout')}</Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
