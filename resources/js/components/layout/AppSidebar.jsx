import { Link, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

import { navigationIcons } from '@/components/layout/navigation-icons';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useRoleLabel } from '@/hooks/use-labels';
import { useT } from '@/providers/I18nProvider';

function isNavActive(itemUrl, currentUrl) {
    if (itemUrl === '/') {
        return currentUrl === '/';
    }

    return currentUrl === itemUrl || currentUrl.startsWith(`${itemUrl}/`);
}

function navItemTitle(t, item) {
    if (item.title_key) {
        return t(`nav.items.${item.title_key}`);
    }

    return item.title ?? '';
}

function navGroupLabel(t, group) {
    if (group.label_key) {
        return t(`nav.groups.${group.label_key}`);
    }

    return group.label ?? '';
}

export function AppSidebar() {
    const t = useT();
    const { props, url } = usePage();
    const { auth, navigation = [] } = props;
    const isAdmin = auth.user?.role === 'admin';
    const roleLabel = useRoleLabel(auth.user?.role) || t('dashboard.sidebar.user_fallback');
    const contentRef = useRef(null);

    useEffect(() => {
        const frame = requestAnimationFrame(() => {
            const root = contentRef.current;
            if (!root) {
                return;
            }

            const active = root.querySelector('[data-sidebar="menu-button"][data-active="true"]');
            active?.scrollIntoView({ block: 'nearest', behavior: 'instant' });
        });

        return () => cancelAnimationFrame(frame);
    }, [url]);

    return (
        <Sidebar>
            <SidebarContent ref={contentRef}>
                <SidebarGroup>
                    <SidebarGroupLabel className="text-xs font-bold uppercase tracking-wider text-primary">
                        ERM SaleOps
                    </SidebarGroupLabel>
                </SidebarGroup>

                {navigation.map((group, index) => (
                    <SidebarGroup key={group.label_key ?? group.label ?? `group-${index}`}>
                        {(group.label_key || group.label) && (
                            <SidebarGroupLabel className="text-xs text-muted-foreground">
                                {navGroupLabel(t, group)}
                            </SidebarGroupLabel>
                        )}
                        <SidebarGroupContent>
                            <SidebarMenu>
                                {group.items.map((item) => {
                                    const Icon = navigationIcons[item.icon] ?? navigationIcons.home;
                                    const title = navItemTitle(t, item);

                                    return (
                                        <SidebarMenuItem key={item.url}>
                                            <SidebarMenuButton
                                                asChild
                                                tooltip={title}
                                                isActive={isNavActive(item.url, url)}
                                            >
                                                <Link href={item.url}>
                                                    <Icon className="size-4" />
                                                    <span>{title}</span>
                                                </Link>
                                            </SidebarMenuButton>
                                        </SidebarMenuItem>
                                    );
                                })}
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </SidebarGroup>
                ))}
            </SidebarContent>
            <SidebarFooter className="px-3 py-2 text-xs text-muted-foreground">
                {isAdmin ? t('dashboard.sidebar.admin_footer') : roleLabel}
            </SidebarFooter>
        </Sidebar>
    );
}
