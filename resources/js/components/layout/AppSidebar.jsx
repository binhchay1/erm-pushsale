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

function resolveActiveKey(navigation, currentUrl, activeMenuCode, rememberedMenuCode) {
    const leaves = flattenLeaves(navigation);
    if (activeMenuCode) {
        const byCode = leaves.find(({ item }) => String(item.code ?? '') === String(activeMenuCode));
        if (byCode) return byCode.key;
    }

    const currentPath = cleanPath(currentUrl);
    if (rememberedMenuCode) {
        const remembered = leaves.find(({ item }) =>
            String(item.code ?? '') === String(rememberedMenuCode) && cleanPath(item.url) === currentPath,
        );
        if (remembered) return remembered.key;
    }

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

function LeafLink({ item, className, onNavigate, children, style }) {
    if (item.url && !item.disabled) {
        return (
            <Link
                href={item.url}
                className={className}
                style={style}
                aria-label={item.title}
                preserveScroll={false}
                onClick={onNavigate}
            >
                {children}
            </Link>
        );
    }

    return (
        <span className={cn(className, 'is-disabled')} style={style} aria-label={`${item.title} — chưa có màn hình tương ứng`}>
            {children}
        </span>
    );
}

function ThirdLevelFlyout({ flyout, activeKey, onNavigate, onSelect, onClose, onMouseEnter, onMouseLeave }) {
    if (!flyout || typeof document === 'undefined') return null;

    return createPortal(
        <div
            className="pushsale-third-menu"
            style={{ top: flyout.top, maxHeight: flyout.maxHeight, backgroundColor: '#0b8ff3', backgroundImage: 'none', color: '#fff' }}
            role="menu"
            aria-label={flyout.item.title}
            onMouseEnter={onMouseEnter}
            onMouseLeave={onMouseLeave}
        >
            {(flyout.item.children ?? []).map((child, index) => {
                const childKey = `${flyout.key}.${index}`;
                const active = keyContains(activeKey, childKey);
                return (
                    <div key={childKey} className={cn('pushsale-third-item', active && 'active')}>
                        <LeafLink
                            item={child}
                            className="pushsale-third-link"
                            style={{ backgroundColor: active ? '#0560ad' : 'transparent', color: '#fff' }}
                            onNavigate={() => {
                                onSelect?.(child);
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
    const flyoutTimerRef = useRef(null);
    const [rememberedMenuCode, setRememberedMenuCode] = useState(() =>
        typeof window === 'undefined' ? null : window.sessionStorage.getItem('pushsale-active-menu-code'),
    );

    const activeKey = useMemo(
        () => resolveActiveKey(navigation, url, activeMenuCode, rememberedMenuCode),
        [activeMenuCode, navigation, rememberedMenuCode, url],
    );
    const activeRootIndex = useMemo(() => {
        if (!activeKey) return null;
        const match = activeKey.match(/^root\.(\d+)/);
        return match ? Number(match[1]) : null;
    }, [activeKey]);

    const [openRoot, setOpenRoot] = useState(null);
    const [flyout, setFlyout] = useState(null);

    const clearFlyoutTimer = () => {
        if (flyoutTimerRef.current) {
            window.clearTimeout(flyoutTimerRef.current);
            flyoutTimerRef.current = null;
        }
    };

    const closeFlyout = () => {
        clearFlyoutTimer();
        setFlyout(null);
    };

    const scheduleFlyoutClose = (delay = 180) => {
        clearFlyoutTimer();
        flyoutTimerRef.current = window.setTimeout(() => {
            setFlyout(null);
            flyoutTimerRef.current = null;
        }, delay);
    };

    useEffect(() => {
        if (collapsed) {
            setOpenRoot(null);
            closeFlyout();
            return;
        }
        setOpenRoot(activeRootIndex);
    }, [activeRootIndex, collapsed]);

    useEffect(() => {
        closeFlyout();
    }, [url]);

    useEffect(() => {
        const close = (event) => {
            const target = event.target;
            if (target.closest?.('.pushsale-third-menu') || target.closest?.('[data-pushsale-second-parent="true"]')) return;
            closeFlyout();
        };
        const closeOnViewportChange = () => closeFlyout();
        document.addEventListener('mousedown', close);
        document.addEventListener('touchstart', close, { passive: true });
        window.addEventListener('resize', closeOnViewportChange);
        return () => {
            document.removeEventListener('mousedown', close);
            document.removeEventListener('touchstart', close);
            window.removeEventListener('resize', closeOnViewportChange);
            clearFlyoutTimer();
        };
    }, []);

    const rememberSelection = (item) => {
        const code = item?.code ? String(item.code) : null;
        if (!code) return;
        setRememberedMenuCode(code);
        window.sessionStorage.setItem('pushsale-active-menu-code', code);
    };

    const toggleRoot = (index) => {
        closeFlyout();
        setOpenRoot((current) => (current === index ? null : index));
    };

    const toggleFlyout = (event, item, key) => {
        clearFlyoutTimer();
        const rect = event.currentTarget.getBoundingClientRect();
        const estimatedHeight = Math.max(40, (item.children?.length ?? 1) * 40);
        const maxHeight = Math.max(120, window.innerHeight - 58);
        const top = Math.max(50, Math.min(rect.top, window.innerHeight - Math.min(estimatedHeight, maxHeight) - 8));
        setFlyout((current) => (current?.key === key ? null : { item, key, top, maxHeight }));
    };

    return (
        <>
            <aside className="main-sidebar pushsale-main-sidebar" aria-label="Điều hướng chính">
                <section
                    className="sidebar pushsale-sidebar"
                    ref={sidebarRef}
                    onMouseEnter={clearFlyoutTimer}
                    onMouseLeave={() => scheduleFlyoutClose(140)}
                >
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
                                            aria-label={root.title}
                                        >
                                            <i className={`fa fa-${icon}`} aria-hidden="true" />
                                            <span>{root.title}</span>
                                            <i className={cn('i1 fa pull-right', rootOpen ? 'fa-minus' : 'fa-plus')} aria-hidden="true" />
                                        </button>
                                    ) : (
                                        <LeafLink item={root} className="a1 pushsale-menu-link" onNavigate={() => { rememberSelection(root); onNavigate?.(); }}>
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
                                                        onMouseEnter={() => {
                                                            if (!hasGrandchildren) closeFlyout();
                                                        }}
                                                    >
                                                        {hasGrandchildren ? (
                                                            <button
                                                                type="button"
                                                                className="a2 pushsale-menu-link"
                                                                data-pushsale-second-parent="true"
                                                                onClick={(event) => toggleFlyout(event, child, key)}
                                                                onMouseEnter={(event) => {
                                                                    clearFlyoutTimer();
                                                                    const rect = event.currentTarget.getBoundingClientRect();
                                                                    const estimatedHeight = Math.max(40, (child.children?.length ?? 1) * 40);
                                                                    const maxHeight = Math.max(120, window.innerHeight - 58);
                                                                    const top = Math.max(50, Math.min(rect.top, window.innerHeight - Math.min(estimatedHeight, maxHeight) - 8));
                                                                    setFlyout({ item: child, key, top, maxHeight });
                                                                }}
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
                    onMouseLeave={() => scheduleFlyoutClose(140)}
                />
            )}
        </>
    );
}
