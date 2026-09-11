import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

import AppLayout from '@/layouts/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { apiRequest } from '@/lib/api';
import { useConfirm } from '@/hooks/use-confirm';
import { useT } from '@/providers/I18nProvider';

const ACTION_FIELDS = {
    CAP_NHAT_DON: ['warehouse', 'dims', 'shipping', 'notes'],
    CAP_NHAT_TTGH: ['delivery_status', 'note'],
    CAP_NHAT_GHI_CHU_KE_TOAN: ['accounting_note'],
    CAP_NHAT_TTDS: ['reconciliation_status'],
    DANG_DON: [],
    HUY_DANG_DON: [],
    HUY_DANG_DON_WITHOUT_API: [],
    DOI_MA_DON_PUSHSALE: [],
    CAP_NHAT_TT_CARE_DON: ['care'],
};

function optionList(items = []) {
    return (items ?? []).map((item) => ({
        value: String(item.value ?? item.id ?? ''),
        label: item.label ?? item.name ?? String(item.value ?? item.id ?? ''),
    })).filter((item) => item.value !== '');
}

export default function BulkUpdateByCode({
    pageTitle,
    activeMenuCode = '5.1',
    backUrl = '/admin/warehouse/operations',
    executeUrl = '/admin/warehouse/orders/update-by-code',
    initialCodes = '',
    actions = [],
    filterOptions = {},
}) {
    const t = useT();
    const { ask } = useConfirm();
    const title = pageTitle || t('operations.bulk_update_by_code.title');

    const [codeType, setCodeType] = useState('MHT');
    const [isGhtk, setIsGhtk] = useState(false);
    const [codes, setCodes] = useState(initialCodes || '');
    const [action, setAction] = useState(actions[0]?.value || 'CAP_NHAT_DON');
    const [form, setForm] = useState({
        warehouse_id: '',
        shipping_provider: '',
        shipping_method: '',
        shipping_notes: '',
        length_cm: '',
        width_cm: '',
        height_cm: '',
        weight_grams: '',
        delivery_status: '',
        reconciliation_status: '',
        warehouse_care_status: '',
        warehouse_care_note: '',
        accounting_note: '',
        note: '',
    });
    const [busy, setBusy] = useState(false);
    const [results, setResults] = useState([]);

    const visible = ACTION_FIELDS[action] || [];
    const showRight = visible.length > 0;
    const warehouses = useMemo(() => optionList(filterOptions.warehouses), [filterOptions.warehouses]);
    const providers = useMemo(() => optionList(filterOptions.shippingProviders), [filterOptions.shippingProviders]);
    const deliveryStatuses = useMemo(() => optionList(filterOptions.deliveryStatuses), [filterOptions.deliveryStatuses]);
    const reconStatuses = useMemo(() => optionList(filterOptions.reconciliationStatuses), [filterOptions.reconciliationStatuses]);
    const careStatuses = useMemo(() => optionList(filterOptions.warehouseCareStatuses), [filterOptions.warehouseCareStatuses]);
    const serviceOptions = filterOptions.shippingServiceOptions || {};
    const transportOptions = useMemo(
        () => optionList(serviceOptions[form.shipping_provider] || []),
        [serviceOptions, form.shipping_provider],
    );

    const setField = (key, value) => setForm((old) => ({ ...old, [key]: value }));

    const onProviderChange = (value) => {
        setForm((old) => ({ ...old, shipping_provider: value, shipping_method: '' }));
    };

    const submit = async () => {
        if (!String(codes).trim()) {
            toast.error(t('operations.bulk_update_by_code.codes_required'));
            return;
        }

        const ok = await ask({
            title: t('operations.bulk_update_by_code.confirm_title'),
            description: t('operations.bulk_update_by_code.confirm_body'),
            confirmLabel: t('operations.bulk_update_by_code.execute'),
        });
        if (!ok) return;

        setBusy(true);
        try {
            const payload = {
                code_type: codeType,
                is_ghtk: isGhtk,
                codes,
                action,
                warehouse_id: form.warehouse_id || null,
                shipping_provider: form.shipping_provider || null,
                shipping_method: form.shipping_method || null,
                shipping_notes: form.shipping_notes || null,
                length_cm: form.length_cm !== '' ? Number(form.length_cm) : null,
                width_cm: form.width_cm !== '' ? Number(form.width_cm) : null,
                height_cm: form.height_cm !== '' ? Number(form.height_cm) : null,
                weight_grams: form.weight_grams !== '' ? Number(form.weight_grams) : null,
                delivery_status: form.delivery_status || null,
                reconciliation_status: form.reconciliation_status || null,
                warehouse_care_status: form.warehouse_care_status || null,
                warehouse_care_note: form.warehouse_care_note || null,
                accounting_note: form.accounting_note || null,
                note: form.note || null,
            };
            const data = await apiRequest(executeUrl, { method: 'POST', body: payload });
            setResults(data.results || []);
            if (data.failed_count > 0) {
                toast.warning(data.message || t('operations.bulk_update_by_code.done_partial'));
            } else {
                toast.success(data.message || t('operations.bulk_update_by_code.done_ok'));
            }
        } catch (error) {
            toast.error(error.message || t('operations.bulk_update_by_code.done_fail'));
        } finally {
            setBusy(false);
        }
    };

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title={title} />
            <section className="ps-wh-bulk-page pushsale-page" data-page-code={activeMenuCode}>
                <PageHeader
                    title={title}
                    pageCode={activeMenuCode}
                    actions={(
                        <Link href={backUrl} className="btn btn-default btn-sm ps-wh-bulk-close" title={t('operations.bulk_update_by_code.close')}>
                            <i className="fa fa-close" aria-hidden="true" />
                        </Link>
                    )}
                />

                <div className="box-body ps-wh-bulk-body">
                    <div className="row">
                        <div className="col-sm-6">
                            <div className="row">
                                <div className="col-xs-12">
                                    <span className="h-label">{t('operations.bulk_update_by_code.code_type')}</span>
                                </div>
                                <div className="col-xs-6 form-group">
                                    <select className="form-control" value={codeType} onChange={(e) => setCodeType(e.target.value)}>
                                        <option value="MHT">{t('operations.bulk_update_by_code.code_type_mht')}</option>
                                        <option value="MGV">{t('operations.bulk_update_by_code.code_type_mgv')}</option>
                                    </select>
                                </div>
                                <div className="col-xs-6 form-group">
                                    <label className="ps-wh-bulk-check">
                                        <input type="checkbox" checked={isGhtk} onChange={(e) => setIsGhtk(e.target.checked)} />
                                        {' '}
                                        {t('operations.bulk_update_by_code.is_ghtk')}
                                    </label>
                                </div>

                                <div className="col-xs-12">
                                    <span className="h-label">{t('operations.bulk_update_by_code.codes')}</span>
                                </div>
                                <div className="col-xs-12 form-group">
                                    <textarea
                                        className="form-control ps-wh-bulk-codes"
                                        rows={5}
                                        value={codes}
                                        onChange={(e) => setCodes(e.target.value)}
                                        placeholder={t('operations.bulk_update_by_code.codes_placeholder')}
                                    />
                                </div>

                                <div className="col-xs-6 form-group">
                                    <select className="form-control" value={action} onChange={(e) => setAction(e.target.value)}>
                                        {actions.map((item) => (
                                            <option key={item.value} value={item.value}>{item.label}</option>
                                        ))}
                                    </select>
                                </div>
                                <div className="col-xs-6 form-group">
                                    <button type="button" className="btn btn-sm btn-primary mr15" disabled={busy} onClick={submit}>
                                        <i className="fa fa-gears" aria-hidden="true" />
                                        {' '}
                                        {t('operations.bulk_update_by_code.execute')}
                                    </button>
                                </div>

                                <div className="col-xs-12 form-group">
                                    <div className="notice ps-wh-bulk-notice">
                                        <b>{t('operations.bulk_update_by_code.guide_title')}</b>
                                        <br />
                                        -
                                        {' '}
                                        <span className="ps-wh-bulk-danger">
                                            {t('operations.bulk_update_by_code.guide_one_process')}
                                        </span>
                                        <br />
                                        -
                                        {' '}
                                        {t('operations.bulk_update_by_code.guide_codes')}
                                        <br />
                                        -
                                        {' '}
                                        {t('operations.bulk_update_by_code.guide_update_order')}
                                        {' '}
                                        <span className="ps-wh-bulk-danger">
                                            {t('operations.bulk_update_by_code.guide_update_order_warn')}
                                        </span>
                                        <br />
                                        -
                                        {' '}
                                        {t('operations.bulk_update_by_code.guide_ttgh')}
                                        {' '}
                                        <span className="ps-wh-bulk-danger">
                                            {t('operations.bulk_update_by_code.guide_ttgh_warn')}
                                        </span>
                                        <br />
                                        -
                                        {' '}
                                        {t('operations.bulk_update_by_code.guide_cancel')}
                                        <br />
                                        -
                                        {' '}
                                        {t('operations.bulk_update_by_code.guide_cancel_no_api')}
                                        {' '}
                                        <span className="ps-wh-bulk-danger">
                                            {t('operations.bulk_update_by_code.guide_cancel_no_api_warn')}
                                        </span>
                                        {t('operations.bulk_update_by_code.guide_cancel_no_api_tail')}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {showRight ? (
                            <div className="col-sm-6">
                                {visible.includes('warehouse') || visible.includes('notes') ? (
                                    <>
                                        <div className="row">
                                            <div className="col-xs-4">
                                                <span className="h-label">{t('operations.bulk_update_by_code.warehouse')}</span>
                                            </div>
                                            <div className="col-xs-4">
                                                <span className="h-label">{t('operations.bulk_update_by_code.shipping_notes')}</span>
                                            </div>
                                            <div className="col-xs-4">
                                                <span className="h-label">&nbsp;</span>
                                            </div>
                                        </div>
                                        <div className="row form-group">
                                            <div className="col-xs-4">
                                                {visible.includes('warehouse') ? (
                                                    <select className="form-control" value={form.warehouse_id} onChange={(e) => setField('warehouse_id', e.target.value)}>
                                                        <option value="">{t('operations.bulk_update_by_code.warehouse_placeholder')}</option>
                                                        {warehouses.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                                                    </select>
                                                ) : null}
                                            </div>
                                            <div className="col-xs-4">
                                                {visible.includes('notes') ? (
                                                    <input className="form-control" value={form.shipping_notes} onChange={(e) => setField('shipping_notes', e.target.value)} />
                                                ) : null}
                                            </div>
                                            <div className="col-xs-4" />
                                        </div>
                                    </>
                                ) : null}

                                {visible.includes('dims') ? (
                                    <>
                                        <div className="row">
                                            <div className="col-xs-4">
                                                <span className="h-label">{t('operations.bulk_update_by_code.length')}</span>
                                            </div>
                                            <div className="col-xs-4">
                                                <span className="h-label">{t('operations.bulk_update_by_code.width')}</span>
                                            </div>
                                            <div className="col-xs-4">
                                                <span className="h-label">{t('operations.bulk_update_by_code.height')}</span>
                                            </div>
                                        </div>
                                        <div className="row form-group">
                                            <div className="col-xs-4">
                                                <input className="form-control" type="number" min="0" value={form.length_cm} onChange={(e) => setField('length_cm', e.target.value)} />
                                            </div>
                                            <div className="col-xs-4">
                                                <input className="form-control" type="number" min="0" value={form.width_cm} onChange={(e) => setField('width_cm', e.target.value)} />
                                            </div>
                                            <div className="col-xs-4">
                                                <input className="form-control" type="number" min="0" value={form.height_cm} onChange={(e) => setField('height_cm', e.target.value)} />
                                            </div>
                                        </div>
                                    </>
                                ) : null}

                                {visible.includes('shipping') ? (
                                    <>
                                        <div className="row">
                                            <div className="col-xs-4">
                                                <span className="h-label">{t('operations.bulk_update_by_code.shipping_provider')}</span>
                                            </div>
                                            <div className="col-xs-4">
                                                <span className="h-label">{t('operations.bulk_update_by_code.ship_via')}</span>
                                            </div>
                                            <div className="col-xs-4">
                                                <span className="h-label">{t('operations.bulk_update_by_code.weight')}</span>
                                            </div>
                                        </div>
                                        <div className="row form-group">
                                            <div className="col-xs-4">
                                                <select className="form-control" value={form.shipping_provider} onChange={(e) => onProviderChange(e.target.value)}>
                                                    <option value="">{t('operations.bulk_update_by_code.shipping_provider_placeholder')}</option>
                                                    {providers.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                                                </select>
                                            </div>
                                            <div className="col-xs-4">
                                                <select className="form-control" value={form.shipping_method} onChange={(e) => setField('shipping_method', e.target.value)}>
                                                    <option value="">&nbsp;</option>
                                                    {transportOptions.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                                                </select>
                                            </div>
                                            <div className="col-xs-4">
                                                <input className="form-control" type="number" min="0" value={form.weight_grams} onChange={(e) => setField('weight_grams', e.target.value)} />
                                            </div>
                                        </div>
                                    </>
                                ) : null}

                                {visible.includes('delivery_status') ? (
                                    <div className="form-group">
                                        <span className="h-label">{t('operations.bulk_update_by_code.delivery_status')}</span>
                                        <select className="form-control" value={form.delivery_status} onChange={(e) => setField('delivery_status', e.target.value)}>
                                            <option value="">{t('operations.bulk_update_by_code.delivery_status_placeholder')}</option>
                                            {deliveryStatuses.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                                        </select>
                                    </div>
                                ) : null}

                                {visible.includes('reconciliation_status') ? (
                                    <div className="form-group">
                                        <span className="h-label">{t('operations.bulk_update_by_code.reconciliation_status')}</span>
                                        <select className="form-control" value={form.reconciliation_status} onChange={(e) => setField('reconciliation_status', e.target.value)}>
                                            <option value="">{t('operations.bulk_update_by_code.select_placeholder')}</option>
                                            {reconStatuses.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                                        </select>
                                    </div>
                                ) : null}

                                {visible.includes('accounting_note') ? (
                                    <div className="form-group">
                                        <span className="h-label">{t('operations.bulk_update_by_code.accounting_note')}</span>
                                        <textarea className="form-control" rows={4} value={form.accounting_note} onChange={(e) => setField('accounting_note', e.target.value)} />
                                    </div>
                                ) : null}

                                {visible.includes('care') ? (
                                    <div className="row">
                                        <div className="col-xs-6 form-group">
                                            <span className="h-label">{t('operations.bulk_update_by_code.care_status')}</span>
                                            <select className="form-control" value={form.warehouse_care_status} onChange={(e) => setField('warehouse_care_status', e.target.value)}>
                                                <option value="">{t('operations.bulk_update_by_code.select_placeholder')}</option>
                                                {careStatuses.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                                            </select>
                                        </div>
                                        <div className="col-xs-6 form-group">
                                            <span className="h-label">{t('operations.bulk_update_by_code.care_note')}</span>
                                            <input className="form-control" value={form.warehouse_care_note} onChange={(e) => setField('warehouse_care_note', e.target.value)} />
                                        </div>
                                    </div>
                                ) : null}

                                {visible.includes('note') ? (
                                    <div className="form-group">
                                        <span className="h-label">{t('operations.bulk_update_by_code.note')}</span>
                                        <input className="form-control" value={form.note} onChange={(e) => setField('note', e.target.value)} />
                                    </div>
                                ) : null}
                            </div>
                        ) : null}
                    </div>

                    {results.length > 0 ? (
                        <div className="ps-wh-bulk-results">
                            <div className="ps-wh-bulk-progress-sep" />
                            <h4 className="ps-wh-bulk-results-title">{t('operations.bulk_update_by_code.results')}</h4>
                            <table className="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>{t('operations.bulk_update_by_code.col_input')}</th>
                                        <th>{t('operations.bulk_update_by_code.col_order')}</th>
                                        <th>{t('operations.bulk_update_by_code.col_result')}</th>
                                        <th>{t('operations.bulk_update_by_code.col_detail')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {results.map((row, index) => (
                                        <tr key={`${row.code}-${index}`} className={row.ok ? '' : 'danger'}>
                                            <td>{row.code}</td>
                                            <td>{row.order_code || '—'}</td>
                                            <td>{row.ok ? t('operations.bulk_update_by_code.result_ok') : t('operations.bulk_update_by_code.result_fail')}</td>
                                            <td>{row.message}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            <button type="button" className="btn btn-default btn-sm" onClick={() => router.visit(backUrl)}>
                                {t('operations.bulk_update_by_code.back')}
                            </button>
                        </div>
                    ) : null}
                </div>
            </section>
        </AppLayout>
    );
}
