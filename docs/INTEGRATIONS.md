# Tích hợp nền tảng — ERM SaleOps

Hướng dẫn lấy API / webhook và cấu hình `.env` + API admin để thu lead từ Facebook, TikTok, Zalo, Landing.

## Kiến trúc nhanh

```
Nền tảng (FB/TikTok/...) 
    → POST/GET /api/v1/webhooks/{platform}
    → Driver chuẩn hóa payload
    → LeadIngestionService → Order mới
    → Event LeadIngested (Reverb realtime dashboard)
```

**URL webhook công khai** (cần HTTPS, dùng ngrok khi dev):

| Nền tảng | URL |
|----------|-----|
| Facebook | `{APP_URL}/api/v1/webhooks/facebook` |
| TikTok | `{APP_URL}/api/v1/webhooks/tiktok` |
| Zalo | `{APP_URL}/api/v1/webhooks/zalo` |
| Landing | `{APP_URL}/api/v1/webhooks/landing` hoặc `POST /api/v1/leads` (Bearer token) |
| Google | `{APP_URL}/api/v1/webhooks/google` |

---

## 1. Facebook Lead Ads

### Bước lấy credentials

1. Vào [Meta for Developers](https://developers.facebook.com/) → **My Apps** → tạo app loại **Business**.
2. Thêm sản phẩm **Webhooks** và **Marketing API** (hoặc Lead Ads).
3. Lấy **App ID** và **App Secret** (Settings → Basic).
4. Trang **Page** cần nhận lead: Settings → Advanced → lấy **Page Access Token** (quyền `leads_retrieval`, `pages_manage_metadata`, `pages_read_engagement`).
5. Tạo **Verify Token** tự đặt (chuỗi bất kỳ, ví dụ `saleops_fb_verify_2026`).

### Cấu hình Webhook trên Meta

1. Webhooks → Page → Subscribe field **`leadgen`**.
2. Callback URL: `https://your-domain.com/api/v1/webhooks/facebook`
3. Verify Token: trùng `FACEBOOK_VERIFY_TOKEN` trong `.env`.
4. Meta gửi GET challenge → app trả `hub_challenge` (đã xử lý trong `FacebookLeadDriver`).

### `.env`

```env
FACEBOOK_APP_ID=
FACEBOOK_APP_SECRET=
FACEBOOK_PAGE_ACCESS_TOKEN=
FACEBOOK_VERIFY_TOKEN=saleops_fb_verify_2026
```

### Ký POST webhook

Header `X-Hub-Signature-256` = `sha256=` + HMAC body bằng **App Secret**.

---

## 2. TikTok Lead Generation

1. [TikTok for Business Developers](https://business-api.tiktok.com/portal/docs) → tạo app.
2. Lấy **App ID**, **App Secret**, **Access Token** (OAuth hoặc tool sandbox).
3. Cấu hình webhook lead tới `https://your-domain.com/api/v1/webhooks/tiktok`.
4. Body JSON chuẩn generic: `phone`, `name`, `product`, `utm_*`.

### `.env`

```env
TIKTOK_APP_ID=
TIKTOK_APP_SECRET=
TIKTOK_ACCESS_TOKEN=
```

### Xác thực

Gửi header `X-SaleOps-Signature` = `hash_hmac('sha256', raw_body, INTEGRATION_WEBHOOK_SECRET)`  
hoặc `X-Api-Key` = secret đã lưu trong bảng `integration_connections.webhook_secret` (cập nhật qua API admin).

---

## 3. Zalo OA

1. [Zalo Developers](https://developers.zalo.me/) → tạo **Official Account App**.
2. Lấy **OA ID**, **App ID**, **Secret Key**; đổi **Access Token** qua OAuth OA.
3. Webhook URL trong Zalo OA console: `https://your-domain.com/api/v1/webhooks/zalo`.

### `.env`

```env
ZALO_OA_ID=
ZALO_APP_ID=
ZALO_SECRET_KEY=
ZALO_ACCESS_TOKEN=
```

Payload form lead map qua `GenericWebhookDriver` (phone, name, …).

---

## 4. Landing page / form riêng

Hai cách:

**A. Webhook (không cần đăng nhập user)**  
`POST /api/v1/webhooks/landing`  
Header: `X-Api-Key: {LANDING_API_KEY}`

**B. API có token Sanctum**  
`POST /api/v1/leads`  
Header: `Authorization: Bearer {token}`

Body ví dụ:

```json
{
  "name": "Nguyễn Văn A",
  "phone": "0901234567",
  "product": "Kem dưỡng X",
  "utm_source": "landing",
  "utm_campaign": "summer-2026"
}
```

### `.env`

```env
LANDING_API_KEY=your-random-secret-key
```

---

## 5. Google Ads Lead Form

Webhook key từ Google Ads → extension lead form.  
URL: `/api/v1/webhooks/google`  
Header `X-Api-Key` hoặc signature như TikTok.

```env
GOOGLE_LEADS_WEBHOOK_KEY=
```

---

## Cấu hình chung

```env
INTEGRATION_WEBHOOK_SECRET=change-me-long-random
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1
```

Chạy migration (cần DB driver hoạt động):

```bash
php artisan migrate
```

---

## API quản lý tích hợp (Admin)

1. Lấy token: `POST /api/v1/auth/token`  
   Body: `{ "email": "admin@saleops.local", "password": "password", "device_name": "postman" }`

2. Xem trạng thái: `GET /api/v1/integrations`  
   Header: `Authorization: Bearer {token}`

3. Bật / cập nhật secret DB (ưu tiên hơn .env cho webhook):

```http
PUT /api/v1/integrations/facebook
Content-Type: application/json

{
  "is_enabled": true,
  "verify_token": "saleops_fb_verify_2026",
  "webhook_secret": "optional-extra-secret",
  "credentials": {
    "page_access_token": "EAAx..."
  }
}
```

---

## Realtime sau khi có lead

Event `lead.ingested` broadcast trên channel `dashboard.admin` và `dashboard.sales` (cùng Reverb với dashboard stats).

Frontend có thể subscribe thêm trong `useRealtimeDashboard.js`:

```js
channel.listen('.lead.ingested', (e) => { /* toast + refresh */ })
```

---

## Dev local với ngrok

```bash
ngrok http 8000
# Đặt APP_URL=https://xxxx.ngrok-free.app
# Đăng ký URL webhook trên Meta/TikTok trỏ tới ngrok
```

Chi tiết endpoint REST: [docs/API.md](./API.md).
