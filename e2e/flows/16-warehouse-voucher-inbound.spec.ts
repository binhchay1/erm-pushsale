import {
    test,
    expect,
    DEMO,
    loginAs,
    waitUiReady,
    expectSuccessToast,
} from '../helpers/fixtures';

/**
 * Phiếu nhập kho (5.3.1) — tương tác thật trên UI.
 * Bootstrap kho + SP nếu môi trường vừa wipe (không skip vì thiếu master data).
 */

async function ensureWarehouse(page: import('@playwright/test').Page, stamp: string) {
    await page.goto('/admin/warehouses', { waitUntil: 'domcontentloaded' });
    await waitUiReady(page);

    const existing = page.locator('table.ps-warehouse-table tbody tr').filter({
        hasNot: page.locator('td.ps-empty, .ps-empty'),
    }).first();
    if (await existing.isVisible({ timeout: 5_000 }).catch(() => false)) {
        return;
    }

    await page.locator('.ps-warehouse-toolbar button.btn-primary').filter({ hasText: 'Thêm' }).click();
    const form = page.locator('form.ps-warehouse-form');
    await expect(form).toBeVisible({ timeout: 15_000 });
    await form.locator('label').filter({ hasText: /Tên kho/i }).locator('input.form-control').fill(`Kho E2E ${stamp}`);
    await form.locator('label').filter({ hasText: /Mã kho/i }).locator('input.form-control').fill(`WH-E2E-${stamp}`);
    await form.locator('label').filter({ hasText: /^Địa chỉ/i }).locator('input.form-control').fill('12 Nguyễn Trãi');
    await form.locator('label').filter({ hasText: /Số điện thoại/i }).locator('input.form-control').fill('0901234567');

    const province = form.locator('.ps-address-select--province').first();
    await province.locator('button.ps-select__control').click();
    const provinceOpt = page.locator('button.ps-select__option').filter({ hasText: /Hà Nội/i }).first();
    if (await provinceOpt.isVisible({ timeout: 8_000 }).catch(() => false)) {
        await provinceOpt.click();
    } else {
        await page.locator('button.ps-select__option').filter({ hasNotText: /^--/ }).first().click();
    }
    await page.waitForTimeout(400);
    const wardBtn = form.locator('.ps-address-select--ward button.ps-select__control');
    if (await wardBtn.isEnabled().catch(() => false)) {
        await wardBtn.click();
        const wardOpt = page.locator('button.ps-select__option').filter({ hasNotText: /^--/ }).first();
        if (await wardOpt.isVisible({ timeout: 8_000 }).catch(() => false)) await wardOpt.click();
    }

    await form.locator('button.btn-primary').filter({ hasText: /Thêm mới/i }).click();
    await Promise.race([
        expect(page.locator('table.ps-warehouse-table')).toContainText(`Kho E2E ${stamp}`, { timeout: 30_000 }),
        expectSuccessToast(page, /Đã tạo kho/i, 30_000),
    ]);
}

async function ensureProduct(page: import('@playwright/test').Page, stamp: string) {
    await page.goto('/admin/products', { waitUntil: 'domcontentloaded' });
    await waitUiReady(page);

    const row = page.locator('table tbody tr').filter({ hasNot: page.locator('.ps-empty') }).first();
    if (await row.isVisible({ timeout: 5_000 }).catch(() => false)) {
        return `E2E Product ${stamp}`;
    }

    const name = `E2E Product ${stamp}`;
    await page.locator('.ps-toolbar button, .ps-products-page button').filter({ hasText: /Thêm mới/i }).first().click();
    const modal = page.locator('.ps-product-source-modal, .ps-product-source-form, [role="dialog"]').filter({ hasText: /THÊM MỚI|sản phẩm|Product/i }).first();
    await expect(modal).toBeVisible({ timeout: 15_000 });
    const nameInput = modal.locator('label').filter({ hasText: /Tên SP gốc|Tên sản phẩm/i }).locator('input.form-control').first();
    await nameInput.fill(name);
    const priceInput = modal.locator('label').filter({ hasText: /Đơn giá/i }).locator('input').first();
    if (await priceInput.isVisible().catch(() => false)) {
        await priceInput.fill('10000');
    }
    await modal.locator('button.ps-save-button, button.btn-primary').filter({ hasText: /Lưu/i }).first().click();
    await Promise.race([
        expectSuccessToast(page, /Đã|thành công|saved|created/i, 30_000),
        expect(page.locator('table tbody')).toContainText(name, { timeout: 30_000 }),
        page.waitForTimeout(3_000),
    ]);
    return name;
}

test.describe('16 — Phiếu nhập kho (inbound) interactions', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('luồng nhập kho: bootstrap → fill → lưu nháp → hoàn thành', async ({ page }) => {
        const stamp = String(Date.now()).slice(-6);
        const code = `PNK-E2E-${stamp}`;

        await loginAs(page, DEMO.admin);
        await ensureWarehouse(page, stamp);
        const productName = await ensureProduct(page, stamp);

        await page.goto('/admin/warehouse/vouchers/entry', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        await expect(page.locator('.ps-voucher-entry-page')).toBeVisible({ timeout: 20_000 });
        await expect(page.getByText(/Phiếu nhập\s*\/\s*xuất kho|Stock in\s*\/\s*out/i).first()).toBeVisible();

        const typeSelect = page.locator('.ps-voucher-entry-body select.form-control').first();
        await typeSelect.selectOption('inbound');
        await expect(typeSelect).toHaveValue('inbound');

        const warehouseSelect = page.locator('.ps-voucher-entry-body select.form-control').nth(1);
        const warehouseOk = await warehouseSelect.evaluate((el: HTMLSelectElement) => {
            const opt = Array.from(el.options).find((o) => o.value);
            if (!opt) return false;
            el.value = opt.value;
            el.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
        });
        expect(warehouseOk, 'Phải có ít nhất 1 kho sau bootstrap').toBeTruthy();

        await page.locator('input.ps-voucher-entry-code').fill(code);

        const delivererInput = page.locator('.ps-voucher-entry-body .row').filter({ hasText: /Họ tên người giao|Deliverer/i }).locator('input.form-control').first();
        await delivererInput.fill('Nguyễn Văn E2E');
        const partnerInput = page.locator('.ps-voucher-entry-body .row').filter({ hasText: /Nhà cung cấp|Supplier/i }).locator('input.form-control').first();
        await partnerInput.fill(`NCC E2E ${stamp}`);

        const productControl = page.locator(
            '.ps-voucher-entry-body .ps-product-select-button, .ps-voucher-entry-body button.ps-select__control, .ps-product-search-select button',
        ).first();
        await expect(productControl).toBeVisible({ timeout: 15_000 });
        await productControl.click();

        const search = page.locator('.ps-product-search-input, .ps-select__menu input.ps-select__search').first();
        if (await search.isVisible({ timeout: 3_000 }).catch(() => false)) {
            await search.fill(productName.slice(0, 12));
        }

        const productOpt = page.locator(
            '.ps-product-search-results button[role="option"], button.ps-select__option, .ps-select__menu button',
        ).filter({ hasNotText: /^--/ }).first();
        await expect(productOpt).toBeVisible({ timeout: 15_000 });
        await productOpt.click();
        await page.waitForTimeout(500);

        const dataRow = page.locator('.ps-voucher-entry-table tbody tr:not(.ps-voucher-entry-total-row)').first();
        await expect(dataRow).toBeVisible({ timeout: 10_000 });

        const numberInputs = dataRow.locator('input[type="number"]');
        await numberInputs.nth(0).fill('5');
        await numberInputs.nth(1).fill('5');
        await numberInputs.nth(2).fill('12000');

        const unitCostApply = page.locator('tr.ps-voucher-apply-row label.ps-voucher-col-apply').nth(0).locator('input.ps-voucher-col-apply__tick');
        await unitCostApply.check();
        await expect(unitCostApply).toBeChecked();

        await page.locator('.ps-voucher-entry-footer-actions button.btn-primary').filter({ hasText: /Cập nhật|Save draft/i }).click();
        await Promise.race([
            expectSuccessToast(page, /Đã lưu phiếu tạm|Draft voucher saved|Đã cập nhật phiếu tạm/i, 30_000),
            page.waitForURL(/id=\d+/, { timeout: 30_000 }),
            expect(page.locator('.ps-voucher-entry-status')).toContainText(/Phiếu tạm|Draft/i, { timeout: 30_000 }),
        ]);
        await expect(page.locator('.ps-voucher-entry-status')).toContainText(/Phiếu tạm|Draft/i);
        await expect(page.locator('body')).not.toContainText(/Server Error|Whoops|SQLSTATE/i);

        await page.locator('.ps-voucher-entry-footer-actions button.btn-success').filter({ hasText: /Hoàn thành|Complete/i }).click();
        const confirmBtn = page.getByRole('dialog').getByRole('button').filter({ hasText: /Hoàn thành|Complete|Xác nhận|Confirm/i }).last();
        await expect(confirmBtn).toBeVisible({ timeout: 15_000 });
        await confirmBtn.click();
        await Promise.race([
            expectSuccessToast(page, /Đã hoàn thành phiếu kho|Warehouse voucher completed/i, 45_000),
            expect(page.locator('.ps-voucher-entry-status')).toContainText(/Hoàn thành|Completed/i, { timeout: 45_000 }),
        ]);
        await expect(page.locator('.ps-voucher-entry-status')).toContainText(/Hoàn thành|Completed/i, { timeout: 20_000 });

        await expect(typeSelect).toBeDisabled();
        await expect(page.locator('.ps-voucher-entry-footer-actions button.btn-primary').filter({ hasText: /Cập nhật|Save draft/i })).toBeDisabled();
        await expect(page.locator('body')).not.toContainText(/Server Error|Whoops|SQLSTATE/i);
    });

    test('validation: thiếu kho / thiếu dòng → toast lỗi, không 500', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/warehouse/vouchers/entry', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        await page.locator('.ps-voucher-entry-footer-actions button.btn-primary').filter({ hasText: /Cập nhật|Save draft/i }).click();
        const errorToast = page.locator('[data-sonner-toast][data-type="error"]').first();
        await expect(errorToast).toBeVisible({ timeout: 15_000 });
        await expect(errorToast).toContainText(/kho|warehouse|dòng|line|sản phẩm|product/i);
        await expect(page.locator('body')).not.toContainText(/Server Error|Whoops|SQLSTATE/i);
    });
});
