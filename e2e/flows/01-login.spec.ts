import { test, expect, DEMO, loginAs, waitUiReady } from '../helpers/fixtures';

test.describe('01 — Login', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('admin đăng nhập → /admin/dashboard', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await waitUiReady(page);
        await expect(page).toHaveURL(/\/admin\/dashboard/);
        await expect(page.locator('body')).toBeVisible();
    });

    test('sale đăng nhập → /sales/dashboard hoặc workspace home', async ({ page }) => {
        await loginAs(page, DEMO.sales);
        await waitUiReady(page);
        await expect(page).not.toHaveURL(/\/login/);
        await expect(page).toHaveURL(/\/sales\//);
    });

    test('sai mật khẩu → vẫn ở /login + lỗi', async ({ page }) => {
        await page.goto('/login', { waitUntil: 'domcontentloaded' });
        await page.locator('#email').fill(DEMO.admin);
        await page.locator('#password').fill('wrong-password-!!!');
        await page.locator('button.public-login-submit').click();
        await expect(page).toHaveURL(/\/login/);
        await expect(page.locator('.public-login-alert, .public-login-error').first()).toBeVisible({ timeout: 15_000 });
    });
});
