import {
    test,
    expect,
    DEMO,
    loginAs,
    openWarehouseFab,
    waitUiReady,
} from '../helpers/fixtures';

/**
 * Warehouse 5.1 → NetShip UX walkthrough (giả lập).
 * - Không tạo đơn mới
 * - Mock create-shipment cho case 422/500; case E gọi thật nhưng company=manual not ready → 422, không NetShip
 * - Kiểm từng action: toast thân thiện, không trang 500 / Whoops
 */
test.describe('18 — Kho → NetShip UX (mock, không đụng carrier thật)', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    const assertNoScaryErrorPage = async (page: import('@playwright/test').Page) => {
        await expect(page.locator('body')).not.toContainText(/Server Error|Whoops!|SQLSTATE|Illuminate\\|stack trace/i);
        if (await page.locator('.ps-error-shell, [data-error-status="500"]').count()) {
            throw new Error('Hit ErrorShell 500 — không được hiện cho user khi thao tác kho.');
        }
    };

    const goWarehouseWaiting = async (page: import('@playwright/test').Page) => {
        await page.goto('/admin/warehouse/operations', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);
        const waitingTile = page.locator('button, a').filter({ hasText: /Chờ vận đơn/i }).first();
        if (await waitingTile.isVisible().catch(() => false)) {
            await waitingTile.click();
            await waitUiReady(page);
        }
        await assertNoScaryErrorPage(page);
    };

    const firstRegisterBtn = (page: import('@playwright/test').Page) => page
        .locator('button[title="Đăng vận đơn"], button[fam-tooltip="Đăng vận đơn"]')
        .first();

    test('A. Mở Kho 5.1 + tab Chờ vận đơn — không 500', async ({ page }) => {
        await loginAs(page, DEMO.warehouse);
        const responses: { url: string; status: number }[] = [];
        page.on('response', (res) => {
            if (res.request().resourceType() === 'document' || res.url().includes('/admin/warehouse')) {
                responses.push({ url: res.url(), status: res.status() });
            }
        });

        await goWarehouseWaiting(page);
        await expect(page.locator('body')).toContainText(/Kho|Tác nghiệp|Đăng đơn|Chờ vận đơn/i);

        const bad = responses.filter((r) => r.status >= 500);
        expect(bad, `Document/XHR 5xx: ${JSON.stringify(bad)}`).toEqual([]);

        const table = page.locator('table.ps-wh-table, .ps-wh-table-shell table');
        await expect(table.or(page.getByText(/Không có|chưa có đơn/i))).toBeVisible({ timeout: 20_000 });
    });

    test('B. FAB Đăng đơn không chọn đơn → dialog hoặc toast validate (không 500)', async ({ page }) => {
        await loginAs(page, DEMO.warehouse);
        await goWarehouseWaiting(page);

        const checkAll = page.locator('table.ps-wh-table thead input[type="checkbox"], .ps-wh-table-shell thead input[type="checkbox"]').first();
        if (await checkAll.isVisible().catch(() => false)) {
            if (await checkAll.isChecked().catch(() => false)) {
                await checkAll.click();
            }
        }

        await openWarehouseFab(page);
        const fab = page.locator('button[title="Đăng đơn"], button[fam-tooltip="Đăng đơn"]').first();
        await expect(fab).toBeVisible({ timeout: 15_000 });
        await fab.click();

        const confirm = page.getByRole('dialog').filter({ hasText: /Đăng đơn/i });
        const registerByCode = page.locator('.ps-wh-register-dialog');
        const errorToast = page.locator('[data-sonner-toast][data-type="error"]');

        await Promise.race([
            confirm.waitFor({ state: 'visible', timeout: 12_000 }),
            registerByCode.waitFor({ state: 'visible', timeout: 12_000 }),
            errorToast.waitFor({ state: 'visible', timeout: 12_000 }),
        ]);

        await assertNoScaryErrorPage(page);

        if (await registerByCode.isVisible().catch(() => false)) {
            await page.getByRole('button', { name: /^Đăng đơn$/i }).click();
            await expect(errorToast).toBeVisible({ timeout: 10_000 });
            const text = await errorToast.innerText();
            expect(text.length).toBeGreaterThan(5);
            expect(text).not.toMatch(/Server Error|Whoops|SQLSTATE|<!DOCTYPE|<html/i);
            await page.getByRole('button', { name: /Đóng|Hủy|Cancel/i }).first().click().catch(() => undefined);
            return;
        }

        if (await confirm.isVisible().catch(() => false)) {
            await page.getByRole('button', { name: /Hủy|Đóng|Cancel/i }).first().click().catch(async () => {
                await page.keyboard.press('Escape');
            });
            test.info().annotations.push({ type: 'note', description: 'FAB mở confirm — đã hủy, không gọi carrier.' });
            return;
        }

        await expect(errorToast).toBeVisible();
        expect(await errorToast.innerText()).not.toMatch(/Server Error|Whoops|SQLSTATE/i);
    });

    test('C. Mock create-shipment 422 → HTTP+toast thân thiện', async ({ page }) => {
        await loginAs(page, DEMO.warehouse);

        let mocked = 0;
        await page.route('**/create-shipment**', async (route) => {
            if (route.request().method() !== 'POST') {
                await route.continue();
                return;
            }
            mocked += 1;
            await route.fulfill({
                status: 422,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: false,
                    message: 'Chưa cấu hình đơn vị vận chuyển / NetShip cho đơn này. Chọn PTGH (Viettel/GHTK…) trước khi đăng đơn.',
                }),
            });
        });

        await goWarehouseWaiting(page);
        const rowBtn = firstRegisterBtn(page);
        if (!(await rowBtn.isVisible().catch(() => false))) {
            test.skip(true, 'No Đăng vận đơn button');
            return;
        }

        const responsePromise = page.waitForResponse(
            (res) => /create-shipment/i.test(res.url()) && res.request().method() === 'POST',
            { timeout: 20_000 },
        );

        await rowBtn.click({ force: true });
        const res = await responsePromise;
        const json = await res.json().catch(() => ({}));
        expect(res.status()).toBe(422);
        expect(String(json.message || '')).toMatch(/NetShip|vận chuyển|PTGH|đăng đơn/i);
        expect(mocked).toBeGreaterThan(0);

        const toast = page.locator('[data-sonner-toast]').filter({ hasText: /NetShip|vận chuyển|PTGH|đăng đơn|cấu hình/i }).first();
        await expect(toast).toBeVisible({ timeout: 10_000 });
        await expect(toast).not.toContainText(/Server Error|Whoops|SQLSTATE|<!DOCTYPE/i);
        await assertNoScaryErrorPage(page);
    });

    test('D. Mock create-shipment 500 HTML → toast fallback không lộ HTML', async ({ page }) => {
        await loginAs(page, DEMO.warehouse);

        await page.route('**/create-shipment**', async (route) => {
            if (route.request().method() !== 'POST') {
                await route.continue();
                return;
            }
            await route.fulfill({
                status: 500,
                contentType: 'text/html',
                body: '<!DOCTYPE html><html><body><h1>Server Error</h1><pre>SQLSTATE whoops illuminate</pre></body></html>',
            });
        });

        await goWarehouseWaiting(page);
        const rowBtn = firstRegisterBtn(page);
        if (!(await rowBtn.isVisible().catch(() => false))) {
            test.skip(true, 'No register button for 500 mock');
            return;
        }

        await rowBtn.click({ force: true });

        const toast = page.locator('[data-sonner-toast]').first();
        await expect(toast).toBeVisible({ timeout: 15_000 });
        const text = await toast.innerText();
        expect(text).not.toMatch(/SQLSTATE|Whoops|<!DOCTYPE|<html|<pre|500|Server Error/i);
        expect(text).toMatch(/Không thực hiện được|Kiểm tra lại dữ liệu|thử lại|action|incomplete/i);
        await assertNoScaryErrorPage(page);
    });

    test('E. Real create-shipment đơn Thủ công → 422 JSON (không NetShip, không 500)', async ({ page }) => {
        await loginAs(page, DEMO.warehouse);
        await goWarehouseWaiting(page);

        const row = page.locator('table.ps-wh-table tbody tr, .ps-wh-table-shell tbody tr')
            .filter({ hasText: /PS0000000000[123]PS/ })
            .first();
        const rowBtn = row.locator('button[title="Đăng vận đơn"], button[fam-tooltip="Đăng vận đơn"]').first();
        if (!(await row.isVisible().catch(() => false)) || !(await rowBtn.isVisible().catch(() => false))) {
            test.skip(true, 'Không thấy đơn PS… chờ vận đơn');
            return;
        }

        const responsePromise = page.waitForResponse(
            (res) => /create-shipment/i.test(res.url()) && res.request().method() === 'POST',
            { timeout: 30_000 },
        );

        await rowBtn.click({ force: true });
        const res = await responsePromise;
        const status = res.status();
        let message = '';
        try {
            message = String((await res.json()).message ?? '');
        } catch {
            message = await res.text();
        }

        test.info().annotations.push({
            type: 'note',
            description: `REAL create-shipment status=${status} message=${message.slice(0, 200)}`,
        });

        expect(status).toBeGreaterThanOrEqual(400);
        expect(status).toBeLessThan(500);
        expect(message).not.toMatch(/SQLSTATE|Whoops|<!DOCTYPE|<html/i);
        expect(message.length).toBeGreaterThan(5);
        await assertNoScaryErrorPage(page);
    });

    test('F. Chi tiết vận đơn — API không 500', async ({ page }) => {
        await loginAs(page, DEMO.warehouse);
        await goWarehouseWaiting(page);

        const apiStatuses: number[] = [];
        page.on('response', (res) => {
            if (/shipping\/orders\/\d+\/(detail|sync|calculate-fee|label)/i.test(res.url())) {
                apiStatuses.push(res.status());
            }
        });

        const detail = page.locator('button[title*="Chi tiết"], button[fam-tooltip*="Chi tiết"]').first();
        if (await detail.isVisible().catch(() => false)) {
            await detail.click();
            await page.waitForTimeout(1500);
        }

        await assertNoScaryErrorPage(page);
        expect(apiStatuses.filter((s) => s >= 500)).toEqual([]);
        test.info().annotations.push({ type: 'note', description: `shipping API statuses=${JSON.stringify(apiStatuses)}` });
    });
});
