import {
    test,
    expect,
    DEMO,
    loginAs,
    expectSuccessToast,
    openWarehouseFab,
    waitUiReady,
} from '../helpers/fixtures';

/**
 * Kho 5.1 — FAB Đăng đơn (create shipment / NetShip proxy).
 * Requires at least one closed order with canCreateShipment (seed or prior sale flow).
 * Success toast: "Đã đăng vận đơn cho N đơn." OR per-row "Đã tạo vận đơn cho …"
 */
test.describe('04 — Kho đăng vận đơn', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('mở FAB → Đăng đơn → confirm → toast thành công hoặc thông báo không đủ điều kiện', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/warehouse/operations', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        await openWarehouseFab(page);

        const registerBtn = page.locator('button.ps-wh-action-button, button.n-button').filter({
            has: page.locator('[fam-tooltip="Đăng đơn"], [title="Đăng đơn"]'),
        }).or(page.locator('button[title="Đăng đơn"], button[fam-tooltip="Đăng đơn"]'));

        await expect(registerBtn.first()).toBeVisible({ timeout: 15_000 });
        await registerBtn.first().click();

        // Either confirm dialog, register-by-code dialog, or immediate toast error
        const confirm = page.getByRole('dialog').filter({ hasText: /Đăng đơn/i });
        const registerByCode = page.locator('.ps-wh-register-dialog');
        const errorToast = page.locator('[data-sonner-toast][data-type="error"]');

        await Promise.race([
            confirm.waitFor({ state: 'visible', timeout: 10_000 }),
            registerByCode.waitFor({ state: 'visible', timeout: 10_000 }),
            errorToast.waitFor({ state: 'visible', timeout: 10_000 }),
        ]);

        if (await confirm.isVisible().catch(() => false)) {
            await confirm.getByRole('button', { name: 'Đăng đơn' }).click();
            await expectSuccessToast(page, /Đã đăng vận đơn|Đã tạo vận đơn/i, 60_000);
            return;
        }

        if (await registerByCode.isVisible().catch(() => false)) {
            // No eligible rows on page — dialog for paste codes. Soft-pass for empty env.
            await expect(registerByCode.getByRole('button', { name: /Đăng đơn|Đóng/i }).first()).toBeVisible();
            test.info().annotations.push({
                type: 'note',
                description: 'Không có đơn đủ điều kiện trên trang — mở dialog nhập mã. Chạy 03 trước hoặc demo:ui-flow.',
            });
            return;
        }

        // Explicit empty-state toast is acceptable when DB has no closed orders
        await expect(errorToast).toContainText(/Không có đơn/i);
    });
});
