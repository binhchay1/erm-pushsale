import { Link, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

import { useRoleLabel } from '@/hooks/use-labels';
import { cn } from '@/lib/utils';
import { useT } from '@/providers/I18nProvider';

function cleanPath(url = '') {
    return String(url).split('?')[0].split('#')[0] || '/';
}

function isNavActive(itemUrl, currentUrl) {
    if (!itemUrl || itemUrl === '#') return false;

    const itemPath = cleanPath(itemUrl);
    const currentPath = cleanPath(currentUrl);

    if (itemPath === '/') return currentPath === '/';

    return currentPath === itemPath || currentPath.startsWith(`${itemPath}/`);
}

function nodeIsActive(item, currentUrl) {
    if (isNavActive(item.url, currentUrl)) return true;

    return (item.children ?? []).some((child) => nodeIsActive(child, currentUrl));
}

function branchKeys(items, currentUrl, parentKey = 'root') {
    const keys = [];

    items.forEach((item, index) => {
        const key = `${parentKey}.${index}`;
        if ((item.children?.length ?? 0) > 0 && nodeIsActive(item, currentUrl)) {
            keys.push(key, ...branchKeys(item.children, currentUrl, key));
        }
    });

    return keys;
}

const defaultIcons = {
    1: 'cog',
    2: 'trophy',
    3: 'user',
    4: 'tty',
    5: 'cubes',
    6: 'calculator',
    7: 'user-secret',
    8: 'dashboard',
    9: 'credit-card',
};

function menuNumber(title = '') {
    const match = String(title).match(/^(\d+)\./);
    return match ? Number(match[1]) : null;
}

function MenuNode({ item, level, nodeKey, currentUrl, openNodes, toggleNode, onNavigate }) {
    const hasChildren = (item.children?.length ?? 0) > 0;
    const isOpen = openNodes.has(nodeKey);
    const isActive = nodeIsActive(item, currentUrl);
    const number = menuNumber(item.title);
    const icon = item.icon ?? (level === 1 ? defaultIcons[number] : null);
    const levelClass = level === 1 ? 'li1' : level === 2 ? 'li2' : 'li3';
    const anchorClass = level === 1 ? 'a1' : level === 2 ? 'a2' : 'a3';

    if (hasChildren) {
        return (
            <li className={cn('treeview', levelClass, isOpen && 'menu-open', isActive && 'active')}>
                <button
                    type="button"
                    className={cn('pushsale-menu-link', anchorClass)}
                    onClick={() => toggleNode(nodeKey)}
                    aria-expanded={isOpen}
                    title={item.title}
                >
                    {level === 1 && <i className={`fa fa-${icon || 'circle-o'}`} aria-hidden="true" />}
                    {level > 1 && <i className="fa fa-angle-right pushsale-submenu-arrow" aria-hidden="true" />}
                    <span>{item.title}</span>
                    <i
                        className={cn(
                            'fa pull-right pushsale-expand-icon',
                            isOpen ? 'fa-minus' : 'fa-plus',
                        )}
                        aria-hidden="true"
                    />
                </button>
                <ul
                    className={cn(
                        'treeview-menu',
                        level === 1 ? 'ul2' : 'ul3',
                        isOpen && 'is-open',
                    )}
                >
                    {item.children.map((child, index) => (
                        <MenuNode
                            key={`${nodeKey}.${index}`}
                            item={child}
                            level={level + 1}
                            nodeKey={`${nodeKey}.${index}`}
                            currentUrl={currentUrl}
                            openNodes={openNodes}
                            toggleNode={toggleNode}
                            onNavigate={onNavigate}
                        />
                    ))}
                </ul>
            </li>
        );
    }

    const content = (
        <>
            {level === 1 && <i className={`fa fa-${icon || 'circle-o'}`} aria-hidden="true" />}
            {level === 2 && <i className="fa fa-angle-right pushsale-submenu-arrow" aria-hidden="true" />}
            {level >= 3 && <i className="fa fa-circle-o pushsale-leaf-dot" aria-hidden="true" />}
            <span>{item.title}</span>
        </>
    );

    return (
        <li className={cn(levelClass, isActive && 'active', item.disabled && 'is-disabled')}>
            {item.url && !item.disabled ? (
                <Link
                    href={item.url}
                    className={cn('pushsale-menu-link', anchorClass)}
                    onClick={onNavigate}
                    title={item.title}
                >
                    {content}
                </Link>
            ) : (
                <span
                    className={cn('pushsale-menu-link', anchorClass, 'pushsale-menu-disabled')}
                    title={`${item.title} — chưa có màn hình tương ứng trong dự án`}
                >
                    {content}
                </span>
            )}
        </li>
    );
}

export function AppSidebar({ collapsed = false, onNavigate }) {
    const t = useT();
    const { props, url } = usePage();
    const { auth, navigation = [] } = props;
    const roleLabel = useRoleLabel(auth.user?.role) || t('dashboard.sidebar.user_fallback');
    const contentRef = useRef(null);

    const activeBranchKeys = useMemo(() => branchKeys(navigation, url), [navigation, url]);
    const [openNodes, setOpenNodes] = useState(() => new Set(activeBranchKeys));

    useEffect(() => {
        setOpenNodes((current) => new Set([...current, ...activeBranchKeys]));
    }, [activeBranchKeys]);

    useEffect(() => {
        const frame = requestAnimationFrame(() => {
            contentRef.current?.querySelector('li.active > a')?.scrollIntoView({ block: 'nearest' });
        });

        return () => cancelAnimationFrame(frame);
    }, [url]);

    const toggleNode = (key) => {
        setOpenNodes((current) => {
            const next = new Set(current);
            if (next.has(key)) next.delete(key);
            else next.add(key);
            return next;
        });
    };

    return (
        <aside className="main-sidebar" aria-label="Điều hướng chính">
            <section className="sidebar" ref={contentRef}>
                <div className="pushsale-sidebar-caption">
                    <i className="fa fa-bars" aria-hidden="true" />
                    <span>MENU CHỨC NĂNG</span>
                </div>

                <ul className="sidebar-menu ul1">
                    {navigation.map((item, index) => (
                        <MenuNode
                            key={`root.${index}`}
                            item={item}
                            level={1}
                            nodeKey={`root.${index}`}
                            currentUrl={url}
                            openNodes={openNodes}
                            toggleNode={toggleNode}
                            onNavigate={onNavigate}
                        />
                    ))}
                </ul>

                {!collapsed && (
                    <div className="pushsale-sidebar-footer">
                        <i className="fa fa-circle text-success" aria-hidden="true" />
                        <span>{roleLabel}</span>
                    </div>
                )}
            </section>
        </aside>
    );
}
