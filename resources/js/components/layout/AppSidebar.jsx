import { Link, usePage } from '@inertiajs/react';
import { createPortal } from 'react-dom';
import { useEffect, useMemo, useRef, useState } from 'react';

import { cn } from '@/lib/utils';

function cleanPath(url = '') {
    const path = String(url).split('?')[0].split('#')[0] || '/';
    return path.length > 1 ? path.replace(/\/+$/, '') : path;
}

function flattenLeaves(items, prefix = 'root') {
    const leaves = [];
    items.forEach((item, index) => {
        const key = `${prefix}.${index}`;
        const children = item.children ?? [];
        if (children.length) leaves.push(...flattenLeaves(children, key));
        else if (item.url && !item.disabled) leaves.push({ item, key });
    });
    return leaves;
}

function resolveActiveKey(navigation, currentUrl, activeMenuCode) {
    const leaves = flattenLeaves(navigation);
    if (activeMenuCode) {
        const byCode = leaves.find(({ item }) => String(item.code ?? '') === String(activeMenuCode));
        if (byCode) return byCode.key;
    }

    const currentPath = cleanPath(currentUrl);
    return leaves.find(({ item }) => cleanPath(item.url) === currentPath)?.key ?? null;
}

function keyContains(activeKey, key) {
    return Boolean(activeKey && (activeKey === key || activeKey.startsWith(`${key}.`)));
}

const defaultIcons = {
    1: 'cog',
    2: 'trophy',
    3: 'user',
    4: 'tty',
    5: 'tags',
    6: 'calculator',
    7: 'user-secret',
    8: 'dashboard',
    9: 'credit-card',
};

function menuNumber(title = '') {
    const match = String(title).match(/^(\d+)\./);
    return match ? Number(match[1]) : null;
}

function LeafLink({ item, className, onNavigate, children }) {
    if (item.url && !item.disabled) {
        return (
            <Link
                href={item.url}
                className={className}
                title={item.title}
                preserveScroll={false}
                onClick={onNavigate}
            >
                {children}
            </Link>
        );
    }

    return (
        <span className={cn(className, 'is-disabled')} title={`${item.title} — chưa có màn hình tương ứng`}>
            {children}
        </span>
    );
}

function ThirdLevelFlyout({ flyout, activeKey, onNavigate, onClose }) {
    if (!flyout || typeof document === 'undefined') return null;

    return createPortal(
        <div
            className="pushsale-third-menu"
            style={{ top: flyout.top }}
            role="menu"
            aria-label={flyout.item.title}
        >
            {(flyout.item.children ?? []).map((child, index) => {
                const childKey = `${flyout.key}.${index}`;
                const active = keyContains(activeKey, childKey);
                return (
                    <div key={childKey} className={cn('pushsale-third-item', active && 'active')}>
                        <LeafLink
                            item={child}
                            className="pushsale-third-link"
                            onNavigate={() => {
                                onClose();
                                onNavigate?.();
                            }}
                        >
                            <span>{child.title}</span>
                            {(child.children?.length ?? 0) > 0 && <i className="fa fa-angle-right" aria-hidden="true" />}
                        </LeafLink>
                    </div>
                );
            })}
        </div>,
        document.body,
    );
}

export function AppSidebar({ collapsed = true, onNavigate }) {
    const { props, url } = usePage();
    const { navigation = [], activeMenuCode = null } = props;
    const sidebarRef = useRef(null);

    const activeKey = useMemo(
        () => resolveActiveKey(navigation, url, activeMenuCode),
        [activeMenuCode, navigation, url],
    );
    const activeRootIndex = useMemo(() => {
        if (!activeKey) return null;
        const match = activeKey.match(/^root\.(\d+)/);
        return match ? Number(match[1]) : null;
    }, [activeKey]);

    const [openRoot, setOpenRoot] = useState(null);
    const [flyout, setFlyout] = useState(null);

    useEffect(() => {
        if (collapsed) {
            setOpenRoot(null);
            setFlyout(null);
            return;
        }
        setOpenRoot(activeRootIndex);
    }, [activeRootIndex, collapsed]);

    useEffect(() => {
        setFlyout(null);
    }, [url]);

    useEffect(() => {
        const close = (event) => {
            const target = event.target;
            if (target.closest?.('.pushsale-third-menu') || target.closest?.('[data-pushsale-second-parent="true"]')) return;
            setFlyout(null);
        };
        const closeOnViewportChange = () => setFlyout(null);
        document.addEventListener('mousedown', close);
        window.addEventListener('resize', closeOnViewportChange);
        sidebarRef.current?.addEventListener('scroll', closeOnViewportChange, { passive: true });
        return () => {
            document.removeEventListener('mousedown', close);
            window.removeEventListener('resize', closeOnViewportChange);
            sidebarRef.current?.removeEventListener('scroll', closeOnViewportChange);
        };
    }, []);

    const toggleRoot = (index) => {
        setFlyout(null);
        setOpenRoot((current) => (current === index ? null : index));
    };

    const toggleFlyout = (event, item, key) => {
        const rect = event.currentTarget.getBoundingClientRect();
        const estimatedHeight = Math.max(61, (item.children?.length ?? 1) * 61);
        const top = Math.max(50, Math.min(rect.top, window.innerHeight - estimatedHeight - 8));
        setFlyout((current) => (current?.key === key ? null : { item, key, top }));
    };

    return (
        <>
            <aside className="main-sidebar pushsale-main-sidebar" aria-label="Điều hướng chính">
                <section className="sidebar pushsale-sidebar" ref={sidebarRef}>
                    <ul className="sidebar-menu ul1">
                        {navigation.map((root, rootIndex) => {
                            const rootKey = `root.${rootIndex}`;
                            const rootOpen = openRoot === rootIndex;
                            const rootActive = keyContains(activeKey, rootKey);
                            const icon = root.icon ?? defaultIcons[menuNumber(root.title)] ?? 'circle-o';
                            const hasChildren = (root.children?.length ?? 0) > 0;

                            return (
                                <li
                                    key={rootKey}
                                    className={cn('treeview li1', rootOpen && 'menu-open', rootActive && 'active')}
                                >
                                    {hasChildren ? (
                                        <button
                                            type="button"
                                            className="a1 pushsale-menu-link"
                                            onClick={() => toggleRoot(rootIndex)}
                                            aria-expanded={rootOpen}
                                            title={root.title}
                                        >
                                            <i className={`fa fa-${icon}`} aria-hidden="true" />
                                            <span>{root.title}</span>
                                            <i className={cn('i1 fa pull-right', rootOpen ? 'fa-minus' : 'fa-plus')} aria-hidden="true" />
                                        </button>
                                    ) : (
                                        <LeafLink item={root} className="a1 pushsale-menu-link" onNavigate={onNavigate}>
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
                                                        className={cn('li2', childActive && 'active', flyoutOpen && 'flyout-open')}
                                                    >
                                                        {hasGrandchildren ? (
                                                            <button
                                                                type="button"
                                                                className="a2 pushsale-menu-link"
                                                                data-pushsale-second-parent="true"
                                                                onClick={(event) => toggleFlyout(event, child, key)}
                                                                aria-expanded={flyoutOpen}
                                                                title={child.title}
                                                            >
                                                                <span>{child.title}</span>
                                                                <i className="fa fa-angle-right pull-right" aria-hidden="true" />
                                                            </button>
                                                        ) : (
                                                            <LeafLink
                                                                item={child}
                                                                className="a2 pushsale-menu-link"
                                                                onNavigate={() => {
                                                                    setFlyout(null);
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
                    onClose={() => setFlyout(null)}
                />
            )}
        </>
    );
}
