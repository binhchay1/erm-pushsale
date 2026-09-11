import {
    test,
    expect,
    DEMO,
    loginAs,
    waitUiReady,
} from '../helpers/fixtures';

/**
 * Shop switcher + empty/missing shop must never 500; block/guide instead.
 */
test.describe('12 — Shop switcher / multi-shop guard', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('header hiện shop hoặc empty-state; đổi shop không 500', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/dashboard', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        await expect(page.locator('body')).not.toContainText(/Server Error|Whoops|SQLSTATE/i);

        const switcher = page.locator('.pushsale-shop-switcher').first();
        await expect(switcher).toBeVisible({ timeout: 15_000 });

        // Multi-shop: open dropdown and switch if possible
        const trigger = page.locator('button.pushsale-shop-switcher').first();
        if (await trigger.isVisible().catch(() => false)) {
            await trigger.click();
            const item = page.locator('[role="menuitem"], .pushsale-shop-switcher-dropdown [role="menuitem"]').nth(1);
            if (await item.isVisible({ timeout: 3_000 }).catch(() => false)) {
                await item.click();
                await waitUiReady(page);
                await expect(page.locator('body')).not.toContainText(/Server Error|Whoops|SQLSTATE/i);
            }
        }
    });

    test('trang quản lý cửa hàng /admin/shops load không 500', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/shops', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);
        await expect(page).not.toHaveURL(/\/login/);
        await expect(page.locator('body')).not.toContainText(/Server Error|Whoops|SQLSTATE/i);
    });

    test('overview đa shop /admin/shops/overview load không 500', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/shops/overview', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);
        await expect(page).not.toHaveURL(/\/login/);
        await expect(page.locator('body')).not.toContainText(/Server Error|Whoops|SQLSTATE/i);
    });
});
