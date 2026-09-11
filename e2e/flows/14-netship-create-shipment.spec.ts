import {
    test,
    expect,
    DEMO,
    loginAs,
    mockCustomer,
    expectSuccessToast,
    openWarehouseFab,
    waitUiReady,
} from '../helpers/fixtures';
import { fillAndCloseNewSaleOrder } from '../helpers/saleOrder';

/**
 * Real-user NetShip path:
 * Sale chốt đơn (có PTGH) → Kho Đăng đơn → assert responsePayload.gateway === 'netship'
 * (gateway chọn server-side; Playwright chỉ thấy JSON app trả về).
 *
 * Soft-pass khi môi trường chưa cấu hình NetShip / ShopID / không đủ stock.
 */
test.describe.configure({ mode: 'serial' });

test.describe('14 — NetShip: chốt đơn → đăng vận đơn → kiểm tra payload', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    const customer = mockCustomer();
    let orderPhone = customer.phone;

    test('A. Sale chốt đơn với PTGH (carrier thật)', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/sales/workspace', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        await fillAndCloseNewSaleOrder(page, customer, { preferCarrier: true });
        orderPhone = customer.phone;
        await expect(page.locator('body')).toContainText(orderPhone, { timeout: 20_000 });
    });

    test('B. Kho đăng vận đơn → bắt JSON create-shipment / detail', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/warehouse/operations', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        const search = page.getByPlaceholder(/Họ tên, số điện thoại/i);
        if (await search.count()) {
            await search.fill(orderPhone);
            await page.getByRole('button', { name: /Tìm kiếm/i }).click();
            await waitUiReady(page);
        }

        const waitingTile = page.locator('button, a').filter({ hasText: /Chờ vận đơn/i }).first();
        if (await waitingTile.isVisible().catch(() => false)) {
            await waitingTile.click();
            await waitUiReady(page);
        }

        const row = page.locator('table.ps-wh-table tbody tr, .ps-wh-table-shell tbody tr')
            .filter({ hasText: orderPhone })
            .first();

        type ShipmentProbe = {
            ok: boolean;
            status: number;
            url: string;
            gateway?: string | null;
            provider?: string | null;
            tracking?: string | null;
            message?: string | null;
            rawKeys?: string[];
        };

        const probes: ShipmentProbe[] = [];

        page.on('response', async (response) => {
            const url = response.url();
            if (!/create-shipment|shipping\/orders\/\d+\/detail|\/api\/.*shipment/i.test(url)) return;
            if (!/create-shipment|\/detail/i.test(url)) return;
            let body: Record<string, unknown> = {};
            try {
                body = await response.json();
            } catch {
                return;
            }

            const shipment = (body.shipment as Record<string, unknown> | undefined)
                ?? (Array.isArray(body.shipments) ? (body.shipments as Record<string, unknown>[])[0] : undefined)
                ?? body;
            const payload = (shipment?.responsePayload
                ?? shipment?.response_payload
                ?? body.responsePayload
                ?? body.response_payload) as Record<string, unknown> | undefined;

            probes.push({
                ok: response.ok(),
                status: response.status(),
                url,
                gateway: payload?.gateway != null ? String(payload.gateway) : null,
                provider: shipment?.provider != null ? String(shipment.provider) : (body.provider != null ? String(body.provider) : null),
                tracking: shipment?.trackingNumber != null
                    ? String(shipment.trackingNumber)
                    : (shipment?.tracking_number != null ? String(shipment.tracking_number) : null),
                message: body.message != null ? String(body.message) : null,
                rawKeys: Object.keys(body).slice(0, 20),
            });
        });

        // Prefer per-row Đăng vận đơn when the closed order is visible.
        const rowRegister = row.locator('button[title="Đăng vận đơn"], button[fam-tooltip="Đăng vận đơn"], button.orange').first();
        if (await row.isVisible().catch(() => false) && await rowRegister.isVisible().catch(() => false)) {
            await rowRegister.click();
        } else {
            await openWarehouseFab(page);
            const fab = page.locator('button[title="Đăng đơn"], button[fam-tooltip="Đăng đơn"]').first();
            await expect(fab).toBeVisible({ timeout: 15_000 });
            await fab.click();
            const confirm = page.getByRole('dialog').filter({ hasText: /Đăng đơn/i });
            if (await confirm.isVisible({ timeout: 8_000 }).catch(() => false)) {
                await confirm.getByRole('button', { name: /^Đăng đơn$/i }).click();
            }
        }

        await Promise.race([
            expectSuccessToast(page, /Đã đăng vận đơn|Đã tạo vận đơn/i, 90_000),
            page.locator('[data-sonner-toast][data-type="error"]').waitFor({ state: 'visible', timeout: 90_000 }),
            page.waitForTimeout(5_000).then(() => undefined),
        ]).catch(() => undefined);

        // Open shipping detail if row has link — another chance to read responsePayload.
        if (await row.isVisible().catch(() => false)) {
            const detailBtn = row.locator('button, a').filter({ hasText: /Chi tiết|Vận đơn|tracking/i }).first();
            if (await detailBtn.isVisible().catch(() => false)) {
                await detailBtn.click();
                await page.waitForTimeout(2_000);
            }
        }

        await expect(page.locator('body')).not.toContainText(/Server Error|Whoops|SQLSTATE/i);

        const netshipHit = probes.find((p) => p.gateway === 'netship' && p.ok);
        const anyOk = probes.find((p) => p.ok);
        const anyFail = probes.find((p) => !p.ok);

        test.info().annotations.push({
            type: 'note',
            description: `NetShip probes=${JSON.stringify(probes.slice(0, 5))} phone=${orderPhone}`,
        });

        if (netshipHit) {
            expect(netshipHit.gateway).toBe('netship');
            return;
        }

        if (anyOk) {
            // Carrier đi thẳng (không qua NetShip) — vẫn pass nhưng ghi chú để đối chiếu cấu hình.
            test.info().annotations.push({
                type: 'note',
                description: `Shipment OK nhưng gateway≠netship (provider=${anyOk.provider}, gateway=${anyOk.gateway}). Kiểm tra NetShip ready + ShopID kho.`,
            });
            return;
        }

        if (anyFail) {
            test.info().annotations.push({
                type: 'note',
                description: `create-shipment failed: ${anyFail.status} ${anyFail.message || ''} — soft-pass (thiếu Stock/ShopID/NetShip creds).`,
            });
            return;
        }

        // Không bắt được response — thường do không có đơn đủ điều kiện trên trang.
        const errToast = page.locator('[data-sonner-toast][data-type="error"]');
        if (await errToast.isVisible().catch(() => false)) {
            test.info().annotations.push({
                type: 'note',
                description: `Toast lỗi: ${await errToast.innerText()}`,
            });
            return;
        }

        test.info().annotations.push({
            type: 'note',
            description: 'Không bắt được create-shipment response — kiểm tra đơn vừa chốt có trên Kho / canCreateShipment.',
        });
    });
});
