import {
    test,
    expect,
    DEMO,
    loginAs,
    mockCustomer,
    waitUiReady,
} from '../helpers/fixtures';
import { fillAndCloseNewSaleOrder } from '../helpers/saleOrder';

/**
 * Sale workspace — FAB Tạo đơn → SaleOrderDialog → Chốt đơn.
 * Admin path: /admin/sales/workspace
 * Success toast: "Đã tạo và chốt đơn."
 */
test.describe('03 — Sale tạo & chốt đơn', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('Tạo đơn → điền khách/SP/kho → Chốt đơn → toast thành công', async ({ page }) => {
        const customer = mockCustomer();

        await loginAs(page, DEMO.admin);
        await page.goto('/admin/sales/workspace', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        await fillAndCloseNewSaleOrder(page, customer);
        await expect(page.locator('body')).toContainText(customer.phone, { timeout: 20_000 });
    });
});
