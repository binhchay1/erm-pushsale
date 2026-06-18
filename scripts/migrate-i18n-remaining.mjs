#!/usr/bin/env node
/**
 * Batch i18n migration helper — replaces hardcoded Vietnamese UI strings with t() calls.
 * Run: node scripts/migrate-i18n-remaining.mjs
 */
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve('resources/js');

const REPLACEMENTS = [
    // Common patterns across many files
    ["'Chưa có dữ liệu trong khoảng thời gian đã chọn.'", "t('reports.empty_period')"],
    ["'Không có dữ liệu trong kỳ đã chọn'", "t('pages.empty_period')"],
    ["'Tổng cộng'", "t('common.grand_total')"],
    ["'STT'", "t('pages.stt')"],
    ["'Đã lưu'", "t('integrations.saved')"],
    ["'Đang lưu...'", "t('common.saving')"],
    ["'Đang lưu…'", "t('common.saving')"],
    ["'Lưu cấu hình'", "t('integrations.save_config')"],
    ["'Đã cấu hình'", "t('integrations.configured')"],
    ["'Chưa cấu hình'", "t('integrations.not_configured')"],
    ["'Kiểm thử thất bại.'", "t('integrations.test_failed')"],
    ["'Kiểm thử thành công.'", "t('integrations.test_success')"],
    ["'Không có dữ liệu'", "t('pages.empty_data')"],
    ["'Không in được nhãn vận đơn.'", "t('shipping.print_failed')"],
];

const FILES = [
    'components/connections/CredentialField.jsx',
    'components/connections/ConnectionTestResult.jsx',
    'components/integrations/PlatformCard.jsx',
    'components/shipping/ShippingPartnerCard.jsx',
    'components/shipping/ShippingFeeResult.jsx',
    'components/shipping/ShippingOrderDetailModal.jsx',
    'components/reports/TeamRevenueTable.jsx',
    'components/reports/TableColumnToggle.jsx',
    'components/reports/RevenueMetricsTable.jsx',
    'components/reports/SalesPerformanceTable.jsx',
    'components/reports/MarketingCampaignTable.jsx',
    'components/operations/OperationOrderTable.jsx',
    'components/operations/OperationStatusDialog.jsx',
    'components/operations/WarehouseOrderTable.jsx',
    'components/org/OrgChartBoard.jsx',
    'components/org/OrgStructureCard.jsx',
    'components/org/DepartmentTree.jsx',
    'components/rankings/RevenueRankingChart.jsx',
    'pages/Admin/Marketing/Dashboard.jsx',
    'pages/Admin/Reports/BusinessOverview.jsx',
    'pages/Admin/Sales/PerformanceReport.jsx',
    'pages/Admin/Sales/RevenueReport.jsx',
    'pages/Admin/Marketing/RevenueReport.jsx',
    'pages/Admin/Shipping/Orders.jsx',
    'pages/Admin/Shipping/Reconciliation.jsx',
    'pages/Admin/ShippingPartners/Index.jsx',
    'pages/Admin/Warehouse/Operations.jsx',
    'pages/Admin/Warehouse/Inventory.jsx',
    'pages/Admin/Warehouse/MovementHistory.jsx',
    'pages/Admin/Warehouse/Index.jsx',
    'pages/Admin/Warehouse/Form.jsx',
    'pages/Admin/Products/Index.jsx',
    'pages/Admin/Products/Form.jsx',
    'pages/Admin/Users/Form.jsx',
    'pages/Admin/Teams/Index.jsx',
    'pages/Admin/Teams/Form.jsx',
    'pages/Admin/Leads/Index.jsx',
    'pages/Admin/Orders/FailedOrders.jsx',
    'pages/Admin/Landing/Approvals.jsx',
    'pages/Admin/Marketing/Campaigns/Index.jsx',
    'pages/Admin/Marketing/Campaigns/Form.jsx',
    'pages/Marketing/Campaigns/Index.jsx',
    'pages/Marketing/Campaigns/Form.jsx',
    'pages/Sales/Workspace.jsx',
    'pages/Reports/ExtraReport.jsx',
    'lib/shipping.js',
];

function ensureUseT(content, isComponent) {
    if (content.includes('useT(') || content.includes('const t = useT')) {
        return content;
    }

    let updated = content;

    if (isComponent && !updated.includes("from '@/providers/I18nProvider'")) {
        const importMatch = updated.match(/^import .+;\n/m);
        if (importMatch) {
            const lastImport = [...updated.matchAll(/^import .+;\n/gm)].pop();
            const pos = lastImport.index + lastImport[0].length;
            updated =
                updated.slice(0, pos) +
                "import { useT } from '@/providers/I18nProvider';\n" +
                updated.slice(pos);
        }
    }

    if (isComponent) {
        // Add const t = useT() to first function component
        updated = updated.replace(
            /(export (?:default )?function \w+[^{]*\{)\n(?!\s*const t = useT)/,
            "$1\n    const t = useT();\n"
        );
        updated = updated.replace(
            /(export function \w+[^{]*\{)\n(?!\s*const t = useT)/,
            "$1\n    const t = useT();\n"
        );
    }

    return updated;
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
    const isComponent = rel.endsWith('.jsx');

    for (const [from, to] of REPLACEMENTS) {
        content = content.split(from).join(to);
    }

    if (isComponent) {
        content = ensureUseT(content, true);
    }

    if (content !== original) {
        fs.writeFileSync(filePath, content);
        changed++;
        console.log('Updated:', rel);
    }
}

console.log(`Done. ${changed} files updated.`);
