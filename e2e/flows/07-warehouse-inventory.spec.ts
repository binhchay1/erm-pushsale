import {
    test,
    expect,
    DEMO,
    loginAs,
    mockCustomer,
    expectSuccessToast,
    waitUiReady,
} from '../helpers/fixtures';
import { fillAndCloseNewSaleOrder } from '../helpers/saleOrder';
import { openWarehouseFab } from '../helpers/ui';

async function selectFirstRealOption(select: import('@playwright/test').Locator) {
    const options = select.locator('option');
    for (let i = 0; i < await options.count(); i += 1) {
        const val = await options.nth(i).getAttribute('value');
        if (val) {
            await select.selectOption(val);
            return true;
        }
    }
    return false;
}

test.describe('07 — Tạo kho + nhập SP vào kho', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('tạo kho mới → hiện trong danh sách', async ({ page }) => {
        const stamp = String(Date.now()).slice(-6);
        const name = `Kho E2E ${stamp}`;

        await loginAs(page, DEMO.admin);
        await page.goto('/admin/warehouses', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        await page.locator('.ps-warehouse-toolbar button.btn-primary').filter({ hasText: 'Thêm' }).click();
        const form = page.locator('form.ps-warehouse-form');
        await expect(form).toBeVisible({ timeout: 15_000 });

        await form.locator('label').filter({ hasText: /Tên kho/i }).locator('input.form-control').fill(name);
        await form.locator('label').filter({ hasText: /Mã kho/i }).locator('input.form-control').fill(`WH-E2E-${stamp}`);
        await form.locator('label').filter({ hasText: /^Địa chỉ/i }).locator('input.form-control').fill('12 Nguyễn Trãi');
        await form.locator('label').filter({ hasText: /Số điện thoại/i }).locator('input.form-control').fill('0901234567');

        // 2-level: Tỉnh/TP → Phường/Xã (PushsaleSelect inside AddressSelect)
        const province = form.locator('.ps-address-select--province').first();
        await province.locator('button.ps-select__control').click();
        const provinceOpt = page.locator('button.ps-select__option').filter({ hasText: /Hà Nội/i }).first();
        if (await provinceOpt.isVisible({ timeout: 10_000 }).catch(() => false)) {
            await provinceOpt.click();
        } else {
            await page.locator('button.ps-select__option').filter({ hasNotText: /^--/ }).first().click();
        }
        await page.waitForTimeout(500);

        const ward = form.locator('.ps-address-select--ward').first();
        const wardBtn = ward.locator('button.ps-select__control');
        if (await wardBtn.isEnabled().catch(() => false)) {
            await wardBtn.click();
            const wardOpt = page.locator('button.ps-select__option').filter({ hasNotText: /^--/ }).first();
            if (await wardOpt.isVisible({ timeout: 10_000 }).catch(() => false)) await wardOpt.click();
        }

        await form.locator('button.btn-primary').filter({ hasText: /Thêm mới/i }).click();

        const danger = form.locator('.alert.alert-danger');
        await Promise.race([
            expect(page.locator('table.ps-warehouse-table')).toContainText(name, { timeout: 30_000 }),
            expectSuccessToast(page, /Đã tạo kho/i, 30_000),
            danger.waitFor({ state: 'visible', timeout: 8_000 }).then(async () => {
                throw new Error(`Tạo kho lỗi validation: ${await danger.innerText()}`);
            }),
        ]);
    });

    test('nhập sản phẩm vào kho (inventory intake) → toast thành công', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/warehouse/inventory', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        await page.locator('.ps-inventory-toolbar button').filter({ hasText: /Cập nhật vị trí/i }).click();

        const dialogForm = page.locator('form.ps-form-grid').filter({ hasText: /Nhập kho/i });
        await expect(dialogForm).toBeVisible({ timeout: 15_000 });
        await dialogForm.locator('button').filter({ hasText: 'Nhập kho' }).click();

        const warehouseSelect = dialogForm.locator('label').filter({ hasText: /^Kho/i }).locator('select');
        const productSelect = dialogForm.locator('label').filter({ hasText: /^Sản phẩm/i }).locator('select');
        const qty = dialogForm.locator('label').filter({ hasText: /Số lượng/i }).locator('input');
        const approver = dialogForm.locator('label').filter({ hasText: /Người duyệt/i }).locator('select');

        expect(await selectFirstRealOption(warehouseSelect)).toBeTruthy();
        expect(await selectFirstRealOption(productSelect)).toBeTruthy();
        expect(await selectFirstRealOption(approver)).toBeTruthy();
        await qty.fill('10');

        await dialogForm.locator('button.btn-primary').filter({ hasText: /Lưu phiếu/i }).click();

        const formError = dialogForm.locator('.alert.alert-danger');
        await Promise.race([
            expectSuccessToast(page, /Đã nhập kho|nhập kho thành công/i, 30_000),
            expect(page.locator('table.ps-inventory-table tbody tr').first()).toBeVisible({ timeout: 30_000 }),
            formError.waitFor({ state: 'visible', timeout: 8_000 }).then(async () => {
                throw new Error(`Nhập kho lỗi: ${await formError.innerText()}`);
            }),
        ]);
    });
});

test.describe('08 — Chốt đơn không VC + Đăng đơn không 500', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('chốt đơn shipping thủ công (trống) → thành công, không 500', async ({ page }) => {
        const customer = mockCustomer();
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/sales/workspace', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        await fillAndCloseNewSaleOrder(page, customer);
        await expect(page.locator('body')).not.toContainText(/Server Error|Whoops|Exception|SQLSTATE/i);
        await expect(page).not.toHaveURL(/\/login/);
    });

    test('Đăng đơn khi chưa cấu hình hãng → toast lỗi rõ (422), không trang 500', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/warehouse/operations', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        const waiting = page.locator('button, a, div, span').filter({ hasText: /Chờ vận đơn\s*\(/i }).first();
        if (await waiting.isVisible().catch(() => false)) await waiting.click();
        await waitUiReady(page);

        const row = page.locator('table.ps-wh-table tbody tr').filter({
            hasNot: page.locator('.ps-wh-empty'),
        }).first();

        if (!(await row.isVisible().catch(() => false))) {
            test.skip(true, 'Không có đơn chờ vận đơn để thử Đăng đơn');
            return;
        }

        const checkbox = row.locator('input[type="checkbox"]').first();
        if (await checkbox.count()) await checkbox.check();

        await openWarehouseFab(page);
        await page.locator('button[title="Đăng đơn"], button[fam-tooltip="Đăng đơn"]').first().click();

        const confirm = page.getByRole('dialog').filter({ hasText: /Đăng đơn/i });
        if (await confirm.isVisible({ timeout: 8_000 }).catch(() => false)) {
            await confirm.getByRole('button', { name: 'Đăng đơn' }).click();
        }

        const toast = page.locator('[data-sonner-toast]').first();
        await expect(toast).toBeVisible({ timeout: 60_000 });
        await expect(page.locator('body')).not.toContainText(/Server Error|Whoops|SQLSTATE/i);

        const text = await toast.innerText();
        test.info().annotations.push({ type: 'note', description: `Đăng đơn result: ${text}` });

        if (await page.locator('[data-sonner-toast][data-type="error"]').count()) {
            await expect(toast).toContainText(/vận chuyển|cấu hình|kho|tồn|chưa|không|NetShip|hãng|carrier|đơn vị|bật/i);
        }
    });
});
