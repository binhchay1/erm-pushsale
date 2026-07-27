import { createContext, useCallback, useContext, useId, useLayoutEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';

import { cn } from '@/lib/utils';

/**
 * Header dùng chung cho mọi trang menu.
 *
 * DOM:
 *   .m-header-wrap.ps-page-header > .m-header  (title | filter chính | nút + toggle)
 *   .ps-page-extra-filters                     (filter bổ sung — CÙNG CẤP header, giữa header và body)
 *
 * Filter bổ sung KHÔNG nằm trong .m-header-wrap. Nút toggle trên header
 * mở/đóng khối `.ps-page-extra-filters`.
 *
 * Header được đẩy lên outlet trong AppLayout nên mỗi trang chỉ tồn tại đúng
 * một header, không thể double khi component lồng nhau.
 */
const PageHeaderSlotContext = createContext(null);

export function PageHeaderProvider({ children }) {
    const [node, setNode] = useState(null);
    const [owner, setOwner] = useState(null);

    const claim = useCallback((id) => setOwner((current) => current ?? id), []);
    const release = useCallback((id) => setOwner((current) => (current === id ? null : current)), []);

    const value = useMemo(() => ({ node, setNode, owner, claim, release }), [node, owner, claim, release]);

    return <PageHeaderSlotContext.Provider value={value}>{children}</PageHeaderSlotContext.Provider>;
}

export function PageHeaderOutlet({ className }) {
    const slot = useContext(PageHeaderSlotContext);

    return <div ref={slot?.setNode} className={cn('ps-page-header-outlet', className)} />;
}

export function PageHeader({
    title,
    subtitle,
    description,
    icon: Icon,
    filters,
    primaryFilters,
    actions,
    advanced,
    advancedFilters,
    pageCode,
    className,
    children,
    defaultCollapsed = false,
    collapsible = true,
    sticky = true,
}) {
    const slot = useContext(PageHeaderSlotContext);
    const id = useId();

    useLayoutEffect(() => {
        if (!slot) return undefined;

        slot.claim(id);

        return () => slot.release(id);
    }, [slot, id]);

    // Header trước bị unmount thì header còn lại nhận quyền hiển thị.
    useLayoutEffect(() => {
        if (slot && slot.owner === null) slot.claim(id);
    }, [slot, id]);

    const markup = (
        <PageHeaderBar
            title={title}
            subtitle={subtitle ?? description}
            icon={Icon}
            filters={primaryFilters ?? filters ?? children}
            actions={actions}
            advanced={advancedFilters ?? advanced}
            pageCode={pageCode}
            className={className}
            defaultCollapsed={defaultCollapsed}
            collapsible={collapsible}
            sticky={sticky}
        />
    );

    if (!slot) return markup;
    if (!slot.node || slot.owner !== id) return null;

    return createPortal(markup, slot.node);
}

function PageHeaderBar({ title, subtitle, icon: Icon, filters, actions, advanced, pageCode, className, defaultCollapsed, collapsible, sticky }) {
    const [collapsed, setCollapsed] = useState(defaultCollapsed);

    const hasAdvanced = Boolean(advanced);
    const showToggle = collapsible && hasAdvanced;
    const hasActions = Boolean(actions) || showToggle;
    const showExtra = hasAdvanced && !collapsed;

    return (
        <>
            <div
                className={cn(
                    'm-header-wrap ps-page-header',
                    sticky && 'is-sticky',
                    hasAdvanced && 'has-advanced',
                    collapsed && 'is-collapsed',
                    Boolean(filters) && 'has-filters',
                    className,
                )}
                data-page-code={pageCode || undefined}
            >
                <div className="m-header ps-page-header__row">
                    <div className="ps-page-header__title form-group">
                        {Icon ? (
                            <span className="ps-page-header__icon">
                                {typeof Icon === 'string' ? Icon : <Icon aria-hidden="true" />}
                            </span>
                        ) : null}
                        <span className="text">{title}</span>
                        {subtitle ? <span className="ps-page-header__subtitle">{subtitle}</span> : null}
                    </div>

                    {filters ? <div className="ps-page-header__filters form-group">{filters}</div> : null}

                    {hasActions ? (
                        <div className="ps-page-header__actions form-group">
                            {actions}
                            {showToggle ? (
                                <a
                                    role="button"
                                    tabIndex={0}
                                    className="btn-icon ps-page-header__toggle"
                                    aria-expanded={!collapsed}
                                    title={collapsed ? 'Mở bộ lọc nâng cao' : 'Thu gọn bộ lọc nâng cao'}
                                    onClick={() => setCollapsed((value) => !value)}
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter' || event.key === ' ') {
                                            event.preventDefault();
                                            setCollapsed((value) => !value);
                                        }
                                    }}
                                >
                                    <i className={cn('fa', collapsed ? 'fa-angle-double-down' : 'fa-angle-double-up')} aria-hidden="true" />
                                </a>
                            ) : null}
                        </div>
                    ) : null}
                </div>
            </div>

            {hasAdvanced ? (
                <div
                    className={cn('ps-page-extra-filters', className)}
                    data-page-code={pageCode || undefined}
                    hidden={showExtra ? undefined : true}
                >
                    {advanced}
                </div>
            ) : null}
        </>
    );
}

export default PageHeader;
