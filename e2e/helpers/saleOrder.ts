import type { Locator, Page } from '@playwright/test';
import { expect } from '@playwright/test';

import {
    expectSuccessToast,
    fillOrderText,
    waitUiReady,
} from './ui';

export type SaleOrderCustomer = {
    name: string;
    phone: string;
    message: string;
    address: string;
};

export type SaleOrderOptions = {
    /** Prefer selecting a real carrier (Viettel/GHTK/…) so warehouse can register via NetShip. */
    preferCarrier?: boolean | RegExp;
};

/**
 * Fill SaleOrderDialog for create+close on real prod data.
 * Products may already appear from marketing source — do not force disabled SKU select.
 */
export async function fillAndCloseNewSaleOrder(
    page: Page,
    customer: SaleOrderCustomer,
    options: SaleOrderOptions = {},
) {
    await page.locator('button.tao-don-fixed.ps-create-order-fab').click();
    const dialog = page.locator('.ps-sale-order-dialog, .ps-sale-order-modal').first();
    await expect(dialog).toBeVisible({ timeout: 20_000 });

    // Source (left panel first PushsaleSelect)
    const source = dialog.locator('.ps-order-left-panel .ps-select').first();
    await source.locator('button.ps-select__control').click();
    const sourceOpt = source.locator('button.ps-select__option').filter({ hasNotText: /^--/ }).first();
    await sourceOpt.waitFor({ state: 'visible', timeout: 15_000 });
    await sourceOpt.click();
    await page.waitForTimeout(400);

    await fillOrderText(dialog, /Họ tên khách hàng/i, customer.name);
    await fillOrderText(dialog, /^Số điện thoại|\(\*\) Số điện thoại|Số điện thoại/i, customer.phone);
    await fillOrderText(dialog, /^Tin nhắn/i, customer.message);
    await fillOrderText(dialog, /Số nhà\/đường|Tìm kiếm \(Tối đa 200/i, customer.address);

    // Province → District → Ward (skip if already filled)
    await pickGeoSelect(dialog, /Tỉnh\/TP/i);
    await pickGeoSelect(dialog, /Quận\/Huyện/i);
    await pickGeoSelect(dialog, /Phường\/Xã/i);

    // Warehouse native <select>
    const warehouse = dialog.locator('.ps-order-right-panel').locator('select.form-control').first();
    await expect(warehouse).toBeVisible();
    const whOptions = warehouse.locator('option');
    let pickedWh = false;
    for (let i = 0; i < await whOptions.count(); i += 1) {
        const val = await whOptions.nth(i).getAttribute('value');
        const text = (await whOptions.nth(i).innerText()).trim();
        if (val && !text.includes('Chọn kho')) {
            await warehouse.selectOption(val);
            pickedWh = true;
            break;
        }
    }
    if (!pickedWh) {
        throw new Error('Không có kho trong dropdown — cần ít nhất 1 warehouse.');
    }

    // Shipping provider (PTGH) — needed for NetShip proxy path (carrier = Viettel/GHTK, gateway = netship).
    if (options.preferCarrier) {
        const carrierField = dialog.locator('.ps-order-field').filter({
            hasText: /Phương thức giao|Đơn vị giao|Shipping method|PTGH/i,
        }).first();
        const carrierSelect = carrierField.locator('select').first()
            .or(dialog.locator('.ps-order-right-panel select.form-control').nth(1));
        if (await carrierSelect.count()) {
            const prefer = options.preferCarrier instanceof RegExp
                ? options.preferCarrier
                : /Viettel|GHTK|GHN|J&T|Best|Ninja|Snappy|Ahamove|Grab/i;
            const opts = carrierSelect.locator('option');
            let picked = false;
            for (let i = 0; i < await opts.count(); i += 1) {
                const val = await opts.nth(i).getAttribute('value');
                const text = (await opts.nth(i).innerText()).trim();
                if (val && prefer.test(text)) {
                    await carrierSelect.selectOption(val);
                    picked = true;
                    break;
                }
            }
            if (!picked) {
                for (let i = 0; i < await opts.count(); i += 1) {
                    const val = await opts.nth(i).getAttribute('value');
                    const text = (await opts.nth(i).innerText()).trim();
                    if (val && !/thủ công|manual|--/i.test(text)) {
                        await carrierSelect.selectOption(val);
                        picked = true;
                        break;
                    }
                }
            }
            if (!picked) {
                throw new Error('Không chọn được PTGH (carrier) — cần ít nhất 1 đơn vị giao vận.');
            }
            await page.waitForTimeout(300);
        }
    }

    // Product: only real line rows (qty input), not footer totals / empty placeholder.
    const emptyHint = dialog.locator('.ps-empty-products');
    const qtyInputs = dialog.locator('table.ps-order-product-table tbody tr input.form-control.text-center');
    const hasLine = (await qtyInputs.count()) > 0
        && !(await emptyHint.isVisible().catch(() => false));

    if (!hasLine) {
        const base = dialog.locator('.ps-order-product-picker .ps-select').first();
        await base.locator('button.ps-select__control').click();
        const baseOpt = base.locator('button.ps-select__option').filter({ hasNotText: /^--/ }).first();
        await baseOpt.waitFor({ state: 'visible', timeout: 15_000 });
        await baseOpt.click();
        await page.waitForTimeout(500);

        const variant = dialog.locator('.ps-order-product-picker .ps-select').nth(1);
        const variantBtn = variant.locator('button.ps-select__control');
        if (await variantBtn.isEnabled().catch(() => false)) {
            await variantBtn.click();
            const vOpt = variant.locator('button.ps-select__option').filter({ hasNotText: /^--/ }).first();
            if (await vOpt.isVisible().catch(() => false)) await vOpt.click();
        }

        await expect(qtyInputs.first()).toBeVisible({ timeout: 10_000 });
    }

    const qty = dialog.locator('table.ps-order-product-table tbody tr input.form-control.text-center').first();
    await expect(qty).toBeVisible({ timeout: 10_000 });
    await qty.click();
    await qty.fill('1');
    await qty.dispatchEvent('input');
    await qty.dispatchEvent('change');
    await expect(qty).toHaveValue('1');

    const closeBtn = dialog.locator('.ps-order-actions button.btn-primary').filter({ hasText: 'Chốt đơn' });
    await expect(closeBtn).toBeEnabled();
    await closeBtn.click();

    const formError = dialog.locator('.ps-dialog-form-error').first();
    await Promise.race([
        expectSuccessToast(page, /Đã tạo và chốt đơn\.|Đã chốt đơn và sinh mã đơn\./i, 60_000),
        formError.waitFor({ state: 'visible', timeout: 8_000 }).then(async () => {
            throw new Error(`Chốt đơn bị chặn: ${await formError.innerText()}`);
        }),
    ]);

    await expect(dialog).toBeHidden({ timeout: 30_000 });
}

async function pickGeoSelect(dialog: Locator, label: RegExp) {
    const field = dialog.locator('.ps-order-field').filter({ hasText: label }).first();
    if (!(await field.count())) return;
    const select = field.locator('.ps-select').first();
    const control = select.locator('button.ps-select__control');
    if (!(await control.count()) || !(await control.isEnabled().catch(() => false))) return;

    const current = (await control.innerText()).trim();
    if (current && !current.includes('Chọn') && !current.startsWith('--')) return;

    await control.click();
    const opt = select.locator('button.ps-select__option').filter({ hasNotText: /^--|^$/ }).first();
    if (await opt.isVisible({ timeout: 5_000 }).catch(() => false)) {
        await opt.click();
        await dialog.page().waitForTimeout(350);
    } else {
        await dialog.page().keyboard.press('Escape');
    }
}

export { waitUiReady };
