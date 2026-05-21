# REST API v1 — ERM SaleOps

Base URL: `{APP_URL}/api/v1`

## Định dạng phản hồi

Thành công:

```json
{
  "success": true,
  "message": "optional",
  "data": { }
}
```

Lỗi validation (422):

```json
{
  "message": "...",
  "errors": { "field": ["..."] }
}
```

Lỗi API:

```json
{
  "success": false,
  "message": "..."
}
```

Phân trang (collection): thêm `meta`, `links` theo chuẩn Laravel pagination.

---

## Auth (Sanctum)

| Method | Path | Mô tả |
|--------|------|--------|
| POST | `/auth/token` | Đăng nhập, trả Bearer token |
| GET | `/auth/me` | User hiện tại (cần token) |
| DELETE | `/auth/token` | Thu hồi token hiện tại |

**POST /auth/token**

```json
{
  "email": "admin@saleops.local",
  "password": "password",
  "device_name": "mobile-app"
}
```

Response `201`:

```json
{
  "success": true,
  "data": {
    "token_type": "Bearer",
    "access_token": "1|...",
    "user": { "id": 1, "name": "...", "role": "admin" }
  }
}
```

Header các request sau: `Authorization: Bearer {access_token}`

---

## Webhooks (không cần Bearer — xác thực theo nền tảng)

| Method | Path |
|--------|------|
| GET/POST | `/webhooks/facebook` |
| POST | `/webhooks/tiktok` |
| POST | `/webhooks/zalo` |
| POST | `/webhooks/landing` |
| POST | `/webhooks/google` |

---

## Dashboard

| Method | Path | Quyền |
|--------|------|-------|
| GET | `/dashboard/summary` | Admin + Sales (sales chỉ thấy đơn của mình) |

---

## Orders

| Method | Path |
|--------|------|
| GET | `/orders?search=&per_page=20` |
| GET | `/orders/{id}` |

Sales chỉ xem đơn `sale_user_id` = mình.

---

## Leads (ingestion log)

| Method | Path |
|--------|------|
| GET | `/leads?platform=facebook&status=processed` |
| GET | `/leads/{id}` |
| POST | `/leads` — tạo lead từ landing (Bearer) |

---

## Integrations (Admin only)

| Method | Path |
|--------|------|
| GET | `/integrations` |
| GET | `/integrations/{platform}` |
| PUT | `/integrations/{platform}` |

`platform`: `facebook`, `tiktok`, `zalo`, `landing`, `google`

---

## Transformers (API Resources)

- `UserResource`
- `OrderResource`
- `LeadIngestionResource`
- `IntegrationResource`

Namespace: `App\Http\Resources\V1\`

---

## Ví dụ curl

```bash
# Token
curl -X POST http://localhost:8000/api/v1/auth/token \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@saleops.local","password":"password"}'

# Danh sách tích hợp
curl http://localhost:8000/api/v1/integrations \
  -H "Authorization: Bearer YOUR_TOKEN"

# Gửi lead landing
curl -X POST http://localhost:8000/api/v1/leads \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Test","phone":"0909123456","product":"SP A"}'
```
