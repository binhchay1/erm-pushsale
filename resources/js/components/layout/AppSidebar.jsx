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

const legacyRootIcons = {
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

function normalizeFaIcon(icon, title = '') {
    const fallback = legacyRootIcons[menuNumber(title)] ?? 'circle-o';
    const tokens = String(icon || fallback)
        .trim()
        .split(/\s+/)
        .map((token) => token.trim())
        .filter(Boolean);

    const candidate = tokens
        .map((token) => token.replace(/^fa-/, ''))
        .find((token) => token && token !== 'fa');

    return String(candidate || fallback).replace(/[^a-z0-9-]/gi, '') || fallback;
}

function LegacyIcon({ icon, title, className }) {
    const normalized = normalizeFaIcon(icon, title);

    return <i className={cn('fa', `fa-${normalized}`, className)} aria-hidden="true" />;
}

function RootExpandIcon() {
    return (
        <span className="ps-menu-caret" aria-hidden="true">
            <i className="i1 fa fa-plus pull-right ps-menu-caret-plus" />
            <i className="i1 fa fa-minus pull-right ps-menu-caret-minus" />
        </span>
    );
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

function ThirdLevelFlyout({ flyout, activeKey, onNavigate, onSelect, onClose, onMouseEnter, onMouseLeave }) {
    if (!flyout || typeof document === 'undefined') return null;

    return createPortal(
        <ul
            className="ul3 pushsale-third-menu"
            style={{ top: flyout.top }}
            role="menu"
            aria-label={flyout.item.title}
            onMouseEnter={onMouseEnter}
            onMouseLeave={onMouseLeave}
        >
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
                            {(child.children?.length ?? 0) > 0 && <i className="fa fa-angle-right pull-right ps-menu-angle" aria-hidden="true" />}
                        </LeafLink>
                    </li>
                );
            })}
        </ul>,
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

    const scheduleFlyoutClose = (delay = 220) => {
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
        window.addEventListener('scroll', closeOnViewportChange, true);
        return () => {
            document.removeEventListener('mousedown', close);
            document.removeEventListener('touchstart', close);
            window.removeEventListener('resize', closeOnViewportChange);
            window.removeEventListener('scroll', closeOnViewportChange, true);
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

    const positionFlyout = (event, item, key) => {
        clearFlyoutTimer();
        const rect = event.currentTarget.getBoundingClientRect();
        const estimatedHeight = Math.max(44, (item.children?.length ?? 1) * 31 + 8);
        const top = Math.max(52, Math.min(rect.top, window.innerHeight - estimatedHeight - 8));
        setFlyout((current) => (current?.key === key ? current : { item, key, top }));
    };

    const toggleFlyout = (event, item, key) => {
        clearFlyoutTimer();
        const rect = event.currentTarget.getBoundingClientRect();
        const estimatedHeight = Math.max(44, (item.children?.length ?? 1) * 31 + 8);
        const top = Math.max(52, Math.min(rect.top, window.innerHeight - estimatedHeight - 8));
        setFlyout((current) => (current?.key === key ? null : { item, key, top }));
    };

    return (
        <>
            <aside className="main-sidebar left-side pushsale-main-sidebar hidden-print" aria-label="Điều hướng chính">
                <section
                    className="sidebar pushsale-sidebar"
                    ref={sidebarRef}
                    onMouseEnter={clearFlyoutTimer}
                    onMouseLeave={() => scheduleFlyoutClose(220)}
                >
                    <span id="dnn_MenuLeft_lblMenu" className="pushsale-menu-root">
                        <ul className="sidebar-menu ul1">
                            {navigation.map((root, rootIndex) => {
                                const rootKey = `root.${rootIndex}`;
                                const rootOpen = openRoot === rootIndex;
                                const rootActive = keyContains(activeKey, rootKey);
                                const icon = normalizeFaIcon(root.icon, root.title);
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
                                                <LegacyIcon icon={icon} title={root.title} className="ps-menu-root-icon" />
                                                <span className="ps-menu-title">{root.title}</span>
                                                <RootExpandIcon />
                                            </button>
                                        ) : (
                                            <LeafLink item={root} className="a1 pushsale-menu-link" onNavigate={() => { rememberSelection(root); onNavigate?.(); }}>
                                                <LegacyIcon icon={icon} title={root.title} className="ps-menu-root-icon" />
                                                <span className="ps-menu-title">{root.title}</span>
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
                                                            onMouseLeave={() => {
                                                                if (hasGrandchildren) scheduleFlyoutClose(220);
                                                            }}
                                                        >
                                                            {hasGrandchildren ? (
                                                                <button
                                                                    type="button"
                                                                    className="a2 pushsale-menu-link"
                                                                    data-pushsale-second-parent="true"
                                                                    onClick={(event) => toggleFlyout(event, child, key)}
                                                                    onMouseEnter={(event) => positionFlyout(event, child, key)}
                                                                    aria-expanded={flyoutOpen}
                                                                    title={child.title}
                                                                >
                                                                    <span className="ps-menu-title">{child.title}</span>
                                                                    <i className="fa fa-angle-right pull-right ps-menu-angle" aria-hidden="true" />
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
                                                                    <span className="ps-menu-title">{child.title}</span>
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
                    </span>
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
                    onMouseLeave={() => scheduleFlyoutClose(220)}
                />
            )}
        </>
    );
}
