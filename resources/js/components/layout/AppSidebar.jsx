import { Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Minus, Plus } from 'lucide-react';

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
import { cn } from '@/lib/utils';
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

function groupHasLabel(group) {
    return Boolean(group.label_key || group.label);
}

function groupIsActive(group, currentUrl) {
    return group.items.some((item) => isNavActive(item.url, currentUrl));
}

function NavItems({ items, currentUrl, t }) {
    return (
        <SidebarMenu>
            {items.map((item) => {
                const Icon = navigationIcons[item.icon] ?? navigationIcons.home;
                const title = navItemTitle(t, item);

                return (
                    <SidebarMenuItem key={item.url}>
                        <SidebarMenuButton
                            asChild
                            tooltip={title}
                            isActive={isNavActive(item.url, currentUrl)}
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
    );
}

export function AppSidebar() {
    const t = useT();
    const { props, url } = usePage();
    const { auth, navigation = [] } = props;
    const isAdmin = auth.user?.role === 'admin';
    const roleLabel = useRoleLabel(auth.user?.role) || t('dashboard.sidebar.user_fallback');
    const contentRef = useRef(null);

    const [openGroups, setOpenGroups] = useState({});

    useEffect(() => {
        setOpenGroups((prev) => {
            const next = { ...prev };
            navigation.forEach((group, index) => {
                if (!groupHasLabel(group)) {
                    return;
                }
                const key = group.label_key ?? group.label ?? `group-${index}`;
                if (groupIsActive(group, url)) {
                    next[key] = true;
                }
            });
            return next;
        });
    }, [navigation, url]);

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

    const toggleGroup = (key) =>
        setOpenGroups((prev) => ({ ...prev, [key]: !prev[key] }));

    let labeledIndex = 0;

    return (
        <Sidebar className="!top-(--topbar-height) !bottom-auto !h-[calc(100svh-var(--topbar-height))]">
            <SidebarContent ref={contentRef}>
                <SidebarGroup>
                    <SidebarGroupLabel className="text-xs font-bold uppercase tracking-wider text-primary">
                        ERM SaleOps
                    </SidebarGroupLabel>
                </SidebarGroup>

                {navigation.map((group, index) => {
                    const key = group.label_key ?? group.label ?? `group-${index}`;

                    if (!groupHasLabel(group)) {
                        return (
                            <SidebarGroup key={key}>
                                <SidebarGroupContent>
                                    <NavItems items={group.items} currentUrl={url} t={t} />
                                </SidebarGroupContent>
                            </SidebarGroup>
                        );
                    }

                    labeledIndex += 1;
                    const number = labeledIndex;
                    const isOpen = openGroups[key] ?? false;
                    const active = groupIsActive(group, url);

                    return (
                        <SidebarGroup key={key}>
                            <button
                                type="button"
                                onClick={() => toggleGroup(key)}
                                aria-expanded={isOpen}
                                className={cn(
                                    'flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-xs font-semibold transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                                    active ? 'text-primary' : 'text-muted-foreground',
                                )}
                            >
                                <span className="tabular-nums">{number}.</span>
                                <span className="flex-1 truncate">{navGroupLabel(t, group)}</span>
                                {isOpen ? (
                                    <Minus className="size-3.5 shrink-0 text-primary" />
                                ) : (
                                    <Plus className="size-3.5 shrink-0" />
                                )}
                            </button>
                            {isOpen && (
                                <SidebarGroupContent className="mt-1">
                                    <NavItems items={group.items} currentUrl={url} t={t} />
                                </SidebarGroupContent>
                            )}
                        </SidebarGroup>
                    );
                })}
            </SidebarContent>
            <SidebarFooter className="px-3 py-2 text-xs text-muted-foreground">
                {isAdmin ? t('dashboard.sidebar.admin_footer') : roleLabel}
            </SidebarFooter>
        </Sidebar>
    );
}
