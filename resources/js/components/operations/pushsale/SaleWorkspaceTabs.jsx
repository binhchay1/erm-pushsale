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
        <div className="ps-sale-stage-tabs">
            {tabs.map((tab) => (
                <button
                    type="button"
                    key={tab.status}
                    className={`dm-tac-nghiep ${selected === tab.status || (selected === 'all' && tab.status === 'all') ? 'selected' : ''}`}
                    onClick={() => select(tab.status)}
                >
                    <span className="flag" style={{ backgroundColor: tab.color }} />
                    <span className="text">{tab.label}</span>
                    <span className="count">{tab.count ? `(${tab.count}/${tab.total})` : ''}</span>
                </button>
            ))}
        </div>
    );
}
