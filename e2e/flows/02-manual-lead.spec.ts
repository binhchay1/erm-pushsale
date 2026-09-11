import {
    test,
    expect,
    DEMO,
    loginAs,
    mockCustomer,
    waitUiReady,
} from '../helpers/fixtures';

/**
 * Menu 2.6.2 — Manual lead intake.
 * View: ManualLeadEntry.jsx · POST /admin/leads/manual · phone required.
 */
test.describe('02 — Marketing nhập lead thủ công', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('điền form → Lưu đơn → hiện thông báo thành công', async ({ page }) => {
        const customer = mockCustomer();

        await loginAs(page, DEMO.admin);
        await page.goto('/admin/marketing/leads/manual', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        const form = page.locator('form.ps-manual-lead-form');
        await expect(form).toBeVisible();

        // Nguồn (optional but preferred for allocation) — first available result after open+search empty.
        const sourceDdl = form.locator('.ps-ddl').filter({ hasText: '--Chọn nguồn dữ liệu--' }).first();
        if (await sourceDdl.count()) {
            await sourceDdl.locator('button.ps-ddl-display-text').click();
            const first = sourceDdl.locator('button.ps-ddl-result-item').first();
            if (await first.isVisible().catch(() => false)) {
                await first.click();
            } else {
                await page.keyboard.press('Escape');
            }
        }

        // Sản phẩm (optional multi) — pick first if list has items
        const productDdl = form.locator('.ps-ddl').filter({ hasText: '--Chọn sản phẩm--' }).first();
        if (await productDdl.count()) {
            await productDdl.locator('button.ps-ddl-display-text').click();
            const firstProduct = productDdl.locator('button.ps-ddl-result-item').first();
            if (await firstProduct.isVisible().catch(() => false)) {
                await firstProduct.click();
                await page.keyboard.press('Escape');
            } else {
                await page.keyboard.press('Escape');
            }
        }

        await form.locator('label.ps-manual-lead-field').filter({ hasText: 'Họ tên khách hàng' })
            .locator('input.form-control').fill(customer.name);

        await form.locator('input.form-control.phone-number').fill(customer.phone);

        await form.locator('label.ps-manual-lead-field').filter({ hasText: 'Tin nhắn' })
            .locator('textarea.form-control').fill(customer.message);

        const save = form.locator('button.btn.btn-sm.btn-default').filter({ hasText: 'Lưu đơn' });
        await expect(save).toBeEnabled();
        await save.click();

        await expect(form.locator('.ps-manual-lead-message')).toContainText(
            /Đã lưu data thủ công|Đã thêm lead|Đã tạo và chốt/i,
            { timeout: 30_000 },
        );

        await expect(form.locator('table.ps-manual-lead-table, .ps-manual-lead-list')).toContainText(customer.phone, {
            timeout: 15_000,
        });
    });
});
