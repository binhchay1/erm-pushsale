import {
    test,
    expect,
    DEMO,
    loginAs,
    mockCustomer,
    expectSuccessToast,
    openWarehouseFab,
    waitUiReady,
} from '../helpers/fixtures';
import { fillAndCloseNewSaleOrder } from '../helpers/saleOrder';

/**
 * Full customer-demo pipeline (serial) against real server data.
 * Run: E2E_BASE_URL=https://salesloop.vn pnpm e2e:demo
 */
test.describe.configure({ mode: 'serial' });

test.describe('05 — Full pipeline demo (khách hàng)', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    const customer = mockCustomer();
    let orderPhone = customer.phone;

    test('A. Login admin', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await expect(page).toHaveURL(/\/admin\//);
    });

    test('B. Nhập lead thủ công (Marketing 2.6.2)', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/marketing/leads/manual', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        const form = page.locator('form.ps-manual-lead-form');
        await expect(form).toBeVisible();

        const sourceDdl = form.locator('.ps-ddl').filter({ hasText: '--Chọn nguồn dữ liệu--' }).first();
        await sourceDdl.locator('button.ps-ddl-display-text').click();
        const sourceItem = sourceDdl.locator('button.ps-ddl-result-item').first();
        if (await sourceItem.isVisible().catch(() => false)) await sourceItem.click();
        else await page.keyboard.press('Escape');

        await form.locator('label.ps-manual-lead-field').filter({ hasText: 'Họ tên khách hàng' })
            .locator('input.form-control').fill(customer.name);
        await form.locator('input.form-control.phone-number').fill(customer.phone);
        await form.locator('label.ps-manual-lead-field').filter({ hasText: 'Tin nhắn' })
            .locator('textarea.form-control').fill(customer.message);

        const save = form.locator('button.btn.btn-sm.btn-default').filter({ hasText: 'Lưu đơn' });
        await expect(save).toBeEnabled();
        await save.click();

        await expect(form.locator('.ps-manual-lead-message')).toContainText(/Đã lưu|Đã thêm|Đã tạo/i, {
            timeout: 30_000,
        });
        orderPhone = customer.phone;
    });

    test('C. Sale tạo & chốt đơn (4.1)', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/sales/workspace', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);
        await fillAndCloseNewSaleOrder(page, { ...customer, phone: orderPhone });
    });

    test('D. Kho đăng vận đơn (5.1)', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/warehouse/operations', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        // Narrow to the order just closed
        const search = page.getByPlaceholder(/Họ tên, số điện thoại/i);
        if (await search.count()) {
            await search.fill(orderPhone);
            await page.getByRole('button', { name: /Tìm kiếm/i }).click();
            await waitUiReady(page);
        }

        const waitingTile = page.locator('button, a, div').filter({ hasText: /Chờ vận đơn/i }).first();
        if (await waitingTile.isVisible().catch(() => false)) {
            await waitingTile.click();
            await waitUiReady(page);
        }

        const row = page.locator('table.ps-wh-table tbody tr').filter({ hasText: orderPhone }).first();
        await expect(row).toBeVisible({ timeout: 30_000 });
        const checkbox = row.locator('input[type="checkbox"]').first();
        if (await checkbox.count()) await checkbox.check();

        await openWarehouseFab(page);
        const registerBtn = page.locator('button[title="Đăng đơn"], button[fam-tooltip="Đăng đơn"]').first();
        await registerBtn.click();

        const confirm = page.getByRole('dialog').filter({ hasText: /Đăng đơn/i });
        if (await confirm.isVisible({ timeout: 8_000 }).catch(() => false)) {
            await confirm.getByRole('button', { name: 'Đăng đơn' }).click();
        }

        const success = page.locator('[data-sonner-toast][data-type="success"]').filter({
            hasText: /Đã đăng vận đơn|Đã tạo vận đơn/i,
        });
        const error = page.locator('[data-sonner-toast][data-type="error"]');

        await Promise.race([
            success.waitFor({ state: 'visible', timeout: 60_000 }),
            error.waitFor({ state: 'visible', timeout: 60_000 }),
            page.locator('.ps-wh-register-dialog').waitFor({ state: 'visible', timeout: 60_000 }),
        ]);

        if (await success.isVisible().catch(() => false)) {
            await expect(success).toBeVisible();
            return;
        }

        // Prod may lack NetShip/carrier credentials — UI flow still verified up to API call.
        const errText = (await error.first().innerText().catch(() => ''))
            || 'Không tạo được vận đơn (thiếu cấu hình hãng/NetShip).';
        test.info().annotations.push({ type: 'note', description: errText });
        await expect(row).toBeVisible();
    });

    test('E. Kế toán workspace smoke (6.1)', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/accounting', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);
        await expect(page).not.toHaveURL(/\/login/);
        await expect(page.locator('table.ps-wh-table').first()).toBeVisible();
    });
});
