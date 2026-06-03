import { Head } from '@inertiajs/react';
import { 
    BarChart, 
    Bar, 
    XAxis, 
    YAxis, 
    CartesianGrid, 
    Tooltip, 
    Legend, 
    ResponsiveContainer,
    Cell
} from 'recharts';
import { 
    TrendingUp, 
    Users, 
    BadgeCheck, 
    Percent, 
    Target,
    BarChart3,
    PieChart,
    ArrowUpRight,
    DollarSign,
    Briefcase
} from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { StatusSummaryBar } from '@/components/reports/StatusSummaryBar';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';

export default function CeoReport({ filters, filterOptions, report }) {
    // Process Sales chart data (Top 8 sales by revenue)
    const salesChartData = (report.saleRows || [])
        .map(s => ({
            name: s.saleStaffName,
            revenue: Number(s.totalEstRevenue || 0),
            kpi: Number(s.salesKpi || 0),
            achievement: Number(s.achievementRate || 0)
        }))
        .sort((a, b) => b.revenue - a.revenue)
        .slice(0, 8);

    // Process Marketing chart data
    const marketingChartData = (report.marketingRows || [])
        .map(m => ({
            name: m.marketerName,
            budget: Number(m.budget || 0),
            contactPrice: Number(m.contactPrice || 0)
        }))
        .slice(0, 8);

    // Compute aggregate figures
    const totalSalesRevenue = (report.saleRows || []).reduce((sum, r) => sum + Number(r.totalEstRevenue || 0), 0);
    const avgCloseRate = (report.saleRows || []).length 
        ? (report.saleRows || []).reduce((sum, r) => sum + Number(r.newCloseRate || 0), 0) / (report.saleRows || []).length 
        : 0;
    const totalMarketingBudget = (report.marketingRows || []).reduce((sum, r) => sum + Number(r.budget || 0), 0);

    return (
        <AppLayout>
            <Head title="Báo cáo điều hành CEO" />

            <div className="space-y-6">
                {/* Header section with styling */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
                            <Briefcase className="size-6 text-primary" />
                            <span>Báo cáo điều hành CEO</span>
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Xem tổng quan hiệu suất vận hành chốt đơn của Sales và hiệu quả chiến dịch Marketing.
                        </p>
                    </div>
                </div>

                {/* Filter bar */}
                <ReportFilterBar
                    routeUrl="/admin/reports/ceo"
                    filters={filters}
                    filterOptions={filterOptions}
                />

                {/* Top Metrics Grid */}
                <div className="grid gap-4 sm:grid-cols-3">
                    <Card className="shadow-sm border-slate-100">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div className="space-y-1">
                                <span className="text-xs font-medium text-slate-500 uppercase tracking-wider">Tổng Doanh Số Tạm Tính</span>
                                <h3 className="text-xl font-bold text-slate-900">{formatCurrency(totalSalesRevenue)}</h3>
                            </div>
                            <div className="p-3 bg-emerald-50 text-emerald-600 rounded-lg">
                                <TrendingUp className="size-5" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="shadow-sm border-slate-100">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div className="space-y-1">
                                <span className="text-xs font-medium text-slate-500 uppercase tracking-wider">Tỷ Lệ Chốt Khách Mới TB</span>
                                <h3 className="text-xl font-bold text-slate-900">{formatPercent(avgCloseRate)}</h3>
                            </div>
                            <div className="p-3 bg-blue-50 text-blue-600 rounded-lg">
                                <Percent className="size-5" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="shadow-sm border-slate-100">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div className="space-y-1">
                                <span className="text-xs font-medium text-slate-500 uppercase tracking-wider">Ngân Sách Marketing Đã Chi</span>
                                <h3 className="text-xl font-bold text-slate-900">{formatCurrency(totalMarketingBudget)}</h3>
                            </div>
                            <div className="p-3 bg-violet-50 text-violet-600 rounded-lg">
                                <DollarSign className="size-5" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Status Summary */}
                <div className="space-y-3">
                    <div className="text-sm font-semibold text-slate-700 uppercase tracking-wider">Trạng thái đơn hàng</div>
                    <StatusSummaryBar summary={report.statusSummary} />
                </div>

                {/* Interactive Charts section */}
                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Sales Performance Chart */}
                    <Card className="shadow-sm">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-bold flex items-center gap-1.5 text-slate-800">
                                <BarChart3 className="size-4 text-primary" />
                                Doanh thu thực tế vs Chỉ tiêu KPI (Top Sales)
                            </CardTitle>
                            <CardDescription>So sánh doanh số tạm tính đạt được so với định mức KPI ngày/tháng.</CardDescription>
                        </CardHeader>
                        <CardContent className="h-[280px]">
                            {salesChartData.length > 0 ? (
                                <ResponsiveContainer width="100%" height="100%">
                                    <BarChart
                                        data={salesChartData}
                                        margin={{ top: 10, right: 10, left: -20, bottom: 0 }}
                                    >
                                        <CartesianGrid strokeDasharray="3 3" vertical={false} />
                                        <XAxis dataKey="name" tick={{ fontSize: 10 }} />
                                        <YAxis tickFormatter={(v) => `${v/1000000}M`} tick={{ fontSize: 10 }} />
                                        <Tooltip 
                                            formatter={(value, name) => [formatCurrency(value), name === 'revenue' ? 'Doanh thu đạt' : 'Chỉ tiêu KPI']}
                                        />
                                        <Legend wrapperStyle={{ fontSize: 11 }} />
                                        <Bar dataKey="revenue" fill="var(--color-primary, #3b82f6)" name="Doanh thu" radius={[4, 4, 0, 0]} />
                                        <Bar dataKey="kpi" fill="#cbd5e1" name="Chỉ tiêu KPI" radius={[4, 4, 0, 0]} />
                                    </BarChart>
                                </ResponsiveContainer>
                            ) : (
                                <div className="h-full flex items-center justify-center text-xs text-muted-foreground">Không có dữ liệu biểu đồ</div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Marketing Performance Chart */}
                    <Card className="shadow-sm">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-bold flex items-center gap-1.5 text-slate-800">
                                <PieChart className="size-4 text-violet-600" />
                                Ngân sách & Giá Contact của Marketer
                            </CardTitle>
                            <CardDescription>Chi phí ngân sách đã chi so với giá trị trung bình trên một contact.</CardDescription>
                        </CardHeader>
                        <CardContent className="h-[280px]">
                            {marketingChartData.length > 0 ? (
                                <ResponsiveContainer width="100%" height="100%">
                                    <BarChart
                                        data={marketingChartData}
                                        margin={{ top: 10, right: 10, left: -10, bottom: 0 }}
                                    >
                                        <CartesianGrid strokeDasharray="3 3" vertical={false} />
                                        <XAxis dataKey="name" tick={{ fontSize: 10 }} />
                                        <YAxis yAxisId="left" orientation="left" tickFormatter={(v) => `${v/1000000}M`} tick={{ fontSize: 10 }} />
                                        <YAxis yAxisId="right" orientation="right" tickFormatter={(v) => `${v/1000}k`} tick={{ fontSize: 10 }} />
                                        <Tooltip 
                                            formatter={(value, name) => [formatCurrency(value), name === 'budget' ? 'Ngân sách tiêu' : 'Giá / Contact']}
                                        />
                                        <Legend wrapperStyle={{ fontSize: 11 }} />
                                        <Bar yAxisId="left" dataKey="budget" fill="#8b5cf6" name="Ngân sách (L)" radius={[4, 4, 0, 0]} />
                                        <Bar yAxisId="right" dataKey="contactPrice" fill="#f59e0b" name="Giá Contact (R)" radius={[4, 4, 0, 0]} />
                                    </BarChart>
                                </ResponsiveContainer>
                            ) : (
                                <div className="h-full flex items-center justify-center text-xs text-muted-foreground">Không có dữ liệu biểu đồ</div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Sales Performance Table Section */}
                <Card className="shadow-sm">
                    <CardHeader className="pb-0 border-b border-slate-100 bg-slate-50/50 p-4">
                        <CardTitle className="text-base font-bold flex items-center gap-1.5 text-slate-800">
                            <Users className="size-4 text-emerald-600" />
                            Bảng Hiệu suất Sale
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <ScrollDataTable>
                            <table className="min-w-[1200px] w-full border-collapse">
                                <thead>
                                    <tr className="bg-slate-100/70 border-b">
                                        <Th className="w-[50px]">STT</Th>
                                        <Th className="min-w-[180px]">Nhân viên Telesale</Th>
                                        <Th colSpan={5} className="text-center bg-blue-50/30 border-r border-l">Khách Hàng Mới</Th>
                                        <Th colSpan={5} className="text-center bg-amber-50/30 border-r">Khách Hàng Cũ</Th>
                                        <Th className="font-semibold text-right">Tổng Doanh Số</Th>
                                        <Th className="text-right">KPI</Th>
                                        <Th className="text-center">% Đạt KPI</Th>
                                    </tr>
                                    <tr className="bg-slate-50 text-xs border-b">
                                        <Th />
                                        <Th />
                                        <Th className="text-right bg-blue-50/10 font-normal">Tiếp xúc</Th>
                                        <Th className="text-right bg-blue-50/10 font-normal">Chốt</Th>
                                        <Th className="text-right bg-blue-50/10 font-normal">% Chốt</Th>
                                        <Th className="text-right bg-blue-50/10 font-normal">S.Phẩm</Th>
                                        <Th className="text-right bg-blue-50/10 font-normal border-r">Doanh số</Th>
                                        <Th className="text-right bg-amber-50/10 font-normal">Tiếp xúc</Th>
                                        <Th className="text-right bg-amber-50/10 font-normal">Chốt</Th>
                                        <Th className="text-right bg-amber-50/10 font-normal">% Chốt</Th>
                                        <Th className="text-right bg-amber-50/10 font-normal">S.Phẩm</Th>
                                        <Th className="text-right bg-amber-50/10 font-normal border-r">Doanh số</Th>
                                        <Th />
                                        <Th />
                                        <Th />
                                    </tr>
                                </thead>
                                <tbody>
                                    {report.saleRows?.length ? (
                                        report.saleRows.map((r) => (
                                            <tr key={r.saleStaffId} className="hover:bg-muted/30 border-b text-sm transition-colors">
                                                <Td className="text-center">{r.stt}</Td>
                                                <Td className="font-medium text-slate-900">
                                                    {r.saleStaffName}
                                                    <span className="text-xs text-muted-foreground block font-normal font-mono"> {r.saleUsername}</span>
                                                </Td>
                                                <Td className="text-right font-mono">{formatNumber(r.newContact)}</Td>
                                                <Td className="text-right font-mono font-semibold text-primary">{formatNumber(r.newClosed)}</Td>
                                                <Td className="text-right font-mono text-emerald-600 font-medium">{formatPercent(r.newCloseRate)}</Td>
                                                <Td className="text-right font-mono">{formatNumber(r.newProductQty)}</Td>
                                                <Td className="text-right font-mono border-r">{formatCurrency(r.newEstRevenue)}</Td>
                                                <Td className="text-right font-mono">{formatNumber(r.oldContact)}</Td>
                                                <Td className="text-right font-mono font-semibold text-primary">{formatNumber(r.oldClosed)}</Td>
                                                <Td className="text-right font-mono text-emerald-600 font-medium">{formatPercent(r.oldCloseRate)}</Td>
                                                <Td className="text-right font-mono">{formatNumber(r.oldProductQty)}</Td>
                                                <Td className="text-right font-mono border-r">{formatCurrency(r.oldEstRevenue)}</Td>
                                                <Td className="text-right font-semibold font-mono text-emerald-700 bg-emerald-50/10">{formatCurrency(r.totalEstRevenue)}</Td>
                                                <Td className="text-right font-mono text-muted-foreground">{formatCurrency(r.salesKpi)}</Td>
                                                <Td className="text-center">
                                                    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ${
                                                        Number(r.achievementRate) >= 100 
                                                            ? 'bg-emerald-100 text-emerald-800' 
                                                            : Number(r.achievementRate) >= 50 
                                                            ? 'bg-blue-100 text-blue-800' 
                                                            : 'bg-amber-100 text-amber-800'
                                                    }`}>
                                                        {formatPercent(r.achievementRate)}
                                                    </span>
                                                </Td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <Td colSpan={15} className="py-8 text-center text-muted-foreground">
                                                Không có dữ liệu sale
                                            </Td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </ScrollDataTable>
                    </CardContent>
                </Card>

                {/* Marketing Performance Table Section */}
                <Card className="shadow-sm">
                    <CardHeader className="pb-0 border-b border-slate-100 bg-slate-50/50 p-4">
                        <CardTitle className="text-base font-bold flex items-center gap-1.5 text-slate-800">
                            <BadgeCheck className="size-4 text-violet-600" />
                            Bảng Hiệu suất Marketing
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <ScrollDataTable>
                            <table className="w-full min-w-[640px] border-collapse">
                                <thead>
                                    <tr className="bg-slate-100/70 border-b">
                                        <Th className="w-[50px] text-center">STT</Th>
                                        <Th>Nhân viên Marketing</Th>
                                        <Th className="text-right">Ngân sách chi</Th>
                                        <Th className="text-right">Giá trên một contact</Th>
                                        <Th className="text-right">% Ngân sách / DS Khách mới</Th>
                                        <Th className="text-right">% Ngân sách / DS Tổng</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {report.marketingRows?.length ? (
                                        report.marketingRows.map((r) => (
                                            <tr key={r.marketerId} className="hover:bg-muted/30 border-b text-sm transition-colors">
                                                <Td className="text-center">{r.stt}</Td>
                                                <Td className="font-medium text-slate-900">{r.marketerName}</Td>
                                                <Td className="text-right font-mono font-semibold">{formatCurrency(r.budget)}</Td>
                                                <Td className="text-right font-mono text-amber-600 font-medium">{formatCurrency(r.contactPrice)}</Td>
                                                <Td className="text-right font-mono">{formatPercent(r.budgetRevenueRatioNew)}</Td>
                                                <Td className="text-right font-mono">{formatPercent(r.budgetRevenueRatioTotal)}</Td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <Td colSpan={6} className="py-8 text-center text-muted-foreground">
                                                Không có dữ liệu marketing
                                            </Td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </ScrollDataTable>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
