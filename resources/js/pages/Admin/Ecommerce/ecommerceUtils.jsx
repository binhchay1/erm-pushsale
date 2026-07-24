import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

export function optionNodes(options = [], placeholder = null) {
    return (
        <>
            {placeholder ? <option value="">{placeholder}</option> : null}
            {(options ?? []).map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
        </>
    );
}

export function useDraft(filters) {
    const [draft, setDraft] = useState(filters ?? {});
    useEffect(() => setDraft(filters ?? {}), [filters]);
    const set = (key, value) => setDraft((current) => ({ ...current, [key]: value }));

    return { draft, set, setDraft };
}

export function useLocalToast() {
    const { flash = {} } = usePage().props;
    const [toast, setToast] = useState(null);

    useEffect(() => {
        if (flash.error) setToast({ type: 'warning', message: flash.error });
        else if (flash.success) setToast({ type: 'success', message: flash.success });
    }, [flash.error, flash.success]);

    return { toast, setToast };
}

export function PushsaleToast({ toast, onClose }) {
    if (!toast) return null;

    return (
        <div className={`ps-ecommerce-toast is-${toast.type ?? 'warning'}`}>
            <i className="fa fa-warning" />
            <span>{toast.message}</span>
            <button type="button" onClick={onClose} aria-label="Đóng">×</button>
        </div>
    );
}

export function SimplePagination({ rows }) {
    const meta = rows ?? {};
    if (!meta.links?.length) return null;
    const current = meta.current_page ?? 1;
    const last = meta.last_page ?? 1;

    return (
        <div className="ps-ecommerce-pager-row">
            <div className="btn-group">
                <button type="button" className="btn btn-default btn-sm">{meta.from ?? 0} - {meta.to ?? 0} / {meta.total ?? 0}</button>
                <button type="button" className="btn btn-default btn-sm" disabled={current <= 1} onClick={() => meta.prev_page_url && router.visit(meta.prev_page_url, { preserveScroll: true })}>‹</button>
                <button type="button" className="btn btn-default btn-sm" disabled={current >= last} onClick={() => meta.next_page_url && router.visit(meta.next_page_url, { preserveScroll: true })}>›</button>
            </div>
            <ul className="pagination pagination-sm no-margin">
                {(meta.links ?? []).filter((link) => !String(link.label).includes('Previous') && !String(link.label).includes('Next')).slice(0, 7).map((link, index) => (
                    <li key={`${link.label}-${index}`} className={link.active ? 'active' : ''}>
                        <button type="button" disabled={!link.url} onClick={() => link.url && router.visit(link.url, { preserveScroll: true })} dangerouslySetInnerHTML={{ __html: link.label }} />
                    </li>
                ))}
            </ul>
        </div>
    );
}

export function useRows(paginator) {
    return useMemo(() => paginator?.data ?? [], [paginator]);
}
