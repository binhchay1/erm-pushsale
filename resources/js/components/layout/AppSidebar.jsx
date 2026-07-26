import { Link, usePage } from '@inertiajs/react';
import { createPortal } from 'react-dom';

import {
    DEFAULT_MENU_ICONS,
    keyContains,
    menuNumber,
    usePushsaleSidebarMenu,
} from '@/hooks/usePushsaleSidebarMenu';
import { cn } from '@/lib/utils';

function LeafLink({ item, className, onNavigate, children }) {
    if (item.url && !item.disabled) {
        return (
            <Link
                href={item.url}
                className={className}
                aria-label={item.title}
                preserveScroll={false}
                onClick={onNavigate}
            >
                {children}
            </Link>
        );
    }

    return (
        <span className={cn(className, 'is-disabled')} aria-label={`${item.title} — chưa có màn hình tương ứng`}>
            {children}
        </span>
    );
}

function ThirdLevelFlyout({ flyout, activeKey, onNavigate, onSelect, onClose, onMouseEnter, onMouseLeave }) {
    if (!flyout || typeof document === 'undefined') return null;

    return createPortal(
        <div
            className="pushsale-third-menu is-visible"
            style={{ top: flyout.top, maxHeight: flyout.maxHeight }}
            role="menu"
            aria-label={flyout.item.title}
            onMouseEnter={onMouseEnter}
            onMouseLeave={onMouseLeave}
        >
            <ul className="ul3">
                {(flyout.item.children ?? []).map((child, index) => {
                    const childKey = `${flyout.key}.${index}`;
                    const active = keyContains(activeKey, childKey);

                    return (
                        <li key={childKey} className={cn('li3 pushsale-third-item', active && 'active')}>
                            <LeafLink
                                item={child}
                                className="a3 pushsale-third-link"
                                onNavigate={() => {
                                    onSelect?.(child);
                                    onClose();
                                    onNavigate?.();
                                }}
                            >
                                <span>{child.title}</span>
                                {(child.children?.length ?? 0) > 0 && (
                                    <i className="fa fa-angle-right" aria-hidden="true" />
                                )}
                            </LeafLink>
                        </li>
                    );
                })}
            </ul>
        </div>,
        document.body,
    );
}

export function AppSidebar({ collapsed = true, onNavigate }) {
    const { props, url } = usePage();
    const { navigation = [], activeMenuCode = null } = props;

    const {
        sidebarRef,
        activeKey,
        openRoot,
        flyout,
        hoverSecondKey,
        toggleRoot,
        toggleFlyout,
        openFlyoutFor,
        closeFlyout,
        scheduleFlyoutClose,
        clearFlyoutTimer,
        rememberSelection,
        onSecondEnter,
        onSecondLeave,
    } = usePushsaleSidebarMenu({
        navigation,
        url,
        activeMenuCode,
        collapsed,
    });

    return (
        <>
            <aside className="main-sidebar left-side pushsale-main-sidebar" aria-label="Điều hướng chính">
                <section
                    className="sidebar pushsale-sidebar"
                    ref={sidebarRef}
                    onMouseEnter={clearFlyoutTimer}
                    onMouseLeave={() => scheduleFlyoutClose()}
                >
                    <ul className="sidebar-menu ul1">
                        {navigation.map((root, rootIndex) => {
                            const rootKey = `root.${rootIndex}`;
                            const rootOpen = openRoot === rootIndex;
                            const rootActive = keyContains(activeKey, rootKey);
                            const icon = root.icon ?? DEFAULT_MENU_ICONS[menuNumber(root.title)] ?? 'circle-o';
                            const hasChildren = (root.children?.length ?? 0) > 0;

                            return (
                                <li
                                    key={rootKey}
                                    className={cn('treeview li1', rootOpen && 'menu-open is-open', rootActive && 'active')}
                                >
                                    {hasChildren ? (
                                        <button
                                            type="button"
                                            className="a1 pushsale-menu-link"
                                            onClick={() => toggleRoot(rootIndex)}
                                            aria-expanded={rootOpen}
                                            aria-label={root.title}
                                        >
                                            <i className={`fa fa-${icon}`} aria-hidden="true" />
                                            <span>{root.title}</span>
                                            <i
                                                className={cn('i1 fa pull-right', rootOpen ? 'fa-minus' : 'fa-plus')}
                                                aria-hidden="true"
                                            />
                                        </button>
                                    ) : (
                                        <LeafLink
                                            item={root}
                                            className="a1 pushsale-menu-link"
                                            onNavigate={() => {
                                                rememberSelection(root);
                                                onNavigate?.();
                                            }}
                                        >
                                            <i className={`fa fa-${icon}`} aria-hidden="true" />
                                            <span>{root.title}</span>
                                        </LeafLink>
                                    )}

                                    {hasChildren && (
                                        <ul className={cn('treeview-menu ul2', rootOpen && 'is-open')}>
                                            {root.children.map((child, childIndex) => {
                                                const key = `${rootKey}.${childIndex}`;
                                                const hasGrandchildren = (child.children?.length ?? 0) > 0;
                                                const childActive = keyContains(activeKey, key);
                                                const flyoutOpen = flyout?.key === key;

                                                return (
                                                    <li
                                                        key={key}
                                                        className={cn(
                                                            'li2',
                                                            childActive && 'active',
                                                            flyoutOpen && 'flyout-open',
                                                            hoverSecondKey === key && !childActive && 'ui-hover',
                                                        )}
                                                        onMouseEnter={() => onSecondEnter(key, hasGrandchildren)}
                                                        onMouseLeave={() => onSecondLeave(key)}
                                                    >
                                                        {hasGrandchildren ? (
                                                            <button
                                                                type="button"
                                                                className="a2 pushsale-menu-link pushsale-second-parent-link"
                                                                data-pushsale-second-parent="true"
                                                                onClick={(event) => toggleFlyout(event.currentTarget, child, key)}
                                                                onMouseEnter={(event) => openFlyoutFor(event.currentTarget, child, key)}
                                                                aria-expanded={flyoutOpen}
                                                                aria-label={child.title}
                                                            >
                                                                <span>{child.title}</span>
                                                                <i className="fa fa-angle-right pull-right" aria-hidden="true" />
                                                            </button>
                                                        ) : (
                                                            <LeafLink
                                                                item={child}
                                                                className="a2 pushsale-menu-link"
                                                                onNavigate={() => {
                                                                    rememberSelection(child);
                                                                    closeFlyout();
                                                                    onNavigate?.();
                                                                }}
                                                            >
                                                                <span>{child.title}</span>
                                                            </LeafLink>
                                                        )}
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                </section>
            </aside>

            {!collapsed && (
                <ThirdLevelFlyout
                    flyout={flyout}
                    activeKey={activeKey}
                    onNavigate={onNavigate}
                    onSelect={rememberSelection}
                    onClose={closeFlyout}
                    onMouseEnter={clearFlyoutTimer}
                    onMouseLeave={() => scheduleFlyoutClose()}
                />
            )}
        </>
    );
}
