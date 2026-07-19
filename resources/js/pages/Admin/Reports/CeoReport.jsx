import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import { CeoReportFilterBar } from '@/components/reports/ceo/CeoReportFilterBar';
import { CeoReportHelpDialog } from '@/components/reports/ceo/CeoReportHelpDialog';
import {
    formatNaNPct,
    formatPct,
    maxInRows,
    PushsaleProgressCell,
    sumRows,
} from '@/components/reports/ceo/PushsaleProgressCell';
import { PushsaleStatusSummary } from '@/components/reports/ceo/PushsaleStatusSummary';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

function exportTableToExcel(tableId, fileName) {
    const tab = document.getElementById(tableId);
    if (!tab) {
        return;
    }

    let tabText = "<table border='2px'>";
    for (let j = 0; j < tab.rows.length; j++) {
        tabText += `${tab.rows[j].innerHTML}</tr>`;
    }
    tabText += '</table>';
    tabText = tabText.replace(/<A[^>]*>|<\/A>/g, '');
    tabText = tabText.replace(/<img[^>]*>/gi, '');
    tabText = tabText.replace(/<input[^>]*>|<\/input>/gi, '');

    const pom = document.createElement('a');
    pom.setAttribute('href', `data:application/vnd.ms-excel,${encodeURIComponent(tabText)}`);
    pom.setAttribute('download', fileName);
    pom.dispatchEvent(new Event('click'));
}

function saleLabel(row) {
    return `${row.saleStaffName} (${row.saleUsername})`;
}

function marketingLabel(row) {
    if (row.marketerUsername) {
        return `${row.marketerName} (${row.marketerUsername})`;
    }
    return row.marketerName;
}

function SaleTable({ rows, allRows, t }) {
    const maxNewContact = maxInRows(rows, 'newContact');
    const maxNewClosed = maxInRows(rows, 'newClosed');
    const maxNewProductQty = maxInRows(rows, 'newProductQty');
    const maxNewEstRevenue = maxInRows(rows, 'newEstRevenue');
    const maxOldContact = maxInRows(rows, 'oldContact');
    const maxOldClosed = maxInRows(rows, 'oldClosed');
    const maxOldProductQty = maxInRows(rows, 'oldProductQty');
    const maxOldEstRevenue = maxInRows(rows, 'oldEstRevenue');
    const maxTotalEstRevenue = maxInRows(rows, 'totalEstRevenue');
    const maxUpsellQty = maxInRows(rows, 'upsellQty');
    const maxUpsellRevenue = maxInRows(rows, 'upsellRevenue');
    const maxCodFee = maxInRows(rows, 'codFee');
    const maxCodSupport = maxInRows(rows, 'codSupport');
    const maxBankTransfer = maxInRows(rows, 'bankTransfer');
    const maxDeposit = maxInRows(rows, 'deposit');
    const maxSalesKpi = maxInRows(rows, 'salesKpi');

    const tNewContact = sumRows(allRows, 'newContact');
    const tNewClosed = sumRows(allRows, 'newClosed');
    const tNewProductQty = sumRows(allRows, 'newProductQty');
    const tNewEstRevenue = sumRows(allRows, 'newEstRevenue');
    const tOldContact = sumRows(allRows, 'oldContact');
    const tOldClosed = sumRows(allRows, 'oldClosed');
    const tOldProductQty = sumRows(allRows, 'oldProductQty');
    const tOldEstRevenue = sumRows(allRows, 'oldEstRevenue');
    const tTotalEstRevenue = sumRows(allRows, 'totalEstRevenue');
    const tUpsellQty = sumRows(allRows, 'upsellQty');
    const tUpsellRevenue = sumRows(allRows, 'upsellRevenue');
    const tCodFee = sumRows(allRows, 'codFee');
    const tCodSupport = sumRows(allRows, 'codSupport');
    const tBankTransfer = sumRows(allRows, 'bankTransfer');
    const tDeposit = sumRows(allRows, 'deposit');
    const tSalesKpi = sumRows(allRows, 'salesKpi');

    return (
        <div className="dragscroll1 tableFixHead table_sale">
            <table className="table table-bordered table-multi-select" id="tableReportSale">
                <thead>
                    <tr className="drags-area">
                        <th className="text-center" style={{ width: 50 }} />
                        <th className="text-center" style={{ width: '10%' }} />
                        <th className="text-center" colSpan={5}>
                            {t('reports.ceo_report.new_customers_group')}
                        </th>
                        <th className="text-center" colSpan={5}>
                            {t('reports.ceo_report.old_customers_group')}
                        </th>
                        <th className="text-center" colSpan={11} style={{ width: '42%' }}>
                            {t('reports.ceo_report.total_group')}
                        </th>
                    </tr>
                    <tr className="drags-area">
                        <th className="text-center" style={{ width: 50 }}>
                            {t('reports.ceo_report.stt')}
                        </th>
                        <th className="text-center" style={{ width: '10%' }}>
                            {t('reports.ceo_report.sale')}
                        </th>
                        <th className="text-center">{t('reports.ceo_report.contact')}</th>
                        <th className="text-center">{t('reports.ceo_report.closed')}</th>
                        <th className="text-center no-wrap">{t('reports.ceo_report.close_rate')}</th>
                        <th className="text-center">{t('reports.ceo_report.products')}</th>
                        <th className="text-center">{t('reports.ceo_report.est_revenue')}</th>
                        <th className="text-center">{t('reports.ceo_report.contact')}</th>
                        <th className="text-center">{t('reports.ceo_report.closed')}</th>
                        <th className="text-center no-wrap">{t('reports.ceo_report.close_rate')}</th>
                        <th className="text-center">{t('reports.ceo_report.products')}</th>
                        <th className="text-center">{t('reports.ceo_report.est_revenue')}</th>
                        <th className="text-center" style={{ width: '10%' }}>
                            {t('reports.ceo_report.est_revenue')}
                        </th>
                        <th className="text-center">Upsale (SL)</th>
                        <th className="text-center">Upsale (DS)</th>
                        <th className="text-center">% DS upsale</th>
                        <th className="text-center show_sp" style={{ width: '4%' }}>
                            {t('reports.ceo_report.cod_fee')}
                        </th>
                        <th className="text-center show_sp" style={{ width: '4%' }}>
                            {t('reports.ceo_report.cod_support')}
                        </th>
                        <th className="text-center show_sp" style={{ width: '4%' }}>
                            {t('reports.ceo_report.discount')}
                        </th>
                        <th className="text-center show_sp" style={{ width: '4%' }}>
                            {t('reports.ceo_report.deposit')}
                        </th>
                        <th className="text-center" style={{ width: '7%' }}>
                            {t('reports.ceo_report.kpi_revenue')}
                        </th>
                        <th className="text-center no-wrap" style={{ width: '3%' }}>
                            {t('reports.ceo_report.kpi_pct')}
                        </th>
                    </tr>
                    <tr className="rowsum drags-area">
                        <td colSpan={2} className="text-center font-weight-bold">
                            {t('reports.ceo_report.total_row')}:{' '}
                        </td>
                        <td className="text-center font-weight-bold">{tNewContact}</td>
                        <td className="text-center font-weight-bold">{tNewClosed}</td>
                        <td className="text-center font-weight-bold">
                            {formatPct(tNewClosed, tNewContact)}
                        </td>
                        <td className="text-center font-weight-bold">{tNewProductQty}</td>
                        <td className="text-center font-weight-bold">{tNewEstRevenue}</td>
                        <td className="text-center font-weight-bold">{tOldContact}</td>
                        <td className="text-center font-weight-bold">{tOldClosed}</td>
                        <td className="text-center font-weight-bold">
                            {formatPct(tOldClosed, tOldContact)}
                        </td>
                        <td className="text-center font-weight-bold">{tOldProductQty}</td>
                        <td className="text-center font-weight-bold">{tOldEstRevenue}</td>
                        <td className="text-center font-weight-bold">{tTotalEstRevenue}</td>
                        <td className="text-center font-weight-bold">{tUpsellQty}</td>
                        <td className="text-center font-weight-bold">{tUpsellRevenue}</td>
                        <td className="text-center font-weight-bold">{formatNaNPct(tUpsellRevenue, tTotalEstRevenue)}</td>
                        <td className="text-center font-weight-bold show_sp">{tCodFee}</td>
                        <td className="text-center font-weight-bold show_sp">{tCodSupport}</td>
                        <td className="text-center font-weight-bold show_sp">{tBankTransfer}</td>
                        <td className="text-center font-weight-bold show_sp">{tDeposit}</td>
                        <td className="text-center font-weight-bold">{tSalesKpi}</td>
                        <td className="text-center font-weight-bold">
                            {formatNaNPct(tTotalEstRevenue, tSalesKpi)}
                        </td>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((r, index) => (
                        <tr key={r.saleStaffId}>
                            <td className="text-center">{index + 1}</td>
                            <td>{saleLabel(r)}</td>
                            <PushsaleProgressCell
                                value={r.newContact}
                                max={maxNewContact}
                                className="tdSoContact"
                            />
                            <PushsaleProgressCell
                                value={r.newClosed}
                                max={maxNewClosed}
                                className="tdSoChotDon"
                            />
                            <PushsaleProgressCell
                                value={r.newCloseRate}
                                max={100}
                                format="percent"
                                className="tdTyLeChotDon"
                            />
                            <PushsaleProgressCell
                                value={r.newProductQty}
                                max={maxNewProductQty}
                                className="tdSoSanPham"
                            />
                            <PushsaleProgressCell
                                value={r.newEstRevenue}
                                max={maxNewEstRevenue}
                                format="currency"
                                className="tdDoanhSo"
                            />
                            <PushsaleProgressCell
                                value={r.oldContact}
                                max={maxOldContact}
                                className="tdSoContactOld"
                            />
                            <PushsaleProgressCell
                                value={r.oldClosed}
                                max={maxOldClosed}
                                className="tdSoChotDonOld"
                            />
                            <PushsaleProgressCell
                                value={r.oldCloseRate}
                                max={100}
                                format="percent"
                                className="tdTyLeChotDonOld"
                            />
                            <PushsaleProgressCell
                                value={r.oldProductQty}
                                max={maxOldProductQty}
                                className="tdSoSanPhamOld"
                            />
                            <PushsaleProgressCell
                                value={r.oldEstRevenue}
                                max={maxOldEstRevenue}
                                format="currency"
                                className="tdDoanhSoOld"
                            />
                            <PushsaleProgressCell
                                value={r.totalEstRevenue}
                                max={maxTotalEstRevenue}
                                format="currency"
                                className="tdDoanhSoTong"
                            />
                            <PushsaleProgressCell value={r.upsellQty} max={maxUpsellQty} className="tdSoSanPham" />
                            <PushsaleProgressCell value={r.upsellRevenue} max={maxUpsellRevenue} format="currency" className="tdDoanhSoTong" />
                            <PushsaleProgressCell value={r.upsellRevenueShare} max={100} format="percent" className="tdTyLeChotDon" />
                            <PushsaleProgressCell
                                value={r.codFee}
                                max={maxCodFee}
                                format="currency"
                                className="tdDoanhSoThucTe show_sp"
                            />
                            <PushsaleProgressCell
                                value={r.codSupport}
                                max={maxCodSupport}
                                format="currency"
                                className="tdDoanhSoThucTe show_sp"
                            />
                            <PushsaleProgressCell
                                value={r.bankTransfer}
                                max={maxBankTransfer}
                                format="currency"
                                className="tdDoanhSoThucTe show_sp"
                            />
                            <PushsaleProgressCell
                                value={r.deposit}
                                max={maxDeposit}
                                format="currency"
                                className="tdDoanhSoThucTe show_sp"
                            />
                            <PushsaleProgressCell
                                value={r.salesKpi}
                                max={maxSalesKpi}
                                format="currency"
                                className="tdDoanhSoThucTe"
                            />
                            <PushsaleProgressCell
                                value={r.achievementRate}
                                max={100}
                                format="percent"
                                className="tdSoChotDon"
                            />
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function MarketingTable({ rows, allRows, t }) {
    const maxBudget = maxInRows(rows, 'budget');
    const maxContacts = maxInRows(rows, 'contacts');
    const maxContactPrice = maxInRows(rows, 'contactPrice');
    const maxClosed = maxInRows(rows, 'closed');
    const maxNewEstRevenue = maxInRows(rows, 'newEstRevenue');
    const maxBudgetRatioNew = maxInRows(rows, 'budgetRevenueRatioNew');
    const maxBudgetRatioNewCk = maxInRows(rows, 'budgetRevenueRatioNewAfterDiscount');
    const maxOldEstRevenue = maxInRows(rows, 'oldEstRevenue');
    const maxTotalEstRevenue = maxInRows(rows, 'totalEstRevenue');
    const maxUpsellQty = maxInRows(rows, 'upsellQty');
    const maxUpsellRevenue = maxInRows(rows, 'upsellRevenue');
    const maxBudgetRatioTotal = maxInRows(rows, 'budgetRevenueRatioTotal');
    const maxCodFee = maxInRows(rows, 'codFee');
    const maxCodSupport = maxInRows(rows, 'codSupport');
    const maxBankTransfer = maxInRows(rows, 'bankTransfer');
    const maxDeposit = maxInRows(rows, 'deposit');
    const maxMarketingKpi = maxInRows(rows, 'marketingKpi');

    const tBudget = sumRows(allRows, 'budget');
    const tContacts = sumRows(allRows, 'contacts');
    const tClosed = sumRows(allRows, 'closed');
    const tNewEstRevenue = sumRows(allRows, 'newEstRevenue');
    const tOldEstRevenue = sumRows(allRows, 'oldEstRevenue');
    const tTotalEstRevenue = sumRows(allRows, 'totalEstRevenue');
    const tUpsellQty = sumRows(allRows, 'upsellQty');
    const tUpsellRevenue = sumRows(allRows, 'upsellRevenue');
    const tCodFee = sumRows(allRows, 'codFee');
    const tCodSupport = sumRows(allRows, 'codSupport');
    const tBankTransfer = sumRows(allRows, 'bankTransfer');
    const tDeposit = sumRows(allRows, 'deposit');
    const tMarketingKpi = sumRows(allRows, 'marketingKpi');

    const tContactPrice = tContacts > 0 ? Math.round(tBudget / tContacts) : 0;

    return (
        <div className="dragscroll1 tableFixHead table_marketing">
            <table className="table table-bordered table-multi-select" id="tableReportMarketing">
                <thead>
                    <tr className="drags-area">
                        <th className="text-center" style={{ width: 50 }} />
                        <th className="text-center" style={{ width: '11%' }} />
                        <th className="text-center" colSpan={8}>
                            {t('reports.ceo_report.new_customers_group')}
                        </th>
                        <th className="text-center" colSpan={1}>
                            {t('reports.ceo_report.old_customers_group')}
                        </th>
                        <th className="text-center" colSpan={12}>
                            {t('reports.ceo_report.total_group')}
                        </th>
                    </tr>
                    <tr className="drags-area">
                        <th className="text-center" style={{ width: 50 }}>
                            {t('reports.ceo_report.stt')}
                        </th>
                        <th className="text-center" style={{ width: '11%' }}>
                            {t('reports.ceo_report.marketing')}
                        </th>
                        <th className="text-center" style={{ width: '5%' }}>
                            {t('reports.ceo_report.budget')}
                        </th>
                        <th className="text-center" style={{ width: '5%' }}>
                            {t('reports.ceo_report.contact')}
                        </th>
                        <th className="text-center" style={{ width: '5%' }}>
                            {t('reports.ceo_report.contact_price')}
                        </th>
                        <th className="text-center" style={{ width: '5%' }}>
                            {t('reports.ceo_report.closed')}
                        </th>
                        <th className="text-center" style={{ width: '5%' }}>
                            {t('reports.ceo_report.close_rate_order')}
                        </th>
                        <th className="text-center" style={{ width: '6%' }}>
                            {t('reports.ceo_report.est_revenue_new')}
                        </th>
                        <th className="text-center" style={{ width: '6%' }}>
                            {t('reports.ceo_report.budget_revenue_new_pct')}
                        </th>
                        <th className="text-center" style={{ width: '6%' }}>
                            {t('reports.ceo_report.budget_revenue_new_ck_pct')}
                        </th>
                        <th className="text-center" style={{ width: '7%' }}>
                            {t('reports.ceo_report.est_revenue_old')}
                        </th>
                        <th className="text-center" style={{ width: '10%' }}>
                            {t('reports.ceo_report.est_revenue')}
                        </th>
                        <th className="text-center">Upsale (SL)</th>
                        <th className="text-center">Upsale (DS)</th>
                        <th className="text-center">% DS upsale</th>
                        <th className="text-center" style={{ width: '6%' }}>
                            {t('reports.ceo_report.budget_revenue_total_pct')}
                        </th>
                        <th className="text-center show_sp" style={{ width: '6%' }}>
                            {t('reports.ceo_report.cod_fee')}
                        </th>
                        <th className="text-center show_sp" style={{ width: '6%' }}>
                            {t('reports.ceo_report.cod_support')}
                        </th>
                        <th className="text-center show_sp" style={{ width: '5%' }}>
                            {t('reports.ceo_report.discount')}
                        </th>
                        <th className="text-center show_sp" style={{ width: '5%' }}>
                            {t('reports.ceo_report.deposit')}
                        </th>
                        <th className="text-center" style={{ width: '10%' }}>
                            {t('reports.ceo_report.kpi_revenue')}
                        </th>
                        <th className="text-center no-wrap" style={{ width: '7%' }}>
                            {t('reports.ceo_report.kpi_pct')}
                        </th>
                    </tr>
                    <tr className="rowsum drags-area">
                        <td colSpan={2} className="text-center font-weight-bold">
                            {t('reports.ceo_report.total_row')}:{' '}
                        </td>
                        <td className="text-center font-weight-bold">{tBudget}</td>
                        <td className="text-center font-weight-bold">{tContacts}</td>
                        <td className="text-center font-weight-bold">{tContactPrice}</td>
                        <td className="text-center font-weight-bold">{tClosed}</td>
                        <td className="text-center font-weight-bold">
                            {formatPct(tClosed, tContacts)}
                        </td>
                        <td className="text-center font-weight-bold">{tNewEstRevenue}</td>
                        <td className="text-center font-weight-bold">
                            {formatNaNPct(tBudget, tNewEstRevenue)}
                        </td>
                        <td className="text-center font-weight-bold">
                            {formatNaNPct(tBudget, tNewEstRevenue)}
                        </td>
                        <td className="text-center font-weight-bold">{tOldEstRevenue}</td>
                        <td className="text-center font-weight-bold">{tTotalEstRevenue}</td>
                        <td className="text-center font-weight-bold">{tUpsellQty}</td>
                        <td className="text-center font-weight-bold">{tUpsellRevenue}</td>
                        <td className="text-center font-weight-bold">{formatNaNPct(tUpsellRevenue, tTotalEstRevenue)}</td>
                        <td className="text-center font-weight-bold">
                            {formatNaNPct(tBudget, tTotalEstRevenue)}
                        </td>
                        <td className="text-center font-weight-bold show_sp">{tCodFee}</td>
                        <td className="text-center font-weight-bold show_sp">{tCodSupport}</td>
                        <td className="text-center font-weight-bold show_sp">{tBankTransfer}</td>
                        <td className="text-center font-weight-bold show_sp">{tDeposit}</td>
                        <td className="text-center font-weight-bold">{tMarketingKpi}</td>
                        <td className="text-center font-weight-bold">
                            {formatNaNPct(tTotalEstRevenue, tMarketingKpi)}
                        </td>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((r, index) => (
                        <tr key={r.marketerId ?? index}>
                            <td className="text-center">{index + 1}</td>
                            <td>{marketingLabel(r)}</td>
                            <PushsaleProgressCell
                                value={r.budget}
                                max={maxBudget}
                                format="currency"
                                className="tdNganSach_Marketing"
                            />
                            <PushsaleProgressCell
                                value={r.contacts}
                                max={maxContacts}
                                className="tdSoContact_Marketing"
                            />
                            <PushsaleProgressCell
                                value={r.contactPrice}
                                max={maxContactPrice}
                                format="currency"
                                className="tdSoClick_Marketing"
                            />
                            <PushsaleProgressCell
                                value={r.closed}
                                max={maxClosed}
                                className="tdSoClick_Marketing"
                            />
                            <PushsaleProgressCell
                                value={r.closeRate}
                                max={100}
                                format="percent"
                                className="tdTyLeContactClick_Marketing"
                            />
                            <PushsaleProgressCell
                                value={r.newEstRevenue}
                                max={maxNewEstRevenue}
                                format="currency"
                                className="tdDoanhSo_Marketing"
                            />
                            <PushsaleProgressCell
                                value={r.budgetRevenueRatioNew}
                                max={maxBudgetRatioNew}
                                format="percent"
                                className="tdTyLeNganSachDoanhSo_Marketing"
                            />
                            <PushsaleProgressCell
                                value={r.budgetRevenueRatioNewAfterDiscount}
                                max={maxBudgetRatioNewCk}
                                format="percent"
                                className="tdTyLeNganSachDoanhSo_Marketing"
                            />
                            <PushsaleProgressCell
                                value={r.oldEstRevenue}
                                max={maxOldEstRevenue}
                                format="currency"
                                className="tdDoanhSo_Marketing"
                            />
                            <PushsaleProgressCell
                                value={r.totalEstRevenue}
                                max={maxTotalEstRevenue}
                                format="currency"
                                className="tdDoanhSo_Marketing"
                            />
                            <PushsaleProgressCell value={r.upsellQty} max={maxUpsellQty} className="tdSoClick_Marketing" />
                            <PushsaleProgressCell value={r.upsellRevenue} max={maxUpsellRevenue} format="currency" className="tdDoanhSo_Marketing" />
                            <PushsaleProgressCell value={r.upsellRevenueShare} max={100} format="percent" className="tdTyLeNganSachDoanhSo_Marketing" />
                            <PushsaleProgressCell
                                value={r.budgetRevenueRatioTotal}
                                max={maxBudgetRatioTotal}
                                format="percent"
                                className="tdTyLeNganSachDoanhSo_Marketing"
                            />
                            <PushsaleProgressCell
                                value={r.codFee}
                                max={maxCodFee}
                                format="currency"
                                className="tdDoanhSo_Marketing show_sp"
                            />
                            <PushsaleProgressCell
                                value={r.codSupport}
                                max={maxCodSupport}
                                format="currency"
                                className="tdDoanhSo_Marketing show_sp"
                            />
                            <PushsaleProgressCell
                                value={r.bankTransfer}
                                max={maxBankTransfer}
                                format="currency"
                                className="tdDoanhSo_Marketing show_sp"
                            />
                            <PushsaleProgressCell
                                value={r.deposit}
                                max={maxDeposit}
                                format="currency"
                                className="tdDoanhSo_Marketing show_sp"
                            />
                            <PushsaleProgressCell
                                value={r.marketingKpi}
                                max={maxMarketingKpi}
                                format="currency"
                                className="tdDoanhSoKPI_Marketing"
                            />
                            <PushsaleProgressCell
                                value={r.achievementRate}
                                max={100}
                                format="percent"
                                className="tdSoChotDon"
                            />
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function CeoReport({ filters, filterOptions, report, routeUrl = '/admin/reports/ceo' }) {
    const t = useT();
    const [helpOpen, setHelpOpen] = useState(false);
    const [summaryCollapsed, setSummaryCollapsed] = useState(false);

    const displayLimit = useMemo(() => {
        const perPage = Number(filters?.per_page);
        if (!perPage || perPage >= 999999) {
            return Infinity;
        }
        return perPage;
    }, [filters?.per_page]);

    const saleRowsAll = useMemo(
        () =>
            [...(report?.saleRows ?? [])].sort(
                (a, b) => b.newContact - a.newContact || b.oldContact - a.oldContact,
            ),
        [report?.saleRows],
    );

    const marketingRowsAll = useMemo(
        () => [...(report?.marketingRows ?? [])].sort((a, b) => b.contacts - a.contacts),
        [report?.marketingRows],
    );

    const saleRowsDisplay = useMemo(
        () => saleRowsAll.slice(0, displayLimit),
        [saleRowsAll, displayLimit],
    );

    const marketingRowsDisplay = useMemo(
        () => marketingRowsAll.slice(0, displayLimit),
        [marketingRowsAll, displayLimit],
    );

    const handleExport = (type) => {
        if (type === 'shipping') {
            exportTableToExcel('tableReportGiaoVan', '5.ceo_giao_van.xls');
        } else if (type === 'sale') {
            exportTableToExcel('tableReportSale', '6.ceo_sale.xls');
        } else if (type === 'marketing') {
            exportTableToExcel('tableReportMarketing', '7.ceo_marketing.xls');
        }
    };

    return (
        <AppLayout>
            <Head title={t('reports.ceo_report.title')} />

            <div className="ceo-report-pushsale">
                <CeoReportFilterBar
                    routeUrl={routeUrl}
                    filters={filters}
                    filterOptions={filterOptions}
                    onShowNote={() => setHelpOpen(true)}
                    onExport={handleExport}
                    summaryCollapsed={summaryCollapsed}
                    onToggleSummary={() => setSummaryCollapsed((v) => !v)}
                />

                <div className="box-body">
                    <PushsaleStatusSummary
                        statusSummary={report?.statusSummary}
                        collapsed={summaryCollapsed}
                    />

                    <SaleTable rows={saleRowsDisplay} allRows={saleRowsAll} t={t} />

                    <div className="table-gap" />

                    <MarketingTable
                        rows={marketingRowsDisplay}
                        allRows={marketingRowsAll}
                        t={t}
                    />
                </div>

                <CeoReportHelpDialog open={helpOpen} onClose={() => setHelpOpen(false)} />
            </div>
        </AppLayout>
    );
}
