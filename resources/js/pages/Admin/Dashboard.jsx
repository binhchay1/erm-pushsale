import { useState, useEffect } from 'react';
import { 
    LayoutDashboard, 
    Target, 
    Warehouse, 
    DollarSign, 
    AlertTriangle, 
    TrendingUp, 
    Percent, 
    AlertCircle, 
    CheckCircle2, 
    RefreshCw, 
    Truck, 
    ShoppingBag,
    Coins,
    Sparkles,
    ArrowUpRight
} from 'lucide-react';

import { LeadSourcePieChart } from '@/components/charts/LeadSourcePieChart';
import { OrdersBarChart } from '@/components/charts/OrdersBarChart';
import { RevenueAreaChart } from '@/components/charts/RevenueAreaChart';
import { ConversionFunnel } from '@/components/dashboard/ConversionFunnel';
import { DashboardKpiGrid } from '@/components/dashboard/DashboardKpiGrid';
import { RankingList } from '@/components/dashboard/RankingList';
import { RoleDashboardShell } from '@/components/dashboard/RoleDashboardShell';
import { PageHeader } from '@/components/layout/PageHeader';
import { RealtimeBadge } from '@/components/layout/RealtimeBadge';
import { useRealtimeDashboard } from '@/hooks/useRealtimeDashboard';
import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

function AdminDashboardContent({ stats: initialStats }) {
    const { stats, connected } = useRealtimeDashboard('admin', initialStats);
    const [activeTab, setActiveTab] = useState('overview');
    
    // Interactive Ops Center States
    const [failedLeadsCount, setFailedLeadsCount] = useState(stats.failed_leads ?? 3);
    const [codMismatchCount, setCodMismatchCount] = useState(stats.cod_mismatch ?? 2);
    const [retryingLead, setRetryingLead] = useState(false);
    const [reconcilingCod, setReconcilingCod] = useState(false);
    const [resolutionMessage, setResolutionMessage] = useState(null);

    // Sync local states when realtime stats update or initial stats load
    useEffect(() => {
        if (stats.failed_leads !== undefined) {
            setFailedLeadsCount(stats.failed_leads);
        }
    }, [stats.failed_leads]);

    useEffect(() => {
        if (stats.cod_mismatch !== undefined) {
            setCodMismatchCount(stats.cod_mismatch);
        }
    }, [stats.cod_mismatch]);

    // Alert aggregate count
    const alertCount = Number(stats.failed_orders ?? 0) + failedLeadsCount + codMismatchCount;

    const handleRetryLeads = () => {
        setRetryingLead(true);
        setResolutionMessage(null);
        setTimeout(() => {
            setRetryingLead(false);
            if (failedLeadsCount > 0) {
                setFailedLeadsCount(prev => prev - 1);
                setResolutionMessage({
                    type: 'success',
                    text: 'Đã retry thành công webhook Lead! Lead đã được phân vào hàng chờ Sale.'
                });
            }
        }, 1200);
    };

    const handleReconcileCod = () => {
        setReconcilingCod(true);
        setResolutionMessage(null);
        setTimeout(() => {
            setReconcilingCod(false);
            if (codMismatchCount > 0) {
                setCodMismatchCount(prev => prev - 1);
                setResolutionMessage({
                    type: 'success',
                    text: 'Đã hoàn thành khớp đối soát COD chênh lệch với hãng vận chuyển thành công!'
                });
            }
        }, 1200);
    };

    return (
        <div className="space-y-6">
            <PageHeader
                title="Tổng quan CEO"
                description="Theo dõi doanh thu, biên lợi nhuận, hàng tồn kho và tác nghiệp sự cố theo thời gian thực."
                actions={<RealtimeBadge connected={connected} />}
            />

            {/* Top KPI Grid */}
            <DashboardKpiGrid stats={{
                ...stats,
                failed_orders: stats.failed_orders,
                shipping_mismatch: codMismatchCount
            }} />

            {/* Dashboard Tabs Header */}
            <div className="border-b border-slate-200">
                <nav className="flex space-x-6 -mb-px overflow-x-auto" aria-label="Tabs">
                    {[
                        { id: 'overview', name: 'Tổng quan', icon: LayoutDashboard },
                        { id: 'sales_marketing', name: 'Sales & Marketing', icon: Target },
                        { id: 'logistics', name: 'Kho & Vận chuyển', icon: Warehouse },
                        { id: 'financials', name: 'Tài chính & Biên lợi nhuận', icon: DollarSign },
                        { id: 'ops_center', name: 'Trung tâm Sự cố', icon: AlertTriangle, badge: alertCount },
                    ].map((tab) => {
                        const Icon = tab.icon;
                        const isActive = activeTab === tab.id;
                        return (
                            <button
                                key={tab.id}
                                onClick={() => {
                                    setActiveTab(tab.id);
                                    setResolutionMessage(null);
                                }}
                                className={`
                                    flex items-center gap-2 py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap transition-all duration-200
                                    ${isActive 
                                        ? 'border-primary text-primary font-bold' 
                                        : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'}
                                `}
                            >
                                <Icon className={`size-4 ${isActive ? 'text-primary' : 'text-slate-400'}`} />
                                <span>{tab.name}</span>
                                {tab.badge > 0 && (
                                    <span className={`ml-1.5 px-2 py-0.5 rounded-full text-xs font-bold ${
                                        isActive ? 'bg-primary text-primary-foreground' : 'bg-red-50 text-red-600 border border-red-100'
                                    }`}>
                                        {tab.badge}
                                    </span>
                                )}
                            </button>
                        );
                    })}
                </nav>
            </div>

            {/* Tab 1: Overview */}
            {activeTab === 'overview' && (
                <div className="space-y-6">
                    <div className="grid gap-4 lg:grid-cols-3">
                        <RevenueAreaChart
                            data={stats.revenue_series}
                            title="Doanh thu 7 ngày"
                            description="Doanh thu từ đơn delivered/paid"
                        />
                        <OrdersBarChart
                            data={stats.orders_series}
                            title="Đơn phát sinh 7 ngày"
                            description="Số đơn tạo mới theo ngày"
                        />
                    </div>

                    <div className="grid gap-4 lg:grid-cols-3 lg:items-stretch">
                        <RevenueAreaChart
                            data={stats.lead_series}
                            title="Lead 7 ngày"
                            description="Lead ingest theo ngày"
                            valueFormatter={(v) => formatNumber(v)}
                            yTickFormatter={(v) => String(v)}
                        />
                        <LeadSourcePieChart
                            compact
                            fillHeight
                            data={stats.lead_sources}
                            title="Nguồn lead hôm nay"
                        />
                    </div>
                </div>
            )}

            {/* Tab 2: Sales & Marketing */}
            {activeTab === 'sales_marketing' && (
                <div className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-3">
                        <Card className="shadow-sm">
                            <CardHeader className="pb-2">
                                <CardTitle className="text-xs font-medium text-slate-500 uppercase">Tổng số Lead nhận</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-slate-900">{formatNumber(stats.summary?.leads ?? 0)} lead</div>
                                <p className="text-xs text-muted-foreground mt-1">Từ các kênh landing page & ads webhook</p>
                            </CardContent>
                        </Card>
                        <Card className="shadow-sm">
                            <CardHeader className="pb-2">
                                <CardTitle className="text-xs font-medium text-slate-500 uppercase">Tỷ lệ chốt sales</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-primary">{formatPercent(stats.summary?.conversion_rate ?? 0)}</div>
                                <p className="text-xs text-muted-foreground mt-1">Tỷ lệ chốt đơn của telesale trên tổng data</p>
                            </CardContent>
                        </Card>
                        <Card className="shadow-sm bg-orange-50/10 border-orange-100">
                            <CardHeader className="pb-2">
                                <CardTitle className="text-xs font-medium text-orange-800 uppercase flex items-center gap-1">
                                    <AlertCircle className="size-3.5 text-orange-600" />
                                    Tỷ lệ Lead trùng lặp (Duplicate)
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-orange-700">{formatPercent(stats.summary?.duplicate_rate ?? 0)}</div>
                                <p className="text-xs text-orange-600/80 mt-1">Chỉ số đánh giá độ sạch của data Marketing</p>
                            </CardContent>
                        </Card>
                    </div>

                    <ConversionFunnel data={stats.funnel} />

                    <div className="grid gap-4 xl:grid-cols-2">
                        <RankingList
                            title="Top sale"
                            description="Xếp hạng theo doanh thu giao thành công"
                            rows={stats.top_sales}
                            type="sales"
                        />
                        <RankingList
                            title="Top nguồn lead / campaign"
                            description="Nguồn tạo doanh thu và đơn hàng tốt nhất"
                            rows={stats.top_sources}
                            type="sources"
                        />
                    </div>
                </div>
            )}

            {/* Tab 3: Kho & Vận chuyển */}
            {activeTab === 'logistics' && (
                <div className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-4">
                        <Card className="shadow-sm">
                            <CardHeader className="pb-2">
                                <CardTitle className="text-xs font-medium text-slate-500 uppercase">Chờ vận đơn</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-slate-900">{stats.summary?.waiting_waybill ?? 0} đơn</div>
                                <p className="text-xs text-muted-foreground mt-1">Đơn cần đẩy sang hãng vận chuyển</p>
                            </CardContent>
                        </Card>
                        <Card className="shadow-sm">
                            <CardHeader className="pb-2">
                                <CardTitle className="text-xs font-medium text-slate-500 uppercase">Đang giao</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-slate-900">{stats.summary?.delivering ?? 0} đơn</div>
                                <p className="text-xs text-muted-foreground mt-1">Hàng đã gửi, đang đi giao</p>
                            </CardContent>
                        </Card>
                        <Card className="shadow-sm bg-red-50/10 border-red-100">
                            <CardHeader className="pb-2">
                                <CardTitle className="text-xs font-medium text-red-800 uppercase flex items-center gap-1">
                                    <Truck className="size-3.5 text-red-600" />
                                    Tỷ lệ hoàn hàng (Return Rate)
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-red-700">{formatPercent(stats.summary?.return_rate ?? 0)}</div>
                                <p className="text-xs text-red-600/80 mt-1">Tỷ lệ hàng hoàn/tổng đơn gửi đi</p>
                            </CardContent>
                        </Card>
                        <Card className="shadow-sm">
                            <CardHeader className="pb-2">
                                <CardTitle className="text-xs font-medium text-slate-500 uppercase">Cảnh báo hết hàng</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-red-600 flex items-center gap-1.5">
                                    <AlertCircle className="size-5" />
                                    <span>{stats.inventory_alerts?.length ?? 0} sản phẩm</span>
                                </div>
                                <p className="text-xs text-muted-foreground mt-1">Tồn kho dưới định mức (10 sp)</p>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Carrier Performance Matrix */}
                    <Card className="shadow-sm py-0 gap-0">
                        <CardHeader className="p-4 border-b rounded-t-xl">
                            <CardTitle className="text-sm font-bold flex items-center gap-2 text-slate-800">
                                <Truck className="size-4 text-violet-600" />
                                Hiệu quả xử lý của các hãng vận chuyển (Carrier Performance Matrix)
                            </CardTitle>
                            <CardDescription>
                                Đối chiếu hiệu suất giao hàng thành công và tỷ lệ hàng hoàn giữa các đơn vị vận chuyển đối tác.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm border-collapse text-left">
                                    <thead>
                                        <tr className="bg-slate-50 border-b text-slate-500 font-medium text-xs">
                                            <th className="p-3">Đơn vị vận chuyển</th>
                                            <th className="p-3 text-right">Tổng đơn bàn giao</th>
                                            <th className="p-3 text-right">Tỷ lệ giao thành công</th>
                                            <th className="p-3 text-right">Tỷ lệ hoàn về</th>
                                            <th className="p-3 text-center">Đánh giá hiệu suất</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {stats.carrier_performance?.length > 0 ? (
                                            stats.carrier_performance.map((item, idx) => (
                                                <tr key={idx} className="border-b hover:bg-muted/10">
                                                    <td className="p-3 font-semibold text-slate-900">{item.carrier}</td>
                                                    <td className="p-3 text-right font-mono">{formatNumber(item.total)} đơn</td>
                                                    <td className="p-3 text-right font-mono text-emerald-600 font-semibold">{formatPercent(item.success_rate)}</td>
                                                    <td className="p-3 text-right font-mono text-red-500 font-semibold">{formatPercent(item.return_rate)}</td>
                                                    <td className="p-3 text-center">
                                                        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${
                                                            item.success_rate >= 80 
                                                                ? 'bg-emerald-100 text-emerald-800' 
                                                                : item.success_rate >= 60 
                                                                ? 'bg-yellow-100 text-yellow-800' 
                                                                : 'bg-red-100 text-red-800'
                                                        }`}>
                                                            {item.success_rate >= 80 ? 'Xuất sắc' : item.success_rate >= 60 ? 'Trung bình' : 'Yếu kém'}
                                                        </span>
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={5} className="p-8 text-center text-slate-500">Chưa có dữ liệu bàn giao hãng</td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm py-0 gap-0">
                        <CardHeader className="p-4 border-b rounded-t-xl">
                            <CardTitle className="text-sm font-bold flex items-center gap-2 text-slate-800">
                                <Warehouse className="size-4 text-violet-600" />
                                Cảnh báo tồn kho cực hạn
                            </CardTitle>
                            <CardDescription>Cần nhập thêm hàng để tránh gián đoạn telesale chốt đơn.</CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm border-collapse text-left">
                                    <thead>
                                        <tr className="bg-slate-50 border-b text-slate-500 font-medium text-xs">
                                            <th className="p-3">Sản phẩm</th>
                                            <th className="p-3">Kho chứa</th>
                                            <th className="p-3 text-right">Số lượng còn lại</th>
                                            <th className="p-3 text-center">Trạng thái</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {stats.inventory_alerts?.length > 0 ? (
                                            stats.inventory_alerts.map((item, idx) => (
                                                <tr key={idx} className="border-b hover:bg-muted/10">
                                                    <td className="p-3 font-medium text-slate-900">{item.product}</td>
                                                    <td className="p-3 text-slate-600">{item.warehouse}</td>
                                                    <td className="p-3 text-right font-mono font-semibold text-red-600">{item.stock} chiếc</td>
                                                    <td className="p-3 text-center">
                                                        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                            Sắp hết hàng
                                                        </span>
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={4} className="p-8 text-center text-slate-500">Kho hàng ở mức an toàn</td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            )}

            {/* Tab 4: Tài chính & Biên lợi nhuận */}
            {activeTab === 'financials' && (
                <div className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-3">
                        <Card className="shadow-sm">
                            <CardHeader className="pb-2">
                                <CardTitle className="text-xs font-medium text-slate-500 uppercase">Doanh thu gộp</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-slate-900">{formatCurrency(stats.summary?.revenue ?? 0)}</div>
                                <p className="text-xs text-muted-foreground mt-1">Từ đơn giao thành công & đã thanh toán</p>
                            </CardContent>
                        </Card>
                        <Card className="shadow-sm bg-emerald-50/20 border-emerald-100">
                            <CardHeader className="pb-2">
                                <CardTitle className="text-xs font-medium text-emerald-800 uppercase flex items-center gap-1">
                                    <Coins className="size-3.5 text-emerald-600" />
                                    Lợi nhuận gộp thực tế
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-emerald-700">{formatCurrency(stats.summary?.profit ?? 0)}</div>
                                <p className="text-xs text-emerald-600/80 mt-1">Đã khấu trừ giá vốn và chi phí ship</p>
                            </CardContent>
                        </Card>
                        <Card className="shadow-sm">
                            <CardHeader className="pb-2">
                                <CardTitle className="text-xs font-medium text-slate-500 uppercase">Tỷ suất lợi nhuận</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-primary flex items-center gap-1">
                                    <Percent className="size-5" />
                                    <span>{stats.summary?.profit_margin ?? 0}%</span>
                                </div>
                                <p className="text-xs text-muted-foreground mt-1">Biên lợi nhuận trên doanh số</p>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        {/* Financial Area Chart */}
                        <RevenueAreaChart
                            data={stats.revenue_series}
                            title="Doanh thu tích lũy"
                            description="Biểu diễn sự tăng trưởng doanh số theo chu kỳ lọc ngày."
                        />

                        {/* Mismatch & Recon list */}
                        <Card className="shadow-sm py-0 gap-0">
                            <CardHeader className="p-4 border-b rounded-t-xl">
                                <CardTitle className="text-sm font-bold text-slate-800">
                                    Thống kê đối soát kế toán
                                </CardTitle>
                                <CardDescription>Các đơn hàng có độ lệch thông số cần đối soát kế toán tài chính.</CardDescription>
                            </CardHeader>
                            <CardContent className="p-4 space-y-4">
                                <div className="flex justify-between items-center p-3 rounded-lg bg-slate-50 border">
                                    <div>
                                        <div className="text-sm font-semibold text-slate-900">Đơn hàng chờ đối soát (Reconciliation)</div>
                                        <div className="text-xs text-slate-500 mt-0.5">Yêu cầu xác nhận COD thu về từ hãng vận chuyển</div>
                                    </div>
                                    <span className="text-lg font-bold font-mono text-slate-700 bg-white border px-2.5 py-1 rounded">
                                        {stats.reconciliation_pending ?? 0}
                                    </span>
                                </div>
                                <div className="flex justify-between items-center p-3 rounded-lg bg-amber-50/50 border border-amber-100">
                                    <div>
                                        <div className="text-sm font-semibold text-amber-900">COD đối soát hãng lệch thực nhận</div>
                                        <div className="text-xs text-amber-700/80 mt-0.5">Số tiền COD hãng báo và hệ thống lưu lệch nhau</div>
                                    </div>
                                    <span className="text-lg font-bold font-mono text-amber-700 bg-white border border-amber-200 px-2.5 py-1 rounded">
                                        {codMismatchCount}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            )}

            {/* Tab 5: Ops Center (Actionable Alerts Center) */}
            {activeTab === 'ops_center' && (
                <div className="space-y-6">
                    <Card className="shadow-sm border-red-100 py-0 gap-0">
                        <CardHeader className="p-4 border-b border-red-50 bg-red-50/20 rounded-t-xl">
                            <CardTitle className="text-base font-bold flex items-center gap-2 text-red-950">
                                <AlertTriangle className="size-5 text-red-600" />
                                Trung tâm Tác nghiệp Sự cố Khẩn cấp
                            </CardTitle>
                            <CardDescription className="text-red-900/60">
                                Admin có quyền duyệt hoặc xử lý nhanh các luồng thông tin lỗi mà không cần chuyển màn hình.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-4 space-y-6">
                            
                            {/* Resolution Feed Toast */}
                            {resolutionMessage && (
                                <div className={`p-4 rounded-lg flex items-start gap-3 ${
                                    resolutionMessage.type === 'success' ? 'bg-emerald-50 border border-emerald-100 text-emerald-800' : 'bg-red-50 border border-red-100 text-red-800'
                                }`}>
                                    <CheckCircle2 className="size-5 text-emerald-600 shrink-0 mt-0.5" />
                                    <div className="text-sm font-medium">{resolutionMessage.text}</div>
                                </div>
                            )}

                            <div className="space-y-4">
                                {/* Action 1: Webhook Lead Ingestion errors */}
                                <div className="border rounded-lg p-4 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 hover:shadow-sm transition-shadow">
                                    <div className="space-y-1">
                                        <div className="flex items-center gap-2">
                                            <span className="px-2 py-0.5 rounded text-xs font-bold bg-red-100 text-red-800">
                                                Lỗi Lead Ingest ({failedLeadsCount})
                                            </span>
                                            <span className="text-slate-400 text-xs font-mono">ladipage_webhook</span>
                                        </div>
                                        <h4 className="font-semibold text-slate-800">Webhook LadiPage đẩy lead về bị lỗi định dạng số điện thoại</h4>
                                        <p className="text-xs text-slate-500">Mã lỗi: PHONE_FORMAT_INVALID. Xảy ra lúc 23:45 hôm nay.</p>
                                    </div>
                                    <div className="shrink-0">
                                        <Button 
                                            disabled={retryingLead || failedLeadsCount === 0} 
                                            onClick={handleRetryLeads} 
                                            className="gap-1.5"
                                        >
                                            <RefreshCw className={`size-3.5 ${retryingLead ? 'animate-spin' : ''}`} />
                                            {retryingLead ? 'Đang gửi lại...' : failedLeadsCount === 0 ? 'Đã hoàn thành' : 'Retry Gửi Lại Webhook'}
                                        </Button>
                                    </div>
                                </div>

                                {/* Action 2: COD Mismatches */}
                                <div className="border rounded-lg p-4 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 hover:shadow-sm transition-shadow">
                                    <div className="space-y-1">
                                        <div className="flex items-center gap-2">
                                            <span className="px-2 py-0.5 rounded text-xs font-bold bg-amber-100 text-amber-800">
                                                Lệch COD Đối Soát ({codMismatchCount})
                                            </span>
                                            <span className="text-slate-400 text-xs font-mono">ghtk_webhook</span>
                                        </div>
                                        <h4 className="font-semibold text-slate-800">Tiền COD hãng GHTK báo thu lệch với dữ liệu đơn hàng</h4>
                                        <p className="text-xs text-slate-500">Đơn hàng #DH-SP292-092. Thu chênh lệch: +23,000đ.</p>
                                    </div>
                                    <div className="shrink-0">
                                        <Button 
                                            variant="secondary"
                                            disabled={reconcilingCod || codMismatchCount === 0} 
                                            onClick={handleReconcileCod} 
                                            className="gap-1.5"
                                        >
                                            <CheckCircle2 className="size-3.5 text-emerald-600" />
                                            {reconcilingCod ? 'Đang duyệt đối soát...' : codMismatchCount === 0 ? 'Đã Khớp' : 'Duyệt Khớp COD'}
                                        </Button>
                                    </div>
                                </div>

                                {/* Action 3: Warehouse Low stock alert */}
                                <div className="border rounded-lg p-4 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 hover:shadow-sm transition-shadow">
                                    <div className="space-y-1">
                                        <div className="flex items-center gap-2">
                                            <span className="px-2 py-0.5 rounded text-xs font-bold bg-violet-100 text-violet-800">
                                                Tồn kho cực hạn
                                            </span>
                                            <span className="text-slate-400 text-xs font-mono">warehouse_safety</span>
                                        </div>
                                        <h4 className="font-semibold text-slate-800">Sản phẩm "Gối mây đan (SP292627)" chỉ còn 5 chiếc tại Kho Hòa Bình</h4>
                                        <p className="text-xs text-slate-500">Tốc độ chốt trung bình 8 chiếc/ngày. Hết hàng dự kiến: Trong hôm nay.</p>
                                    </div>
                                    <div className="shrink-0">
                                        <Button variant="outline" asChild>
                                            <a href="/admin/warehouse/operations" className="flex items-center gap-1.5">
                                                <span>Nhập hàng ngay</span>
                                                <ArrowUpRight className="size-3.5" />
                                            </a>
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            )}
        </div>
    );
}

export default function Dashboard({ stats: initialStats }) {
    return (
        <RoleDashboardShell role="admin" title="Tổng quan CEO">
            <AdminDashboardContent stats={initialStats} />
        </RoleDashboardShell>
    );
}
