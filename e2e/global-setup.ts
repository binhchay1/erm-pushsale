import { chromium, type FullConfig } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const authDir = path.join(__dirname, '.auth');
const authFile = path.join(authDir, 'user.json');

/**
 * Runs once before the suite: logs in and saves storageState.
 * Specs reuse cookies/session via playwright.config.ts `use.storageState`.
 */
async function globalSetup(config: FullConfig): Promise<void> {
    const baseURL = config.projects[0]?.use?.baseURL
        || process.env.E2E_BASE_URL
        || 'http://127.0.0.1:8000';

    const email = process.env.E2E_EMAIL || 'superadmin@saleops.local';
    const password = process.env.E2E_PASSWORD || 'password';

    fs.mkdirSync(authDir, { recursive: true });

    const browser = await chromium.launch();
    const page = await browser.newPage({ locale: 'vi-VN' });

    await page.goto(`${baseURL}/login`, { waitUntil: 'networkidle' });
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.locator('button.public-login-submit').click();

    // Admin lands on /admin/dashboard; other roles use role home — accept any non-login URL.
    await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 30_000 });

    await page.context().storageState({ path: authFile });
    await browser.close();
}

export default globalSetup;
