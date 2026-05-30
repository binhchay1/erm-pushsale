import { Link, usePage } from '@inertiajs/react';

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

function isNavActive(itemUrl, currentUrl) {
    if (itemUrl === '/') {
        return currentUrl === '/';
    }

    return currentUrl === itemUrl || currentUrl.startsWith(`${itemUrl}/`);
}

export function AppSidebar() {
    const { props, url } = usePage();
    const { auth, navigation = [] } = props;
    const isAdmin = auth.user?.role === 'admin';
    const roleLabel = auth.user?.role_label ?? 'Người dùng';

    return (
        <Sidebar>
            <SidebarContent>
                <SidebarGroup>
                    <SidebarGroupLabel className="text-xs font-bold uppercase tracking-wider text-primary">
                        ERM SaleOps
                    </SidebarGroupLabel>
                </SidebarGroup>

                {navigation.map((group, index) => (
                    <SidebarGroup key={group.label ?? `group-${index}`}>
                        {group.label && (
                            <SidebarGroupLabel className="text-xs text-muted-foreground">
                                {group.label}
                            </SidebarGroupLabel>
                        )}
                        <SidebarGroupContent>
                            <SidebarMenu>
                                {group.items.map((item) => {
                                    const Icon = navigationIcons[item.icon] ?? navigationIcons.home;

                                    return (
                                        <SidebarMenuItem key={item.url}>
                                            <SidebarMenuButton
                                                asChild
                                                tooltip={item.title}
                                                isActive={isNavActive(item.url, url)}
                                            >
                                                <Link href={item.url}>
                                                    <Icon className="size-4" />
                                                    <span>{item.title}</span>
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
                {isAdmin ? 'Quản trị hệ thống' : roleLabel}
            </SidebarFooter>
        </Sidebar>
    );
}
