import { router } from '@inertiajs/react';

function clean(values) {
    return Object.fromEntries(Object.entries(values).filter(([, value]) => value !== '' && value !== null && value !== undefined && value !== false));
}

export function SaleWorkspaceTabs({ tabs = [], routeUrl, filters }) {
    const selected = filters.operation_stage || 'all';
    const select = (status) => {
        router.get(routeUrl, clean({ ...filters, operation_stage: status === 'all' ? '' : status, page: 1 }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <div className="ps-sale-stage-tabs row ttgh-acc">
            {tabs.map((tab) => {
                const level = tab.level === 0 || tab.level === '0' ? '' : (tab.level ?? '');
                const flagClass = level === '' || level === null || level === undefined
                    ? 'flag level-'
                    : `flag level-${level}`;

                return (
                    <button
                        type="button"
                        key={tab.status}
                        className={`dm-tac-nghiep dm-tac-nghiep${tab.status} ${selected === tab.status || (selected === 'all' && tab.status === 'all') ? 'selected' : ''}`}
                        onClick={() => select(tab.status)}
                    >
                        <span className={flagClass} />
                        <span className="text">{tab.label}</span>
                        <span className="count">{tab.count ? `(${Number(tab.count).toLocaleString('vi-VN')}/${Number(tab.total || tab.count).toLocaleString('vi-VN')})` : ''}</span>
                        <span className="live-stream" />
                        <i className="fa fa-angle-double-right" />
                    </button>
                );
            })}
        </div>
    );
}
