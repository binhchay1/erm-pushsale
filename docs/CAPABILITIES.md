# Đối chiếu năng lực hệ thống — ERM SaleOps

Tài liệu này map yêu cầu nghiệp vụ (mô hình Pushsale / phễu bán hàng) với trạng thái code hiện tại.

## Tóm tắt

| Hạng mục | Trạng thái | Ghi chú |
|----------|------------|---------|
| Phễu thu data (Webhook/API) | ✅ Có | 7 nền tảng + admin UI cấu hình |
| Chống trùng SĐT | ✅ Có | Cửa sổ cấu hình `LEAD_DUPLICATE_WINDOW_DAYS` |
| Chia số telesale | ⚠️ Một phần | Round-robin / least_load — chưa theo team/online |
| Workspace telesale | ✅ UI | Gọi điện VOIP thật — ❌ chưa |
| ROI Marketing | ✅ Báo cáo | Dashboard MKT + BC doanh số theo nguồn |
| Kho & tồn | ✅ UI + DB | Demo data |
| Vận chuyển (GHTK, GHN, VTP) | ❌ Chưa API | Chỉ field trạng thái trên đơn |
| Webhook VC ngược | ❌ Chưa | Cần module shipping riêng |
| Kế toán / COD | ✅ UI | Đối soát demo |
| RBAC chi tiết | ⚠️ 2 role | admin + sales — chưa kho/kế toán/marketing |
| Real-time dashboard | ✅ | Reverb + toast lead mới |
| Cron 24/7 | ⚠️ Demo | `dashboard:broadcast` — chưa sync carrier |
| Nhập Excel / thủ công | ❌ | Có thể thêm import sau |
| Sàn TMĐT pull API | ⚠️ Webhook | Shopee/Lazada webhook generic — chưa OAuth pull |

---

## 1. Data Input — Dữ liệu từ đâu?

| Nguồn | Cách vào hệ thống | Trạng thái |
|-------|-------------------|------------|
| Landing / Ladipage | Webhook `POST /api/v1/webhooks/landing` hoặc `POST /api/v1/leads` | ✅ |
| Facebook Lead Ads | Webhook leadgen + verify GET | ✅ |
| TikTok / Google Ads | Webhook generic + signature | ✅ |
| Zalo OA | Webhook generic | ✅ |
| Shopee / Lazada | Webhook generic (payload phone/name) | ⚠️ Cần map payload thật từ sàn |
| Inbox FB / TikTok Shop chat | — | ❌ Cần tích hợp chat API |
| Excel / nhập tay | — | ❌ |

**Admin cấu hình:** `/admin/integrations` — bật/tắt, credentials, copy webhook URL, test payload.

---

## 2. Luồng lõi sau khi có data

```
Webhook/API → LeadIngestionService
  → Kiểm tra trùng external_id
  → Kiểm tra trùng SĐT (30 ngày)
  → LeadRoutingService (round_robin | least_load)
  → Tạo Order (operation_stage: new_customer)
  → Event LeadIngested → Reverb
  → Telesale workspace
```

| Bước | Trạng thái |
|------|------------|
| Hứng & phân loại | ✅ |
| Chia số thông minh | ⚠️ Round-robin, chưa online/priority |
| Telesale chốt đơn | ✅ UI workspace |
| Click-to-call VOIP | ❌ |
| Xuất kho + in vận đơn | ⚠️ UI only |
| Đẩy GHTK/GHN/VTP | ❌ |
| Webhook trạng thái VC | ❌ |
| Đối soát COD | ⚠️ UI báo cáo |

---

## 3. Backend cần có (theo spec clone)

| Thành phần | Trạng thái |
|------------|------------|
| Database domain (orders, sources, warehouse…) | ✅ |
| REST API v1 + Sanctum | ✅ |
| Webhook 24/7 | ✅ (cần HTTPS + queue nếu volume lớn) |
| RBAC chi tiết | ❌ → roadmap |
| Cron / queue jobs | ⚠️ Queue driver có, job nghiệp vụ chưa |
| Transformers (API Resources) | ✅ |
| Mã hóa credentials DB | ✅ |

---

## 4. Triển khai webhook production

1. `php artisan migrate`
2. Admin → **Tích hợp nền tảng** → bật nền tảng + nhập key
3. `APP_URL` = domain HTTPS public
4. Đăng ký URL webhook trên Meta/TikTok/Zalo/Landing
5. Bật Reverb + queue: `composer run dev` hoặc supervisor
6. Kiểm tra **Nhật ký lead** `/admin/leads` và toast real-time

Biến môi trường bổ sung: xem `.env.example` (`LEAD_ROUTING_STRATEGY`, `SHOPEE_*`, `LAZADA_*`).

---

## 5. Roadmap gợi ý (chưa làm)

1. **Shipping module** — GHTK/GHN/VTP create order + webhook status
2. **VOIP** — click-to-call (Stringee, Twilio, OmiCall…)
3. **RBAC** — roles warehouse, accounting, marketing
4. **Import Excel** — lead manual
5. **Shopee/Lazada** — driver payload chuẩn + OAuth refresh token job
6. **Online-aware routing** — chỉ chia cho sale đang online (presence)
