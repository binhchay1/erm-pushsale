import { useState } from 'react';

export function PushsalePageHeader({
    title,
    children,
    advanced,
    className = '',
    onSubmit,
    toggleTitle = 'Bộ lọc nâng cao',
}) {
    const [advancedOpen, setAdvancedOpen] = useState(false);
    const Tag = onSubmit ? 'form' : 'div';

    return (
        <Tag className={`ps-page-header ps-page-header-v119 ${className}`.trim()} onSubmit={onSubmit}>
            <div className="ps-page-header-main">
                <div className="ps-title ps-page-title">{title}</div>
                <div className="ps-page-primary-filters">
                    {children}
                    {advanced ? (
                        <button type="button" className="btn-icon pslc-toggle" title={toggleTitle} onClick={() => setAdvancedOpen((value) => !value)}>
                            <i className={`fa ${advancedOpen ? 'fa-angle-double-up' : 'fa-angle-double-down'}`} />
                        </button>
                    ) : null}
                </div>
            </div>
            {advanced && advancedOpen ? <div className="ps-page-advanced-filters">{advanced}</div> : null}
        </Tag>
    );
}
