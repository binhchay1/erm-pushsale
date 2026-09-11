import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const authFile = path.join(__dirname, 'e2e/.auth/user.json');

/**
 * E2E against a running Laravel app (php artisan serve / local nginx / staging).
 * Credentials: E2E_EMAIL / E2E_PASSWORD (see .env.e2e.example).
 *
 * Demo full pipeline (serial):
 *   pnpm e2e:demo
 */
export default defineConfig({
    testDir: './e2e',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    reporter: [['list'], ['html', { open: 'never' }]],
    timeout: 120_000,
    expect: { timeout: 15_000 },

    globalSetup: path.join(__dirname, 'e2e/global-setup.ts'),

    use: {
        baseURL: process.env.E2E_BASE_URL || 'http://127.0.0.1:8000',
        storageState: authFile,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        locale: 'vi-VN',
        actionTimeout: 20_000,
        navigationTimeout: 45_000,
    },

    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'demo',
            testMatch: '**/flows/05-full-pipeline.demo.spec.ts',
            use: { ...devices['Desktop Chrome'], storageState: { cookies: [], origins: [] } },
        },
    ],
});
