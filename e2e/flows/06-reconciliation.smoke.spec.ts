import {
    test,
    expect,
    DEMO,
    loginAs,
    waitUiReady,
} from '../helpers/fixtures';

/**
 * Optional closing slide for demo: shipping reconciliation list loads.
 */
test.describe('06 — Đối soát vận chuyển / COD (smoke)', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('trang đối soát mở được khi đã login admin', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/shipping/reconciliation', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);
        await expect(page).not.toHaveURL(/\/login/);
        await expect(page.locator('body')).toBeVisible();
    });
});
