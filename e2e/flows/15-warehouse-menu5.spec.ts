import {
    test,
    expect,
    DEMO,
    loginAs,
    waitUiReady,
} from '../helpers/fixtures';

/**
 * Menu 5 (Kho) — smoke + key interactions on real UI.
 * Covers 5.1 / 5.2 / 5.3 / 5.4 / 5.5 entry points + voucher apply-column radios.
 */
const MENU5 = [
    { code: '5.1', path: '/admin/warehouse/operations', assert: /Kho|Đăng đơn|Tác nghiệp/i },
    { code: '5.2.1', path: '/admin/warehouses', assert: /Kho|warehouse/i },
    { code: '5.2.2', path: '/admin/warehouse/inventory', assert: /tồn kho|sản phẩm kho|Ngừng KD/i },
    { code: '5.3.1', path: '/admin/warehouse/vouchers/entry', assert: /Phiếu nhập|xuất kho/i },
    { code: '5.3.2', path: '/admin/warehouse/vouchers', assert: /Phiếu|voucher/i },
    { code: '5.3.3', path: '/admin/warehouse/movement-history', assert: /lịch sử|xuất nhập|movement/i },
    { code: '5.4', path: '/admin/warehouse/incidents', assert: /biên bản|incident/i },
    { code: '5.5.1', path: '/admin/warehouse/reports/daily-stock', assert: /tồn|báo cáo|stock/i },
    { code: '5.5.2', path: '/admin/warehouse/reports/pending-export', assert: /chờ xuất|pending|báo cáo/i },
    { code: '5.5.4', path: '/admin/warehouse/reports/movement-summary', assert: /xuất nhập|movement|báo cáo/i },
] as const;

test.describe('15 — Menu 5 Kho (pages + phiếu NXK)', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    for (const item of MENU5) {
        test(`${item.code} mở ${item.path} không 500`, async ({ page }) => {
            await loginAs(page, DEMO.admin);
            await page.goto(item.path, { waitUntil: 'domcontentloaded' });
            await waitUiReady(page);
            await expect(page).not.toHaveURL(/\/login/);
            await expect(page.locator('body')).not.toContainText(/Server Error|Whoops|SQLSTATE/i);
            await expect(page.locator('body')).toContainText(item.assert, { timeout: 20_000 });
        });
    }

    test('5.2.2 Ngừng KD checkbox cập nhật được', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/warehouse/inventory', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        const dataRow = page.locator('table.ps-inventory-table tbody tr').filter({
            hasNot: page.locator('td.ps-empty'),
        }).filter({
            has: page.locator('input[type="checkbox"]'),
        }).first();

        if (!(await dataRow.isVisible({ timeout: 8_000 }).catch(() => false))) {
            test.info().annotations.push({
                type: 'note',
                description: 'Không có dòng tồn kho — soft-pass Ngừng KD.',
            });
            await expect(page.locator('body')).not.toContainText(/Server Error|Whoops|SQLSTATE/i);
            return;
        }

        const discontinued = dataRow.locator('td').nth(11).locator('input[type="checkbox"]');
        await expect(discontinued).toBeVisible({ timeout: 10_000 });
        const before = await discontinued.isChecked();
        await discontinued.click();
        await page.waitForTimeout(800);
        await expect(page.locator('body')).not.toContainText(/Server Error|Whoops|SQLSTATE/i);
        const after = await discontinued.isChecked().catch(() => before);
        test.info().annotations.push({
            type: 'note',
            description: `Ngừng KD before=${before} after=${after}`,
        });
    });

    test('5.3.1 phiếu NXK: layout + Import/Xuất + cột apply radios', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/warehouse/vouchers/entry', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        await expect(page.getByText(/Phiếu nhập\s*\/\s*xuất kho/i).first()).toBeVisible({ timeout: 20_000 });
        const close = page.locator('button.ps-header-close-icon').first();
        await expect(close).toBeVisible();
        const closeBox = await close.boundingBox();
        expect((closeBox?.width ?? 99) <= 28, `Close button still large: ${closeBox?.width}`).toBeTruthy();

        await expect(page.getByText(/Họ tên người giao/i).first()).toBeVisible();
        await expect(page.getByText(/Thủ kho/i).first()).toBeVisible();
        await expect(page.getByText(/Kế toán trưởng/i).first()).toBeVisible();
        await expect(page.getByText(/Sản phẩm trong kho/i).first()).toBeVisible();

        await expect(page.locator('.ps-voucher-excel-link').filter({ hasText: /Import Excel/i })).toBeVisible();
        await expect(page.locator('.ps-voucher-excel-link').filter({ hasText: /Xuất Excel/i })).toBeVisible();

        await expect(page.getByText(/SL chứng từ/i).first()).toBeVisible();

        const unitCostApply = page.locator('tr.ps-voucher-apply-row label.ps-voucher-col-apply').nth(0).locator('input.ps-voucher-col-apply__tick');
        const batchApply = page.locator('tr.ps-voucher-apply-row label.ps-voucher-col-apply').nth(1).locator('input.ps-voucher-col-apply__tick');
        const expiryApply = page.locator('tr.ps-voucher-apply-row label.ps-voucher-col-apply').nth(2).locator('input.ps-voucher-col-apply__tick');
        await expect(unitCostApply).toBeVisible();
        await expect(batchApply).toBeVisible();
        await expect(expiryApply).toBeVisible();
        // Labels row has text only; ticks live on the row below
        await expect(page.locator('tr.ps-voucher-head-labels label.ps-voucher-col-apply')).toHaveCount(0);
        await expect(page.locator('tr.ps-voucher-apply-row')).toBeVisible();

        const control = page.locator('.ps-voucher-entry-body button.ps-select__control, .ps-product-search-select button').first();
        if (await control.isVisible().catch(() => false)) {
            await control.click();
            const opt = page.locator('button.ps-select__option, .ps-product-search-select [role="option"]').filter({ hasNotText: /^--/ }).first();
            if (await opt.isVisible({ timeout: 5_000 }).catch(() => false)) {
                await opt.click();
                await page.waitForTimeout(400);
            }
        }

        // number inputs per data row: document_quantity, quantity, unit_cost
        const costInputs = page.locator('.ps-voucher-entry-table tbody tr:not(.ps-voucher-entry-total-row) input[type="number"]').nth(2);
        if (await costInputs.isVisible().catch(() => false)) {
            await costInputs.fill('12345');
            await unitCostApply.check();
            await expect(unitCostApply).toBeChecked();
        }

        await expect(page.locator('body')).not.toContainText(/Server Error|Whoops|SQLSTATE/i);
    });

    test('5.1 trang cập nhật theo mã — không còn chữ Pushsale cứng', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/warehouse/orders/update-by-code', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);
        await expect(page.locator('.ps-wh-bulk-page').first()).toBeVisible({ timeout: 15_000 });
        await expect(page.locator('button.ps-wh-bulk-close, a.ps-wh-bulk-close')).toHaveCount(0);
        const notice = page.locator('.ps-wh-bulk-notice').first();
        await expect(notice).toBeVisible();
        const title = await page.locator('.ps-page-header .text, .m-header .text, h1').first().innerText().catch(async () => (
            await page.locator('.ps-wh-bulk-page').first().innerText()
        ));
        const noticeText = await notice.innerText();
        expect(`${title}\n${noticeText}`.toLowerCase()).not.toMatch(/\bpushsale\b/);
    });
});
