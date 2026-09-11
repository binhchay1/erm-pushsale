import type { Locator, Page } from '@playwright/test';
import { expect } from '@playwright/test';

/** Wait until Inertia/XHR settle enough for UI actions. */
export async function waitUiReady(page: Page) {
    await page.waitForLoadState('domcontentloaded');
    await page.waitForLoadState('networkidle', { timeout: 30_000 }).catch(() => undefined);
}

/** Sonner success toast (Toaster in AppLayout). */
export async function expectSuccessToast(page: Page, text: string | RegExp, timeout = 30_000) {
    const toast = page.locator('[data-sonner-toast][data-type="success"]').filter({ hasText: text }).first();
    await expect(toast).toBeVisible({ timeout });
}

/** Field block under SaleOrderDialog: label text → input/textarea/select. */
export function orderField(dialog: Locator, label: string | RegExp) {
    return dialog.locator('.ps-order-field').filter({ hasText: label }).first();
}

export async function fillOrderText(dialog: Locator, label: string | RegExp, value: string) {
    const field = orderField(dialog, label);
    const input = field.locator('input.form-control, textarea.form-control').first();
    await input.waitFor({ state: 'visible' });
    await input.fill(value);
}

/**
 * PushsaleSelect (SaleOrderDialog): `.ps-select` / `.ps-order-search-select`
 * Opens control → optional search → clicks option by visible label.
 */
export async function selectPushsaleOption(
    root: Locator,
    placeholderOrLabel: string | RegExp,
    optionText: string | RegExp,
    searchText?: string,
) {
    const select = root.locator('.ps-select').filter({ hasText: placeholderOrLabel }).first();
    await select.locator('button.ps-select__control').click();
    const menu = select.locator('.ps-select__menu');
    await menu.waitFor({ state: 'visible' });

    const search = menu.locator('input.ps-select__search');
    if (await search.count()) {
        await search.fill(searchText ?? (typeof optionText === 'string' ? optionText : ''));
    }

    const option = menu.locator('button.ps-select__option').filter({ hasText: optionText }).first();
    await option.waitFor({ state: 'visible', timeout: 15_000 });
    await option.click();
}

/**
 * Manual lead page DDL: `.ps-ddl` opened via `.ps-ddl-display-text`
 */
export async function selectManualDdl(
    form: Locator,
    placeholder: string,
    optionText: string | RegExp,
    searchText?: string,
) {
    const ddl = form.locator('.ps-ddl').filter({ hasText: placeholder }).first();
    await ddl.locator('button.ps-ddl-display-text').click();
    const search = ddl.locator('input.ps-ddl-search-box');
    await search.waitFor({ state: 'visible' });
    if (searchText || typeof optionText === 'string') {
        await search.fill(searchText ?? String(optionText));
    }
    const item = ddl.locator('button.ps-ddl-result-item').filter({ hasText: optionText }).first();
    await item.waitFor({ state: 'visible', timeout: 15_000 });
    await item.click();
}

/** Native <select class="form-control"> by surrounding field label. */
export async function selectNativeByLabel(dialog: Locator, label: string | RegExp, optionLabel: string | RegExp) {
    const field = orderField(dialog, label);
    const select = field.locator('select.form-control').first();
    await select.waitFor({ state: 'visible' });
    const options = select.locator('option');
    const count = await options.count();
    for (let i = 0; i < count; i += 1) {
        const text = (await options.nth(i).innerText()).trim();
        const match = typeof optionLabel === 'string' ? text.includes(optionLabel) : optionLabel.test(text);
        if (match && (await options.nth(i).getAttribute('value'))) {
            await select.selectOption({ index: i });
            return;
        }
    }
    // Fallback: first non-empty value
    await select.selectOption({ index: 1 });
}

/** ConfirmDialog from useConfirm — button text = confirmLabel. */
export async function confirmDialog(page: Page, confirmLabel: string | RegExp) {
    const dialog = page.getByRole('dialog').filter({ hasText: confirmLabel }).first();
    await dialog.waitFor({ state: 'visible', timeout: 15_000 });
    await dialog.getByRole('button', { name: confirmLabel }).click();
}

export async function openWarehouseFab(page: Page) {
    const fab = page.locator('nav.ps-wh-floating-actions, nav.action-container.ps-wh-floating-actions').first();
    await fab.waitFor({ state: 'visible', timeout: 20_000 });
    await fab.hover();
    const toggle = page.locator('#warehouseMenuToggle, button.ps-wh-main-action').first();
    if (await toggle.count()) {
        await toggle.click().catch(() => undefined);
    }
    await fab.locator('button.ps-wh-action-button, button.n-button').first().waitFor({ state: 'visible', timeout: 10_000 });
}
