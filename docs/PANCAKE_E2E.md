# Pancake — hướng dẫn cấu hình & test E2E (dev → thấy kết quả trên UI)

Tài liệu này mô tả **từ bước 0** (môi trường + credential) đến **kết thúc** (đơn/lead hiển thị trên workspace, inbound event processed). Kiến trúc tích hợp chi tiết: [INTEGRATIONS.md](./INTEGRATIONS.md).

## Phạm vi E2E được cover ở đây

| Luồng | Mô tả ngắn |
| --- | --- |
| **A — Webhook đơn POS** | Pancake POS gửi đơn → queue → lead + order + `pancake_sync_records` |
| **B — Đồng bộ đơn ngay / polling** | Kéo đơn từ Open API khi shop đã có đơn trên POS |
| **C — Kiểm tra kết nối API** | Nút trên UI (không tạo lead mẫu) |
| **D — Extension / chat Pages** | Chỉ liệt kê bước tham chiếu; test riêng nếu bật Pages webhook |

Luồng **inbox chat toàn trang** (khách nhắn không gắn đơn) **không** được coi là CRM inbox đầy đủ — webhook tin nhắn chỉ cache khi đã khớp đơn/hội thoại.

---

## 0. Chuẩn bị môi trường local

### 0.1 Chạy stack dev

```bash
composer install && pnpm install
cp .env.example .env   # nếu chưa có
php artisan key:generate
php artisan migrate --seed
composer run dev       # server + Vite + queue worker + …
```

- App mặc định dev: **http://127.0.0.1:8000** (khớp với URL webhook in trên UI).
- Trong `.env`, đặt `APP_URL=http://127.0.0.1:8000` (tránh `http://localhost` nếu Pancake/curl gọi `127.0.0.1`).
- Queue Pancake: Redis, queue `pancake-orders` (xử lý `ProcessLeadIngestionJob`).
- **Sau khi sửa code PHP (observer, import, job):** restart `composer dev` để worker nạp code mới.

### 0.2 Tài khoản demo

| Vai trò | Email | Mật khẩu |
| --- | --- | --- |
| Super Admin | `superadmin@saleops.local` | `password` |

Đăng nhập → chọn **shop** trên header (ví dụ *Main shop*). Đơn Pancake phải có `shop_id` khớp shop đang chọn mới hiện trên **Sales workspace** (import mới tự gán shop mặc định của company).

### 0.3 Biến môi trường Pancake (tham chiếu)

Lấy từ Pancake POS / Pages (không commit giá trị thật vào git):

| Biến `.env` | Ý nghĩa |
| --- | --- |
| `PANCAKE_SHOP_ID` | ID shop trên POS (`pos.pages.fm`) |
| `PANCAKE_API_KEY` | Open API key (POS) |
| `PANCAKE_PAGE_ID` | Page ID chat (Pages), dạng `tl_…` |
| `PANCAKE_PAGE_ACCESS_TOKEN` | Token gọi Page API (query `page_access_token`) |

Resolver đọc **DB connection** trước, fallback **`.env`**. Nên **Save** trên UI để doctor và form đồng bộ (chỉ `.env` → doctor có thể báo thiếu page token dù API vẫn chạy).

---

## 1. Cấu hình trên SaleOps (UI)

### 1.1 Mở hub tích hợp

1. Đăng nhập Super Admin.
2. Vào **Quản trị → Kết nối nền tảng**: [`/admin/integrations`](http://127.0.0.1:8000/admin/integrations).
3. Filter **POS / Omnichannel** (hoặc tìm *Pancake*).

> **Lưu ý menu:** Pancake nằm ở **Integrations hub**, không phải mục đối tác vận chuyển menu 1.4.

### 1.2 Bật và điền credential

| Trường UI | Ghi chú |
| --- | --- |
| Switch **Pancake POS / Extension** | Bật ON |
| **Webhook URL** | Copy sang Pancake POS (mục 2) |
| **Shop ID** | Trùng shop POS |
| **API / webhook key** | `PANCAKE_API_KEY` |
| **Page ID để chat** | `PANCAKE_PAGE_ID` (luồng chat sau này) |
| **Facebook page access token** | `PANCAKE_PAGE_ACCESS_TOKEN` (Page API) |
| **Allowed Shop IDs / Page IDs** | Tuỳ chọn; nếu set thì webhook phải khớp |

Bấm **Save configuration**.

### 1.3 Kiểm tra kết nối API (không phải webhook mẫu)

- Nút **Send test webhook** trên card Pancake → thực tế gọi **`Pancake API test()`** (POS).
- Kỳ vọng: toast *Kết nối Pancake POS thành công*.
- **Không** tạo lead/đơn mẫu (khác Facebook/TikTok trên cùng trang).

### 1.4 Webhook tin nhắn Pages (tuỳ chọn)

Trên card có dòng **Webhook tin nhắn Pages**:

```text
POST {APP_URL}/api/v1/pancake/messages/{webhook_token}
```

Cấu hình riêng trên Pancake Pages (thường cần Pancake bật tính năng). Dùng cho chat gắn đơn, không thay inbox CRM đầy đủ.

---

## 2. Cấu hình trên Pancake POS (webhook đơn thật)

1. Đăng nhập [pos.pages.fm](https://pos.pages.fm).
2. **Cài đặt → Nâng cao → Kết nối bên thứ 3 (Webhook/API)**.
3. Dán **Webhook URL** từ SaleOps (bước 1.2).
4. Tích **Đơn hàng — Tạo mới, Cập nhật** (hoặc tương đương).
5. Lưu trên Pancake.

Khi có đơn mới/cập nhật trên POS, Pancake `POST` vào SaleOps → job queue → import.

---

## 3. Test E2E inbound (simulated webhook — dev)

Dùng khi chưa có đơn trên POS hoặc cần test nhanh.

### 3.1 Lấy `webhook_token`

- Trên UI: field **Webhook URL** dạng  
  `…/api/v1/webhooks/pancake/{webhook_token}`  
- Hoặc DB: bảng `integration_connections`, `platform = pancake`.

### 3.2 Gửi payload mẫu

**Bắt buộc:** `id` (mã đơn Pancake) **unique** mỗi lần test; `shop_id` khớp cấu hình.

```bash
export WEBHOOK_TOKEN="p4FpxVM0mcDlvPB1E2nWizzyemhNm9E4"   # thay bằng token tenant bạn
export SHOP_ID="1943159632"                              # shop POS của bạn

curl -sS -X POST "http://127.0.0.1:8000/api/v1/webhooks/pancake/${WEBHOOK_TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{
    \"id\": \"E2E-$(date +%s)\",
    \"shop_id\": \"${SHOP_ID}\",
    \"customer_name\": \"Khách E2E Pancake\",
    \"customer_phone\": \"0918999888\",
    \"conversation\": {
      \"id\": \"tl_8298318621_test\",
      \"page_id\": \"tl_8298318621\"
    },
    \"items\": [{
      \"product_name\": \"Gói test E2E\",
      \"quantity\": 1,
      \"unit_price\": 350000
    }]
  }"
```

**Kỳ vọng HTTP:** `202` + `"queued": true`.

### 3.3 Queue xử lý

- Với `composer dev`: đợi vài giây, log queue có  
  `ProcessLeadIngestionJob … DONE`.
- Nếu job **FAIL** sau khi vừa sửa code: restart `composer dev`.

### 3.4 Kiểm tra backend (CLI)

```bash
php artisan tinker --execute="
\$ev = DB::table('inbound_events')->where('channel','pancake')->orderByDesc('id')->first(['id','status','error_message']);
echo json_encode(\$ev, JSON_PRETTY_PRINT);
"
```

| `status` | Ý nghĩa |
| --- | --- |
| `processed` | Luồng E2E inbound **thành công** |
| `failed` | Xem `error_message`; thường do job lỗi hoặc worker cũ |
| `queued` | Worker chưa chạy / Redis down |

Lead/order:

```bash
php artisan tinker --execute="
\$l = App\Models\LeadIngestion::where('platform','pancake')->orderByDesc('id')->first(['id','external_id','company_id','order_id','customer_phone','shop_id']);
echo json_encode(\$l);
"
```

Kỳ vọng: `company_id` có giá trị, `order_id` not null, `shop_id` not null (đơn mới sau fix import).

---

## 4. Test E2E đồng bộ từ Open API

### 4.1 Trên UI

1. [`/admin/integrations`](http://127.0.0.1:8000/admin/integrations) → Pancake.
2. **Đồng bộ đơn ngay**.

| Toast | Ý nghĩa |
| --- | --- |
| *Không tìm thấy đơn hàng mới…* | API OK nhưng shop **0 đơn** (hoặc đã sync hết) |
| Thông báo đã kéo N đơn | Có đơn trên POS → import tương tự webhook |

### 4.2 CLI

```bash
php artisan pancake:sync-orders --limit=20
```

API key lấy từ connection hoặc `.env` (`PancakeConnectionResolver`).

---

## 5. Xác nhận trên UI (kết thúc E2E)

Thay `0918999888` bằng SĐT bạn vừa gửi trong curl.

### 5.1 System monitor — inbound

URL: [`/admin/system-monitor?tab=events`](http://127.0.0.1:8000/admin/system-monitor?tab=events)

- Tab **Inbound events**.
- Event mới: **Processed** (Failed = lần test cũ hoặc lỗi job).
- Filter **Status: Processed** để chỉ xem thành công.

### 5.2 Sales workspace — thấy đơn sale

URL (gợi ý):

```text
/admin/sales/workspace?search=0918999888&operation_stage=all
```

- Ô **Name, phone** → Search.
- Tab **New customer** hoặc **All** có count `(1/1)`.
- Cột khách: tên + SĐT; nguồn **Pancake**; ghi chú/gói từ payload.

**Nếu bảng trống:**

1. Header đang chọn shop khác → chọn đúng shop hoặc shop “all” (nếu có).
2. Đơn thiếu `shop_id` (data cũ) → chạy lại webhook sau fix hoặc gán shop trong DB dev.
3. Khoảng ngày filter — mặc định thường 7 ngày gần nhất; đơn mới nằm trong range.

### 5.3 Phân bổ data (lead routing)

URL: [`/admin/leads?platform=pancake`](http://127.0.0.1:8000/admin/leads?platform=pancake)

- Màn **phân bổ / reassign data**, không phải danh sách lead dạng CRM.
- Filter **Pancake** + Search SĐT để thấy contact trong pool phân số.

### 5.4 Dialog đơn & chat (khi có `conversation_id`)

1. Trên workspace → **Update order** / mở dialog đơn.
2. Tab tin nhắn Pancake (nếu bật Page token + mapping user).
3. Doctor: `php artisan pancake:doctor --json`.

---

## 6. Luồng phụ (không bắt buộc cho E2E đơn cơ bản)

### 6.1 Chrome Extension

- Build/load: `extensions/pancake-saleops`.
- Endpoint SaleOps: `POST /api/v1/pancake/extension/orders` + Bearer Sanctum (`POST /api/v1/auth/token`).
- Gán sale theo user đang đăng nhập extension.

### 6.2 Outbound (SaleOps → Pancake)

- Khi đơn có `tracking_number` / `delivery_status` đổi → `SyncOrderToPancakeJob`.
- Test riêng sau khi inbound ổn.

### 6.3 Automated tests (CI/local)

```bash
./vendor/bin/phpunit tests/Unit/Pancake/
# Feature (cần DB test / pdo_sqlite hoặc Postgres test):
php artisan test --filter=Pancake
```

---

## 7. Troubleshooting nhanh

| Triệu chứng | Nguyên nhân thường gặp | Hướng xử lý |
| --- | --- | --- |
| HTTP 202 nhưng không có đơn | Queue không chạy / job FAIL | Xem log `[queue]` trong `composer dev`; restart dev |
| `inbound_events` **failed**, lỗi `cache_locks` / transaction | Job báo cáo trong transaction import (đã fix: dispatch fact **after commit**) | Pull code mới + restart worker |
| `inbound_events` **failed**, `ON DUPLICATE KEY` (Postgres) | SQL aggregate MySQL trên PG dev (đã fix aggregator) | Pull code mới |
| Workspace **No data**, DB có order | `shop_id` null + header chọn 1 shop | Import mới có `ensureShopScope`; backfill dev hoặc chọn shop đúng |
| **Send test webhook** OK nhưng không có lead | Đúng thiết kế — chỉ test API POS | Dùng curl mục 3 hoặc đơn thật POS |
| **Đồng bộ đơn ngay** = 0 đơn | Shop POS trống | Tạo đơn trên POS hoặc simulated webhook |
| Doctor `has_page_token: false` | Token chỉ trong `.env`, chưa Save UI | Save integrations hoặc bỏ qua nếu chat chưa test |
| Lead `company_id` null (data cũ) | Import trước khi set tenant/context | Test lại với webhook mới |

Lệnh chẩn đoán:

```bash
php artisan pancake:doctor --json
php artisan horizon:status   # nếu dùng Horizon thay queue worker dev
```

---

## 8. Checklist E2E (in một trang)

- [ ] `composer run dev` đang chạy, `APP_URL` = `http://127.0.0.1:8000`
- [ ] `/admin/integrations` — Pancake ON, credential Save (hoặc `.env` + shop_id đúng)
- [ ] **Send test webhook** → toast kết nối POS OK
- [ ] Webhook URL dán trên Pancake POS **hoặc** curl mục 3 → **202**
- [ ] Queue: `ProcessLeadIngestionJob` **DONE**
- [ ] `/admin/system-monitor?tab=events` → event **Processed**
- [ ] `/admin/sales/workspace?search={phone}` → 1 dòng khách Pancake
- [ ] (Tuỳ chọn) **Đồng bộ đơn ngay** khi POS đã có đơn
- [ ] (Tuỳ chọn) `pancake:doctor --json` + chat tab trên dialog đơn

---

## 9. Tài liệu liên quan

- [INTEGRATIONS.md](./INTEGRATIONS.md) — kiến trúc inbound/outbound/chat
- [OPERATIONS.md](./OPERATIONS.md) — Sales workspace 4.1, shop scope
- [DEPLOY.md](./DEPLOY.md) — production URL webhook (HTTPS, không dùng `127.0.0.1`)
