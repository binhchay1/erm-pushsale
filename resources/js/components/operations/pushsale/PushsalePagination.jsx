import { router } from '@inertiajs/react';

function clean(values) {
    return Object.fromEntries(Object.entries(values).filter(([, value]) => value !== '' && value !== null && value !== undefined && value !== false));
}

export function PushsalePagination({ meta, routeUrl, filters }) {
    if (!meta) return null;

    const current = Number(meta.current_page || 1);
    const last = Math.max(1, Number(meta.last_page || 1));
    const pages = [];
    const start = Math.max(1, current - 2);
    const end = Math.min(last, current + 2);
    for (let page = start; page <= end; page += 1) pages.push(page);

    const currentPerPage = Number(filters.per_page ?? meta.per_page ?? 20);
    const visit = (page, perPage = currentPerPage) => {
        const nextPerPage = Number(perPage);
        if (page < 1 || page > last) return;
        if (page === current && nextPerPage === currentPerPage) return;
        router.get(routeUrl, clean({ ...filters, page, per_page: nextPerPage }), {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    return (
        <div className="ps-sale-pagination-wrap">
            <div className="ps-sale-pagination-info">
                Hiển thị <b>{meta.from ?? 0}</b> - <b>{meta.to ?? 0}</b> / <b>{meta.total ?? 0}</b> bản ghi
                <label className="ps-page-size">
                    <span>Số dòng</span>
                    <select className="form-control input-sm" value={currentPerPage} onChange={(event) => visit(1, Number(event.target.value))}>
                        {[10, 20, 50, 100].map((size) => <option key={size} value={size}>{size}</option>)}
                    </select>
                </label>
            </div>
            <ul className="pagination pagination-sm ps-sale-pagination">
                <li className={current <= 1 ? 'disabled' : ''}>
                    <button type="button" onClick={() => visit(1)} aria-label="Trang đầu">«</button>
                </li>
                <li className={current <= 1 ? 'disabled' : ''}>
                    <button type="button" onClick={() => visit(current - 1)} aria-label="Trang trước">‹</button>
                </li>
                {start > 1 && <li className="disabled"><span>…</span></li>}
                {pages.map((page) => (
                    <li key={page} className={page === current ? 'active' : ''}>
                        <button type="button" onClick={() => visit(page)}>{page}</button>
                    </li>
                ))}
                {end < last && <li className="disabled"><span>…</span></li>}
                <li className={current >= last ? 'disabled' : ''}>
                    <button type="button" onClick={() => visit(current + 1)} aria-label="Trang sau">›</button>
                </li>
                <li className={current >= last ? 'disabled' : ''}>
                    <button type="button" onClick={() => visit(last)} aria-label="Trang cuối">»</button>
                </li>
            </ul>
        </div>
    );
}
