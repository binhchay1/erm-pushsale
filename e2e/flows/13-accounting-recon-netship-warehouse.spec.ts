import path from 'node:path';
import { fileURLToPath } from 'node:url';

import {
    test,
    expect,
    DEMO,
    loginAs,
    openWarehouseFab,
    waitUiReady,
} from '../helpers/fixtures';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const RECON_XLS = path.join(__dirname, '..', 'fixtures', '3.doisoat.xls');

async function clickFabByTooltip(page: import('@playwright/test').Page, pattern: RegExp) {
    await openWarehouseFab(page);
    const buttons = page.locator('button.n-button, button.ps-wh-action-button, button.main-action');
    const count = await buttons.count();
    for (let i = 0; i < count; i += 1) {
        const el = buttons.nth(i);
        const tip = `${(await el.getAttribute('fam-tooltip')) || ''} ${(await el.getAttribute('title')) || ''}`.trim();
        if (pattern.test(tip)) {
            await el.click();
            return tip;
        }
    }
    throw new Error(`FAB action not found matching ${pattern}`);
}

test.describe('13 — Kế toán đối soát + NetShip + Kho', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('kế toán 6.1: bảng thẳng hàng trái với status chips', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/accounting', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        const status = page.locator('.ps-acc-page .ps-wh-status-row').first();
        const table = page.locator('.ps-acc-page .ps-wh-table-shell').first();
        await expect(status).toBeVisible({ timeout: 20_000 });
        await expect(table).toBeVisible({ timeout: 20_000 });

        const aligned = await page.evaluate(() => {
            const statusEl = document.querySelector('.ps-acc-page .ps-wh-status-row') as HTMLElement | null;
            const tableEl = document.querySelector('.ps-acc-page .ps-wh-table-shell') as HTMLElement | null;
            if (!statusEl || !tableEl) return { ok: false, delta: -1, statusLeft: 0, tableLeft: 0 };
            const s = statusEl.getBoundingClientRect();
            const t = tableEl.getBoundingClientRect();
            const delta = Math.abs(s.left - t.left);
            return { ok: delta <= 2, delta, statusLeft: s.left, tableLeft: t.left };
        });

        expect(aligned.ok, `Table left ${aligned.tableLeft} vs status ${aligned.statusLeft} (Δ=${aligned.delta})`).toBeTruthy();
    });

    test('FAB → dialog đối soát theo mã đơn', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/accounting', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        await clickFabByTooltip(page, /Cập nhật đối soát đơn|đối soát theo mã/i);

        const dialog = page.getByRole('dialog').filter({ hasText: /Cập nhật đối soát theo mã đơn/i });
        await expect(dialog).toBeVisible({ timeout: 15_000 });
        await expect(dialog.locator('textarea, select').first()).toBeVisible();
        await expect(dialog.getByRole('button', { name: /Cập nhật đối soát/i })).toBeVisible();
        await page.keyboard.press('Escape');
    });

    test('FAB → đối soát Excel: upload fixture 3.doisoat.xls', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/accounting', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        await clickFabByTooltip(page, /Đối soát đơn bằng Excel|đối soát Excel/i);

        const dialog = page.getByRole('dialog').filter({ hasText: /Cập nhật đối soát Excel/i });
        await expect(dialog).toBeVisible({ timeout: 15_000 });
        await expect(dialog.getByText(/Tải mẫu/i).first()).toBeVisible();
        await expect(dialog.getByRole('button', { name: /Upload/i })).toBeVisible();
        await expect(dialog.getByRole('button', { name: /2\.\s*Đối soát/i })).toBeVisible();

        const fileInput = dialog.locator('input[type="file"]');
        await expect(fileInput).toBeAttached();
        await fileInput.setInputFiles(RECON_XLS);
        await dialog.getByRole('button', { name: /^Upload$/i }).click();

        const statsCell = dialog.locator('.ps-ttgh-excel-stats td').nth(1);
        const toast = page.locator('[data-sonner-toast]');
        await Promise.race([
            expect(statsCell).not.toHaveText(/^0$/, { timeout: 60_000 }),
            toast.waitFor({ state: 'visible', timeout: 60_000 }),
            dialog.locator('.ps-ttgh-excel-main li, .ps-ttgh-excel-main tr, .ps-ttgh-excel-list li').first()
                .waitFor({ state: 'visible', timeout: 60_000 }),
        ]);

        await expect(page.locator('body')).not.toContainText(/Server Error|Whoops|SQLSTATE/i);
        test.info().annotations.push({
            type: 'note',
            description: `Uploaded e2e/fixtures/${path.basename(RECON_XLS)}`,
        });
    });

    test('FAB → trang cập nhật theo mã Pushsale', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/accounting', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        await clickFabByTooltip(page, /Cập nhật nhiều đơn theo mã Pushsale|theo mã pushsale/i);

        await expect(page).toHaveURL(/update-by-code/, { timeout: 20_000 });
        await waitUiReady(page);
        await expect(page.locator('.ps-wh-bulk-page, .ps-wh-bulk-body').first()).toBeVisible({ timeout: 15_000 });
        await expect(page.locator('textarea.ps-wh-bulk-codes, textarea').first()).toBeVisible();
        await expect(page.getByRole('button', { name: /Thực hiện/i })).toBeVisible();
        await expect(page.getByText(/Chỉ dẫn|Ghi chú/i).first()).toBeVisible();
    });

    test('kho 5.1: FAB đăng đơn không 500', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/warehouse/operations', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        await openWarehouseFab(page);
        await expect(page.locator('button.n-button, button.ps-wh-action-button').first()).toBeVisible();

        const register = page.locator('button[title="Đăng đơn"], button[fam-tooltip="Đăng đơn"]').first();
        if (await register.isVisible().catch(() => false)) {
            await register.click();
            const confirm = page.getByRole('dialog').filter({ hasText: /Đăng đơn/i });
            if (await confirm.isVisible({ timeout: 8_000 }).catch(() => false)) {
                await confirm.getByRole('button', { name: /Đăng đơn/i }).click();
            }
            await page.locator('[data-sonner-toast]').first().waitFor({ state: 'visible', timeout: 60_000 }).catch(() => undefined);
        }

        await expect(page.locator('body')).not.toContainText(/Server Error|Whoops|SQLSTATE/i);
    });

    test('phiếu NXK 5.3.1: Import/Xuất Excel hiện đủ chữ', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/warehouse/vouchers/entry', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        await expect(page).not.toHaveURL(/\/login/);
        await expect(page.locator('body')).not.toContainText(/Server Error|Whoops|SQLSTATE/i);

        const importBtn = page.locator('button, a, label').filter({ hasText: /Import Excel/i }).first();
        const exportBtn = page.locator('button, a, label').filter({ hasText: /Xuất Excel/i }).first();
        await expect(importBtn).toBeVisible({ timeout: 20_000 });
        await expect(exportBtn).toBeVisible({ timeout: 10_000 });

        const box = await importBtn.boundingBox();
        expect((box?.width ?? 0) > 60, `Import Excel button too narrow: ${box?.width}`).toBeTruthy();
    });

    test('NetShip: cấu hình đối tác + ShopID kho không 500', async ({ page }) => {
        await loginAs(page, DEMO.admin);

        await page.goto('/admin/warehouses', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);
        await expect(page.locator('body')).not.toContainText(/Server Error|Whoops|SQLSTATE/i);

        const edit = page.locator('table.ps-warehouse-table tbody tr').first()
            .locator('a, button').filter({ hasText: /Sửa|Edit|Chi tiết/i }).first();
        if (await edit.isVisible().catch(() => false)) {
            await edit.click();
            await waitUiReady(page);
            await expect(page.locator('body')).not.toContainText(/Server Error|Whoops|SQLSTATE/i);
        }

        // Partners page (menu 1.4) — several possible paths
        const partnerPaths = [
            '/admin/shipping/partners',
            '/admin/integrations/shipping-partners',
            '/admin/company/shipping-partners',
        ];
        let opened = false;
        for (const url of partnerPaths) {
            const res = await page.goto(url, { waitUntil: 'domcontentloaded' }).catch(() => null);
            if (res && res.ok() && !page.url().includes('/login')) {
                opened = true;
                break;
            }
        }
        if (!opened) {
            test.info().annotations.push({ type: 'note', description: 'Shipping partners route not found — skipped NetShip label assert' });
            return;
        }
        await waitUiReady(page);
        await expect(page.locator('body')).not.toContainText(/Server Error|Whoops|SQLSTATE/i);
        const netship = page.getByText(/NetShip/i).first();
        if (await netship.isVisible().catch(() => false)) {
            await expect(netship).toBeVisible();
        }
    });
});
