import {
    test,
    expect,
    DEMO,
    loginAs,
    openWarehouseFab,
    selectNativeByLabel,
    selectPushsaleOption,
    waitUiReady,
} from '../helpers/fixtures';

/**
 * NetShip end-to-end on real data: landing webhook → sale chốt đơn → kho đăng vận đơn.
 *
 * Unlike 14-*, this starts from an inbound landing packet (not a manually created order)
 * and asserts hard on `responsePayload.gateway === 'netship'` plus a NetShip order id.
 *
 * Chạy: E2E_BASE_URL=https://salesloop.vn npx playwright test e2e/flows/19-netship-landing-to-shipment.spec.ts --project=demo
 * Đơn tạo ra là đơn test thật trên NetShip — phải hủy + xóa sau khi chạy.
 */
test.describe.configure({ mode: 'serial' });

const CONNECTION_TOKEN = process.env.E2E_LANDING_CONNECTION
    ?? '8vc1cybgaz8hsoiebvnyvw0xhu7oq2qnoog0n1bz';
const SOURCE_TOKEN = process.env.E2E_LANDING_SOURCE
    ?? 'btunz3hvhapezaxxkqsgme1sr2zni3sq';

/** Đơn đã có sẵn từ packet trước thì tái sử dụng, khỏi tạo thêm rác trên production. */
const PHONE = process.env.E2E_NETSHIP_PHONE ?? '0865000917';
const NAME = 'E2E NETSHIP TEST';

const WAREHOUSE = process.env.E2E_WAREHOUSE_NAME ?? 'Kho Hòa Bình';

const ADDRESS = {
    street: '884/26 Lê Đức Thọ',
    province: 'Thành phố Hồ Chí Minh',
    district: 'Quận Gò Vấp',
    ward: 'Phường 15',
};

type ShipmentProbe = {
    ok: boolean;
    status: number;
    gateway: string | null;
    provider: string | null;
    tracking: string | null;
    externalCode: string | null;
    netshipOrderId: string | null;
    message: string | null;
};

let createdExternalCode = '';
let createdOrderCode = '';

test.describe('19 — NetShip: landing webhook → chốt đơn → vận đơn', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('A. Landing webhook tạo lead + đơn chưa chốt', async ({ request }) => {
        // Chạy lại trên production sẽ đẻ thêm lead trùng số phải dọn — cho phép bỏ qua.
        test.skip(process.env.E2E_SKIP_INTAKE === '1', 'Đơn test đã có sẵn từ packet trước.');

        const response = await request.post(
            `/api/v1/landing-connections/${CONNECTION_TOKEN}/sources/${SOURCE_TOKEN}/submit`,
            {
                headers: { Accept: 'application/json' },
                data: {
                    submission_id: `e2e-netship-${Date.now()}`,
                    name: NAME,
                    phone: PHONE,
                    address: `${ADDRESS.street}, ${ADDRESS.ward}, ${ADDRESS.district}, ${ADDRESS.province}`,
                    message: 'Đơn test E2E NetShip - sẽ được hủy và xóa sau khi kiểm tra',
                    quantity: 1,
                    utm_source: 'e2e-test',
                    utm_campaign: 'e2e-netship',
                },
            },
        );

        expect(response.status(), await response.text()).toBe(201);
        const body = await response.json();
        expect(body.ok).toBe(true);
        // Chạy lại cùng số điện thoại sẽ gộp vào đơn cũ — vẫn hợp lệ, không sinh thêm rác.
        expect(body.status).toMatch(/^(processed|duplicate)$/);
    });

    test('B. Sale mở đơn từ lead và chốt', async ({ page }) => {
        await loginAs(page, DEMO.admin);
        await page.goto('/admin/sales/workspace', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        await page.getByPlaceholder('Họ tên, số điện thoại').fill(PHONE);
        await page.locator('.ps-sale-search-wrap button.btn-primary').click();
        await waitUiReady(page);

        await page.locator('button.ps-cell-action[title="Cập nhật đơn"]').first().click();

        const dialog = page.getByRole('dialog').filter({ hasText: 'Cập nhật đơn' }).first();
        await expect(dialog).toBeVisible({ timeout: 20_000 });
        await expect(dialog.locator('input.form-control').first()).toHaveValue(NAME);

        // Tiêu đề đổi thành mã đơn khi đơn đã chốt — chạy lại thì không chốt nữa.
        const alreadyClosed = /PS\d+PS/.test(await dialog.locator('.pushsale-dialog-title, h2').first().innerText());
        if (alreadyClosed) {
            await dialog.getByRole('button', { name: 'Đóng' }).click();
            return;
        }

        await selectPushsaleOption(dialog, 'Chọn Tỉnh/TP', ADDRESS.province, 'Hồ Chí Minh');
        await selectPushsaleOption(dialog, 'Chọn Quận/Huyện', ADDRESS.district, 'Gò Vấp');
        await selectPushsaleOption(dialog, 'Chọn Phường/Xã', ADDRESS.ward, 'Phường 15');

        // Carrier nghiệp vụ — NetShip vẫn là gateway, hãng thật do NetShip chọn.
        await selectNativeByLabel(dialog, 'Phương thức giao hàng', 'Viettel Post');
        // Select kho không nằm trong `.ps-order-field`, và tên kho trong DB ở dạng Unicode
        // tổ hợp nên so khớp chuỗi không ăn — tìm theo option có value.
        const warehouseOption = page.locator('option').filter({ hasText: /^Kho\s/ });
        const warehouseSelect = dialog.locator('select').filter({ has: warehouseOption }).first();
        await warehouseSelect.selectOption({ index: 1 });
        await expect(warehouseSelect.locator('option:checked')).toHaveText(/^Kho\s/);

        const qty = dialog.locator('table tbody tr input.form-control.text-center').first();
        await qty.fill('1');
        await qty.blur();
        await expect(qty).toHaveValue('1');

        await dialog.getByRole('button', { name: 'Chốt đơn' }).click();

        const toast = page.locator('[data-sonner-toast]').first();
        await toast.waitFor({ state: 'visible', timeout: 60_000 });
        expect(await toast.innerText()).toMatch(/Đã tạo và chốt đơn|Đã chốt đơn/i);
        await expect(dialog).toBeHidden({ timeout: 30_000 });
    });

    test('C. Kho đăng vận đơn qua NetShip', async ({ page }) => {
        const probes: ShipmentProbe[] = [];

        page.on('response', async (response) => {
            if (!/create-shipment/i.test(response.url())) return;
            const body = await response.json().catch(() => null);
            if (!body) return;
            const shipment = (body.shipment ?? body) as Record<string, any>;
            const payload = (shipment.responsePayload ?? shipment.response_payload ?? {}) as Record<string, any>;
            probes.push({
                ok: response.ok(),
                status: response.status(),
                gateway: payload.gateway ?? null,
                provider: shipment.provider ?? null,
                tracking: shipment.trackingNumber ?? shipment.tracking_number ?? null,
                externalCode: payload.netship_external_code ?? payload.netshipExternalCode ?? null,
                netshipOrderId: payload.netship_order_id ?? null,
                message: body.message ?? null,
            });
        });

        await loginAs(page, DEMO.admin);
        await page.goto('/admin/warehouse/operations', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        await page.getByPlaceholder(/Họ tên, số điện thoại/i).fill(PHONE);
        await page.locator('button.btn-primary').filter({ hasText: 'Tìm kiếm' }).first().click();
        await waitUiReady(page);

        const row = page.locator('table.ps-wh-table tbody tr').filter({ hasText: PHONE }).first();
        await expect(row).toBeVisible({ timeout: 30_000 });

        const orderCode = (await row.locator('button.ps-wh-order-code').first().innerText()).trim();
        createdOrderCode = orderCode;

        // Mã đối soát là externalCode (chữ+số), không còn là id số của NetShip.
        const trackingButton = row.locator('button.item-mdgv').first();
        const existingTracking = (await trackingButton.count()) ? (await trackingButton.innerText()).trim() : '';
        if (/^[A-Za-z0-9]{6,}$/.test(existingTracking)) {
            createdExternalCode = existingTracking;
            test.info().annotations.push({ type: 'netship', description: `đã đăng: ${existingTracking}` });
            return;
        }

        await row.locator('input[type="checkbox"]').first().check();

        await openWarehouseFab(page);
        await page.locator('button[title="Đăng đơn"], button[fam-tooltip="Đăng đơn"]').first().click();

        const confirm = page.getByRole('dialog').filter({ hasText: 'Đăng đơn' });
        if (await confirm.isVisible({ timeout: 8_000 }).catch(() => false)) {
            await confirm.getByRole('button', { name: /^Đăng đơn$/ }).click();
        }

        await expect
            .poll(() => probes.length, { timeout: 90_000, message: 'không bắt được response create-shipment' })
            .toBeGreaterThan(0);

        test.info().annotations.push({ type: 'netship', description: JSON.stringify(probes) });

        const created = probes.find((probe) => probe.ok);
        expect(created, `create-shipment lỗi: ${JSON.stringify(probes)}`).toBeTruthy();
        expect(created!.gateway).toBe('netship');
        expect(created!.netshipOrderId).toBeTruthy();
        expect(created!.externalCode).toMatch(/^[A-Za-z0-9]{6,}$/);
        expect(created!.tracking).toBe(created!.externalCode);
        createdExternalCode = created!.externalCode!;
    });

    test('D. Kế toán upload file đối soát NetShip và khớp externalCode', async ({ page }) => {
        expect(createdExternalCode, 'chưa có externalCode từ bước đăng đơn').toMatch(/^[A-Za-z0-9]{6,}$/);

        const csv = [
            'Đối soát,AUTO-E2E',
            'Khách hàng,VIỆT THÀNH SHOP',
            'STT,Mã đơn,Người nhận,SDT người nhận,Trạng thái,Giá trị đơn hàng,COD Gốc,COD Sau,Phí Ship,Tổng đối soát',
            `1,${createdExternalCode},${NAME},${PHONE},Giao hàng thành công,10000,10000,10000,16000,-6000`,
        ].join('\n');

        await loginAs(page, DEMO.admin);
        await page.goto('/admin/accounting', { waitUntil: 'domcontentloaded' });
        await waitUiReady(page);

        await page.getByPlaceholder(/Họ tên, số điện thoại/i).fill(PHONE);
        await page.locator('button.btn-primary').filter({ hasText: 'Tìm kiếm' }).first().click();
        await waitUiReady(page);

        await openWarehouseFab(page);
        await page.locator('button[title="Đối soát đơn bằng Excel"], button[fam-tooltip="Đối soát đơn bằng Excel"]').first().click();

        const dialog = page.getByRole('dialog').filter({ hasText: 'Cập nhật đối soát Excel' }).first();
        await expect(dialog).toBeVisible({ timeout: 20_000 });

        await dialog.locator('input[type="file"]').setInputFiles({
            name: 'netship-doi-soat.csv',
            mimeType: 'text/csv',
            buffer: Buffer.from(csv),
        });
        await dialog.getByRole('button', { name: 'Upload' }).click();

        const matched = dialog.locator('.ps-recon-excel-history-row').filter({ hasText: createdOrderCode || createdExternalCode }).first();
        await expect(matched).toBeVisible({ timeout: 20_000 });
        await expect(matched).toContainText('Đã đối soát');
        await expect(matched).not.toContainText('Chưa khớp đơn');
    });
});
