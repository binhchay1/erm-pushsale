import type { Page } from '@playwright/test';

/** Demo accounts — production salesloop.vn currently has these emails. */
export const DEMO = {
    password: 'password',
    superadmin: 'superadmin@saleops.local',
    admin: 'superadmin@saleops.local',
    sales: 'sales01@saleops.local',
    marketing: 'mkt01@saleops.local',
    /** Fallback to admin when warehouse role user is absent on prod. */
    warehouse: process.env.E2E_WAREHOUSE_EMAIL || 'superadmin@saleops.local',
    accounting: process.env.E2E_ACCOUNTING_EMAIL || 'superadmin@saleops.local',
} as const;

export type DemoRole = keyof Omit<typeof DEMO, 'password'>;

/**
 * Fresh VN mobile (10 digits, 09…) matching `vietnamesePhone` / ManualLeadController.
 * Unique per call so duplicate-phone policies do not block demos.
 */
export function mockCustomer(overrides: Partial<{
    name: string;
    phone: string;
    message: string;
    address: string;
}> = {}) {
    const suffix = String(Date.now()).slice(-8);
    return {
        name: overrides.name ?? `KH Demo E2E ${suffix}`,
        phone: overrides.phone ?? `09${suffix}`,
        message: overrides.message ?? 'Khách gọi lại trong giờ hành chính — E2E demo.',
        address: overrides.address ?? '12 Nguyễn Trãi',
    };
}

/** Login via real LoginForm selectors (#email, #password, button.public-login-submit). */
export async function loginAs(page: Page, email: string, password = DEMO.password) {
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.locator('#email').waitFor({ state: 'visible' });
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    const submit = page.locator('button.public-login-submit');
    await expectEnabled(submit);
    await Promise.all([
        page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 45_000 }),
        submit.click(),
    ]);
}

export async function logout(page: Page) {
    // Prefer POST logout if exposed; otherwise clear cookies.
    await page.context().clearCookies();
}

async function expectEnabled(locator: ReturnType<Page['locator']>) {
    await locator.waitFor({ state: 'visible' });
    await locator.waitFor({ state: 'attached' });
    for (let i = 0; i < 20; i += 1) {
        if (await locator.isEnabled()) return;
        await locator.page().waitForTimeout(150);
    }
}
