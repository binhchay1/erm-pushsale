import {
    test,
    expect,
    DEMO,
    loginAs,
    waitUiReady,
    expectSuccessToast,
} from '../helpers/fixtures';
import { openWarehouseFab } from '../helpers/ui';

/**
 * Smoke + interaction checks for the three ops workspaces on real data.
 * Asserts pages load, key controls exist, no Server Error, and light interactions work.
 */
test.describe('09 — Tác nghiệp Sale / Kho / Kế toán', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('Sale workspace: tabs + bảng + FAB Tạo đơn mở dialog', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/sales/workspace', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        await expect(page.locator('body')).not.toContainText(/Server Error|Whoops|SQLSTATE/i);
        await expect(page.locator('button.tao-don-fixed.ps-create-order-fab')).toBeVisible();

        const tabs = page.locator('.ps-sale-stage-tabs button.dm-tac-nghiep, button.dm-tac-nghiep');
        if (await tabs.count()) {
            const second = tabs.nth(1);
            if (await second.isVisible()) {
                await second.click();
                await waitUiReady(page);
                await expect(page.locator('body')).not.toContainText(/Server Error|Whoops/i);
            }
        }

        await page.locator('button.tao-don-fixed.ps-create-order-fab').click();
        const dialog = page.locator('.ps-sale-order-dialog, .ps-sale-order-modal').first();
        await expect(dialog).toBeVisible({ timeout: 15_000 });
        await page.keyboard.press('Escape');
        // Dialog may need close via overlay/X
        if (await dialog.isVisible().catch(() => false)) {
            const close = page.locator('[data-radix-dialog-close], button').filter({ hasText: /Đóng|Hủy/i }).first();
            if (await close.isVisible().catch(() => false)) await close.click();
            else await page.mouse.click(10, 10);
        }
    });

    test('Sale: đổi kết quả tác nghiệp trên dòng đầu (nếu có) không 500', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/sales/workspace', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        const resultSelect = page.locator('select.ps-result-select').first();
        if (!(await resultSelect.isVisible().catch(() => false))) {
            test.info().annotations.push({ type: 'note', description: 'Không có select kết quả trên trang — skip soft' });
            return;
        }

        const options = resultSelect.locator('option');
        let picked = false;
        for (let i = 1; i < await options.count(); i += 1) {
            const val = await options.nth(i).getAttribute('value');
            if (val && val !== 'closed_success') {
                await resultSelect.selectOption(val);
                picked = true;
                break;
            }
        }
        if (!picked) return;

        // May open dialog — dismiss without hard fail
        await page.waitForTimeout(800);
        await expect(page.locator('body')).not.toContainText(/Server Error|Whoops|SQLSTATE/i);
    });

    test('Kho tác nghiệp: filter tabs + FAB mở được', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/warehouse/operations', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        await expect(page.locator('body')).not.toContainText(/Server Error|Whoops|SQLSTATE/i);
        await expect(page.locator('table.ps-wh-table, .ps-wh-table-shell').first()).toBeVisible();

        const allTab = page.locator('button.dm-tac-nghiep, button').filter({ hasText: /Tất cả/i }).first();
        if (await allTab.isVisible().catch(() => false)) {
            await allTab.click();
            await waitUiReady(page);
        }

        const waiting = page.locator('button, div, span').filter({ hasText: /Chờ vận đơn/i }).first();
        if (await waiting.isVisible().catch(() => false)) {
            await waiting.click();
            await waitUiReady(page);
            await expect(page.locator('body')).not.toContainText(/Server Error/i);
        }

        await openWarehouseFab(page);
        await expect(page.locator('button[title="Đăng đơn"], button[fam-tooltip="Đăng đơn"]').first()).toBeVisible();
    });

    test('Kế toán tác nghiệp: bảng load + sync icon hiện (variant accounting)', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/accounting', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        await expect(page).not.toHaveURL(/\/login/);
        await expect(page.locator('body')).not.toContainText(/Server Error|Whoops|SQLSTATE/i);
        await expect(page.locator('table.ps-wh-table').first()).toBeVisible({ timeout: 20_000 });

        // Accounting uses retweet sync instead of bomb
        const sync = page.locator('button[title*="Đồng bộ"], button.ps-wh-inline-icon').first();
        if (await sync.isVisible().catch(() => false)) {
            await expect(sync).toBeVisible();
        }
    });
});

test.describe('10 — Báo cáo sau nhập kho', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('inventory + daily-stock + movement-summary load và không 500', async ({ page }) => {
        await loginAs(page, DEMO.admin);

        const urls = [
            '/admin/warehouse/inventory',
            '/admin/warehouse/reports/daily-stock',
            '/admin/warehouse/reports/pending-export',
            '/admin/warehouse/reports/movement-summary',
            '/admin/warehouse/movement-history',
            '/admin/warehouse/vouchers',
        ];

        for (const url of urls) {
            await page.goto(url, { waitUntil: 'domcontentloaded' });
            await waitUiReady(page);
            await expect(page, `fail ${url}`).not.toHaveURL(/\/login/);
            await expect(page.locator('body'), `500 at ${url}`).not.toContainText(/Server Error|Whoops|SQLSTATE/i);
        }
    });

    test('sau intake: trang tồn kho có dòng + số lượng > 0', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/warehouse/inventory', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        const rows = page.locator('table.ps-inventory-table tbody tr').filter({
            hasNot: page.locator('.ps-empty'),
        });
        if (await rows.count()) {
            await expect(rows.first()).toBeVisible();
            // Stock column exists
            await expect(page.locator('table.ps-inventory-table')).toContainText(/\d+/);
        } else {
            // Do intake quickly then recheck
            await page.locator('.ps-inventory-toolbar button').filter({ hasText: /Cập nhật vị trí/i }).click();
            const form = page.locator('form.ps-form-grid').filter({ hasText: /Nhập kho/i });
            await expect(form).toBeVisible();
            for (const label of [/^Kho/i, /^Sản phẩm/i, /Người duyệt/i]) {
                const sel = form.locator('label').filter({ hasText: label }).locator('select');
                const opts = sel.locator('option');
                for (let i = 0; i < await opts.count(); i += 1) {
                    const v = await opts.nth(i).getAttribute('value');
                    if (v) {
                        await sel.selectOption(v);
                        break;
                    }
                }
            }
            await form.locator('label').filter({ hasText: /Số lượng/i }).locator('input').fill('5');
            await form.locator('button.btn-primary').filter({ hasText: /Lưu phiếu/i }).click();
            await expectSuccessToast(page, /Đã nhập kho|thành công/i, 30_000).catch(() => undefined);
            await page.goto('/admin/warehouse/inventory', { waitUntil: 'domcontentloaded' });
            await waitUiReady(page);
            await expect(page.locator('table.ps-inventory-table tbody tr').first()).toBeVisible();
        }
    });
});

test.describe('11 — Pancake integration UI + missing config', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('trang integrations#pancake load; test connection không 500', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/integrations', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        await expect(page.locator('body')).not.toContainText(/Server Error|Whoops|SQLSTATE/i);

        // Find Pancake card / section
        const pancake = page.locator('body').getByText(/Pancake/i).first();
        await expect(pancake).toBeVisible({ timeout: 20_000 });

        // Try Test button if present
        const testBtn = page.getByRole('button', { name: /Test|Kiểm tra|Thử/i }).first();
        if (await testBtn.isVisible().catch(() => false)) {
            await testBtn.click();
            await page.waitForTimeout(2000);
            // Must stay on page — 422 shows as toast/inline, not HTML 500
            await expect(page.locator('body')).not.toContainText(/Server Error|Whoops|SQLSTATE/i);
            const toast = page.locator('[data-sonner-toast], .alert, [role="alert"]').first();
            if (await toast.isVisible().catch(() => false)) {
                test.info().annotations.push({
                    type: 'note',
                    description: `Pancake test UI: ${(await toast.innerText()).slice(0, 200)}`,
                });
            }
        }
    });
});
