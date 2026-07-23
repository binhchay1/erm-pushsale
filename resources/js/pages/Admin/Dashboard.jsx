import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    Area,
    Bar,
    CartesianGrid,
    ComposedChart,
    Legend,
    Line,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

import { RealtimeBadge } from '@/components/layout/RealtimeBadge';
import { RoleDashboardShell } from '@/components/dashboard/RoleDashboardShell';
import { useRealtimeDashboard } from '@/hooks/useRealtimeDashboard';
import { formatCurrency, formatCurrencyCompact, formatDate, formatNumber, formatPercent } from '@/lib/format';

const moneyCards = [
    ['recognized_revenue', 'Doanh thu ghi nhận', 'Đơn đã giao/đã thanh toán', 'fa-line-chart', 'blue'],
    ['cash_collected', 'Tiền đã thu', 'Cọc + COD đã đối soát', 'fa-money', 'green'],
    ['outstanding_cod', 'COD chưa thu', 'Cần tiếp tục đối soát', 'fa-truck', 'orange'],
    ['net_profit', 'Lợi nhuận ròng', 'Sau toàn bộ chi phí', 'fa-balance-scale', 'red'],
];

const costCards = [
    ['booked_revenue', 'Doanh số đã chốt'],
    ['gross_profit', 'Lợi nhuận gộp'],
    ['marketing_spend', 'Chi phí marketing'],
    ['cogs', 'Giá vốn hàng bán'],
    ['shipping_cost', 'Chi phí vận chuyển'],
    ['payroll_cost', 'Chi phí nhân sự'],
    ['operating_expenses', 'Chi phí vận hành khác'],
    ['inventory_value', 'Giá trị tồn kho'],
];

function DashboardTooltip({ active, payload, label }) {
    if (!active || !payload?.length) return null;

    return (
        <div className="psfd-chart-tooltip">
            <strong>{label}</strong>
            {payload.map((item) => <div key={item.dataKey}><span>{item.name}</span><b>{formatCurrency(item.value)}</b></div>)}
        </div>
    );
}

function KpiCard({ item, financial }) {
    const [key, label, hint, icon, tone] = item;
    const value = financial[key] ?? 0;

    return (
        <article className={`psfd-kpi psfd-kpi-${tone}`}>
            <div>
                <span>{label}</span>
                <strong>{formatCurrency(value)}</strong>
                <small>{hint}</small>
            </div>
            <i className={`fa ${icon}`} aria-hidden="true" />
        </article>
    );
}

function Metric({ label, value, money = false, percent = false, suffix = '' }) {
    return (
        <div className="psfd-metric">
            <span>{label}</span>
            <strong>{money ? formatCurrency(value) : percent ? formatPercent(value) : `${formatNumber(value)}${suffix}`}</strong>
        </div>
    );
}

function BudgetTable({ rows = [] }) {
    return (
        <div className="psfd-table-scroll">
            <table className="psfd-table">
                <thead>
                    <tr>
                        <th>Kết nối landing</th>
                        <th>Marketing / Kênh</th>
                        <th>Ngân sách kế hoạch</th>
                        <th>Thực chi</th>
                        <th>Tỷ lệ sử dụng</th>
                        <th>Chi phí tính báo cáo</th>
                        <th>Còn lại</th>
                        <th>Contact</th>
                        <th>Đơn chốt</th>
                        <th>Doanh thu</th>
                        <th>CPL</th>
                        <th>CPA</th>
                        <th>ROAS</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.length ? rows.map((row) => (
                        <tr key={row.id}>
                            <td><strong>{row.name}</strong><small>{row.is_active ? 'Đang hoạt động' : 'Tạm dừng'} · {formatDate(row.period_from)} - {formatDate(row.period_to)}</small></td>
                            <td>{row.marketer}<small>{row.channel}</small></td>
                            <td className="money">{formatCurrency(row.planned)}</td>
                            <td className="money">{formatCurrency(row.actual)}</td>
                            <td>
                                <strong className={Number(row.utilization_rate ?? 0) > 100 ? 'psfd-over-budget' : ''}>{formatPercent(row.utilization_rate ?? 0)}</strong>
                                <small>{Number(row.planned ?? 0) > 0 ? 'Thực chi / kế hoạch' : 'Chưa có ngân sách'}</small>
                            </td>
                            <td className="money"><strong>{formatCurrency(row.effective)}</strong><small>{row.basis === 'actual' ? 'Theo thực chi' : row.basis === 'mixed' ? 'Thực chi + kế hoạch' : row.basis === 'planned' ? 'Theo kế hoạch phân bổ' : 'Chưa có chi phí'}</small></td>
                            <td className={`money ${row.remaining < 0 ? 'negative' : 'positive'}`}>{formatCurrency(row.remaining)}</td>
                            <td>{formatNumber(row.leads)}</td>
                            <td>{formatNumber(row.closed_orders)}</td>
                            <td className="money">{formatCurrency(row.revenue)}</td>
                            <td className="money">{formatCurrency(row.cpl)}</td>
                            <td className="money">{formatCurrency(row.cpa)}</td>
                            <td>{formatNumber(row.roas)}x</td>
                        </tr>
                    )) : <tr><td colSpan="13" className="empty">Chưa có kết nối landing trong phạm vi dữ liệu này.</td></tr>}
                </tbody>
            </table>
        </div>
    );
}

function AlertPanel({ alerts = [] }) {
    return (
        <section className="psfd-panel psfd-alert-panel">
            <header><strong>Cảnh báo cần xử lý</strong><span>{alerts.length} mục</span></header>
            <div className="psfd-alert-list">
                {alerts.length ? alerts.map((alert, index) => (
                    <article key={`${alert.title}-${index}`} className={`psfd-alert ${alert.level}`}>
                        <i className={`fa ${alert.level === 'danger' ? 'fa-exclamation-circle' : alert.level === 'warning' ? 'fa-warning' : 'fa-info-circle'}`} />
                        <div><strong>{alert.title}</strong><p>{alert.description}</p></div>
                    </article>
                )) : <div className="psfd-no-alert"><i className="fa fa-check-circle" /> Không có cảnh báo tài chính nghiêm trọng trong kỳ.</div>}
            </div>
        </section>
    );
}

function AdminDashboardContent({ stats: initialStats, filters = {} }) {
    const { stats, connected } = useRealtimeDashboard('admin', initialStats);
    const financial = stats?.financial ?? {};
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? financial.period?.from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? financial.period?.to ?? '');
    const series = stats?.cash_flow_series ?? [];
    const expenseTotal = useMemo(
        () => Number(financial.marketing_spend ?? 0) + Number(financial.cogs ?? 0) + Number(financial.shipping_cost ?? 0) + Number(financial.payroll_cost ?? 0) + Number(financial.operating_expenses ?? 0),
        [financial],
    );

    const submitFilter = (event) => {
        event.preventDefault();
        router.get('/admin/dashboard', { ...filters, preset: 'custom', date_from: dateFrom, date_to: dateTo }, { preserveState: true, replace: true });
    };

    return (
        <section className="psfd-page">
            <form className="psfd-toolbar psfd-toolbar-v88" onSubmit={submitFilter}>
                <div className="psfd-toolbar-copy">
                    <h1>ADMIN DASHBOARD</h1>
                    <p>Điều hành doanh thu, dòng tiền, marketing, kho và vận hành trên cùng một nguồn dữ liệu.</p>
                </div>
                <div className="psfd-toolbar-controls">
                    <div className="psfd-filter-controls">
                        <label>Từ ngày<input type="date" value={dateFrom} onChange={(event) => setDateFrom(event.target.value)} /></label>
                        <label>Đến ngày<input type="date" min={dateFrom || undefined} value={dateTo} onChange={(event) => setDateTo(event.target.value)} /></label>
                        <button type="submit"><i className="fa fa-search" /> Tải dữ liệu</button>
                    </div>
                    <div className="psfd-live-slot">
                        <RealtimeBadge connected={connected} />
                    </div>
                </div>
            </form>

            <div className="psfd-kpi-grid">
                {moneyCards.map((item) => <KpiCard key={item[0]} item={item} financial={financial} />)}
            </div>

            <div className="psfd-finance-strip">
                {costCards.map(([key, label]) => <Metric key={key} label={label} value={financial[key]} money />)}
            </div>

            <div className="psfd-layout-main">
                <section className="psfd-panel psfd-chart-panel">
                    <header>
                        <div><strong>Dòng tiền và lợi nhuận theo ngày</strong><span>Đơn vị hiển thị: VND · tối đa 31 ngày</span></div>
                        <div className="psfd-panel-summary"><span>Tổng chi phí</span><b>{formatCurrency(expenseTotal)}</b></div>
                    </header>
                    <div className="psfd-chart">
                        <ResponsiveContainer width="100%" height="100%">
                            <ComposedChart data={series} margin={{ top: 12, right: 15, left: 6, bottom: 2 }}>
                                <CartesianGrid strokeDasharray="3 3" vertical={false} />
                                <XAxis dataKey="label" tick={{ fontSize: 10 }} />
                                <YAxis tickFormatter={formatCurrencyCompact} tick={{ fontSize: 10 }} width={64} />
                                <Tooltip content={<DashboardTooltip />} />
                                <Legend wrapperStyle={{ fontSize: 11 }} />
                                <Bar dataKey="marketing" name="Marketing" stackId="cost" fill="#f39c12" />
                                <Bar dataKey="cogs" name="Giá vốn" stackId="cost" fill="#dd4b39" />
                                <Bar dataKey="shipping" name="Vận chuyển" stackId="cost" fill="#8e44ad" />
                                <Bar dataKey="payroll" name="Nhân sự" stackId="cost" fill="#605ca8" />
                                <Bar dataKey="expenses" name="Vận hành khác" stackId="cost" fill="#7f8c8d" />
                                <Area type="monotone" dataKey="revenue" name="Doanh thu ghi nhận" fill="#3c8dbc" stroke="#2573a7" fillOpacity={0.16} />
                                <Line type="monotone" dataKey="cash_collected" name="Tiền đã thu" stroke="#00c0ef" strokeWidth={2} dot={{ r: 2 }} />
                                <Line type="monotone" dataKey="net_profit" name="Lợi nhuận ròng" stroke="#00a65a" strokeWidth={2} dot={{ r: 2 }} />
                            </ComposedChart>
                        </ResponsiveContainer>
                    </div>
                </section>

                <section className="psfd-panel psfd-control-panel">
                    <header><strong>Chỉ số kiểm soát</strong><span>{financial.period?.from} → {financial.period?.to}</span></header>
                    <div className="psfd-control-grid">
                        <Metric label="Lead hợp lệ" value={financial.leads} />
                        <Metric label="Tổng đơn" value={financial.orders} />
                        <Metric label="Đơn đã chốt" value={financial.closed_orders} />
                        <Metric label="Đơn ghi nhận DT" value={financial.recognized_orders} />
                        <Metric label="Tỷ lệ chuyển đổi" value={financial.conversion_rate} percent />
                        <Metric label="Tỷ lệ giao thành công" value={financial.delivery_rate} percent />
                        <Metric label="Tỷ lệ thu tiền / doanh số chốt" value={financial.cash_collection_rate} percent />
                        <Metric label="Giá trị đơn TB" value={financial.aov} money />
                        <Metric label="ROAS" value={financial.roas ?? 0} suffix="x" />
                        <Metric label="Đơn hoàn" value={financial.returned_orders} />
                        <Metric label="Đơn hủy/lỗi giao" value={financial.cancelled_orders} />
                        <Metric label="Dòng tồn kho thấp" value={financial.low_stock_items} />
                    </div>
                    <div className="psfd-budget-summary">
                        <div><span>Kế hoạch trong kỳ</span><strong>{formatCurrency(financial.marketing_planned ?? 0)}</strong></div>
                        <div><span>Thực chi đã nhập</span><strong>{formatCurrency(financial.marketing_actual ?? 0)}</strong></div>
                        <div><span>Chi phí đưa vào báo cáo</span><strong>{formatCurrency(financial.marketing_spend ?? 0)}</strong></div>
                    </div>
                    <div className="psfd-payroll-summary">
                        <div><span>Lương cơ bản phân bổ</span><strong>{formatCurrency(financial.payroll_base_salary ?? 0)}</strong></div>
                        <div><span>Thưởng/hoa hồng</span><strong>{formatCurrency(financial.payroll_commission ?? 0)}</strong></div>
                        <div><span>Kế hoạch nhân sự</span><strong>{formatNumber(financial.payroll_plan_count ?? 0)} kế hoạch</strong><small>{formatNumber(financial.payroll_estimated_plan_count ?? 0)} kế hoạch đang tạm tính đủ ngày công</small></div>
                    </div>
                    <div className="psfd-budget-basis">
                        <i className="fa fa-info-circle" />
                        <span>Chi marketing đang tính theo <b>{financial.marketing_basis === 'actual' ? 'thực chi từng ngày' : financial.marketing_basis === 'mixed' ? 'thực chi kết hợp phần kế hoạch chưa nhập' : financial.marketing_basis === 'planned' ? 'ngân sách kế hoạch phân bổ' : 'chưa có ngân sách hoặc thực chi'}</b>. Tỷ lệ thu tiền được đối chiếu với doanh số đã chốt.</span>
                    </div>
                </section>
            </div>

            <section className="psfd-panel psfd-budget-panel">
                <header>
                    <div><strong>Hiệu quả theo kết nối landing</strong><span>Đối chiếu ngân sách → contact → đơn chốt → doanh thu → ROAS</span></div>
                    <a href="/admin/marketing/landing-connections"><i className="fa fa-external-link" /> Quản lý kết nối</a>
                </header>
                <BudgetTable rows={stats?.landing_budget_rows} />
            </section>

            <AlertPanel alerts={stats?.financial_alerts} />
        </section>
    );
}

export default function Dashboard({ stats: initialStats, filters = {} }) {
    return (
        <RoleDashboardShell role="admin" title="Admin dashboard">
            <AdminDashboardContent stats={initialStats} filters={filters} />
        </RoleDashboardShell>
    );
}
