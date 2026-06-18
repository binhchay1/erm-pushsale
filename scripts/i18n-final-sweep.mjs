#!/usr/bin/env node
/**
 * Final i18n sweep: replace remaining Vietnamese UI strings with t() calls.
 */
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve('resources/js');

const REPLACEMENTS = [
    ["'Chưa có dữ liệu trong khoảng thời gian đã chọn.'", "t('reports.empty_period')"],
    ["'Không có dữ liệu trong kỳ đã chọn'", "t('pages.empty_period')"],
    ["'Chưa có doanh số chốt trong kỳ / bộ lọc hiện tại.'", "t('rankings.empty_period')"],
    ["'Không có dữ liệu'", "t('pages.empty_data')"],
    ["'Tổng cộng'", "t('common.grand_total')"],
    ["'STT'", "t('pages.stt')"],
    ["'— Tất cả —'", "t('common.select_all')"],
    ["'Xuất CSV'", "t('common.export_csv')"],
    ["'Kiểm thử thành công.'", "t('integrations.test_success')"],
    ["'Không copy được'", "t('common.copy_failed')"],
    ["'Đã copy URL'", "t('common.copied')"],
    ["'Đã copy đường dẫn nhận lead'", "t('pages.campaigns.copy_lead_url')"],
    ["placeholder ?? '— Tất cả —'", "placeholder ?? t('common.select_all')"],
    ["label = 'Xuất CSV'", "label"],
    ["title = 'Doanh thu 7 ngày'", "title"],
    ["title = 'Nguồn lead hôm nay'", "title"],
    ["(v) => `${Math.round(v / 1_000_000)}tr`", "(v) => formatCurrencyCompact(v)"],
];

const FILES = [
    'components/ui/confirm-dialog.jsx',
    'components/filters/SelectFilter.jsx',
    'components/reports/ReportExportButton.jsx',
    'components/charts/RevenueAreaChart.jsx',
    'components/charts/LeadSourcePieChart.jsx',
    'components/settings/ThemeSettings.jsx',
    'components/shipping/CarrierApiTestPanel.jsx',
    'components/rankings/RevenueRankingChart.jsx',
    'components/operations/OperationOrderTable.jsx',
    'components/operations/OperationStatusDialog.jsx',
    'components/operations/WarehouseOrderTable.jsx',
    'components/operations/OperationCallButton.jsx',
    'components/org/OrgChartBoard.jsx',
    'components/org/OrgStructureCard.jsx',
    'components/org/DepartmentTree.jsx',
    'components/reports/RevenueMetricsTable.jsx',
    'components/reports/SalesPerformanceTable.jsx',
    'components/reports/MarketingCampaignTable.jsx',
    'components/shipping/ShippingPartnerCard.jsx',
    'components/shipping/ShippingFeeResult.jsx',
    'components/shipping/ShippingOrderDetailModal.jsx',
    'pages/Sales/Workspace.jsx',
    'pages/Sales/CustomerProfile.jsx',
    'pages/Reports/ExtraReport.jsx',
    'pages/Admin/Integrations/Index.jsx',
    'pages/Admin/Marketing/CampaignReport.jsx',
    'pages/Admin/Marketing/RevenueReport.jsx',
    'pages/Admin/Marketing/Campaigns/Index.jsx',
    'pages/Admin/Marketing/Campaigns/Form.jsx',
    'pages/Admin/Sales/PerformanceReport.jsx',
    'pages/Admin/Sales/RevenueReport.jsx',
    'pages/Admin/Reports/BusinessOverview.jsx',
    'pages/Admin/Reports/CeoReport.jsx',
    'pages/Admin/Shipping/Orders.jsx',
    'pages/Admin/Shipping/Reconciliation.jsx',
    'pages/Admin/ShippingPartners/Index.jsx',
    'pages/Admin/Warehouse/Operations.jsx',
    'pages/Admin/Warehouse/Inventory.jsx',
    'pages/Admin/Warehouse/MovementHistory.jsx',
    'pages/Admin/Warehouse/Index.jsx',
    'pages/Admin/Warehouse/Form.jsx',
    'pages/Admin/Warehouse/Show.jsx',
    'pages/Admin/Products/Index.jsx',
    'pages/Admin/Products/Form.jsx',
    'pages/Admin/Users/Index.jsx',
    'pages/Admin/Users/Form.jsx',
    'pages/Admin/Teams/Index.jsx',
    'pages/Admin/Teams/Form.jsx',
    'pages/Admin/Leads/Index.jsx',
    'pages/Admin/Orders/FailedOrders.jsx',
    'pages/Admin/Landing/Approvals.jsx',
    'pages/Admin/Accounting/Operations.jsx',
    'pages/Marketing/Campaigns/Index.jsx',
    'pages/Marketing/Campaigns/Form.jsx',
    'hooks/use-labels.js',
    'hooks/useRealtimeDashboard.js',
    'hooks/useRealtimeNotifications.js',
    'hooks/useFlashToast.js',
    'components/ErrorBoundary.jsx',
    'components/errors/ErrorShell.jsx',
    'components/layout/PageInfoButton.jsx',
    'lib/themes.js',
];

function ensureImports(content) {
    let updated = content;
    const needsUseT = updated.includes('t(') && !updated.includes("from '@/providers/I18nProvider'");
    const needsFormatCompact = updated.includes('formatCurrencyCompact') && !updated.includes("from '@/lib/format'");

    if (needsUseT) {
        const lastImport = [...updated.matchAll(/^import .+;\n/gm)].pop();
        if (lastImport) {
            const pos = lastImport.index + lastImport[0].length;
            updated = `${updated.slice(0, pos)}import { useT } from '@/providers/I18nProvider';\n${updated.slice(pos)}`;
        }
    }

    if (needsFormatCompact && !updated.match(/import \{[^}]*formatCurrencyCompact/)) {
        updated = updated.replace(
            /import \{([^}]+)\} from '@\/lib\/format';/,
            (m, imports) => {
                if (imports.includes('formatCurrencyCompact')) return m;
                return `import {${imports.trim()}, formatCurrencyCompact } from '@/lib/format';`;
            },
        );
    }

    return updated;
}

function ensureUseTInFunctions(content) {
    if (!content.includes('t(') || !content.includes('useT')) {
        return content;
    }

    return content.replace(
        /(export (?:default )?function \w+\([^)]*\)\s*\{)\n(?!\s*const t = useT\(\))/g,
        '$1\n    const t = useT();\n',
    );
}

let changed = 0;
for (const rel of FILES) {
    const filePath = path.join(ROOT, rel);
    if (!fs.existsSync(filePath)) {
        console.warn('Skip missing:', rel);
        continue;
    }

    let content = fs.readFileSync(filePath, 'utf8');
    const original = content;

    for (const [from, to] of REPLACEMENTS) {
        content = content.split(from).join(to);
    }

    // English comments for hooks
    content = content.replace(/\/\*\*[\s\S]*?Nhãn enum[\s\S]*?\*\//, '/** Enum labels from server (localized). */');
    content = content.replace(/\/\*\*[\s\S]*?Lắng nghe WebSocket[\s\S]*?\*\//, '/** Listen for WebSocket dashboard stat updates. */');
    content = content.replace(/\/\/ toast\.info\('Số liệu vừa cập nhật'[\s\S]*?\n/, '');
    content = content.replace(/\/\*\* Sắp tăng dần doanh số[\s\S]*?\*\//, '/** Ascending revenue: lower rank left, #1 right. */');
    content = content.replace(/\/\/ Chip màu cho cột %[\s\S]*?\n/, '');

    if (rel.endsWith('.jsx') && content.includes('t(')) {
        content = ensureImports(content);
        content = ensureUseTInFunctions(content);
    }

    if (content !== original) {
        fs.writeFileSync(filePath, content);
        changed++;
        console.log('Updated:', rel);
    }
}

console.log(`Done. ${changed} files updated.`);
