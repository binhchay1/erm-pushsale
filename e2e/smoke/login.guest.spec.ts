// Guest specs: override storageState so global login does not apply.
import { test, expect } from '@playwright/test';

test.use({ storageState: { cookies: [], origins: [] } });

test('login page is reachable for guests', async ({ page }) => {
    await page.goto('/login');
    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
});
