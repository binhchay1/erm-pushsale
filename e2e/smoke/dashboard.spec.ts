import { test, expect } from '@playwright/test';

/**
 * Smoke: global-setup already authenticated — no login steps here.
 */
test.describe('authenticated smoke', () => {
    test('admin dashboard loads with session', async ({ page }) => {
        await page.goto('/admin/dashboard');
        await expect(page).not.toHaveURL(/\/login/);
        await expect(page.locator('body')).toBeVisible();
    });
});
