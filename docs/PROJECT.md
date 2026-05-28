# ERM SaleOps — Tài liệu dự án

> **Tên sản phẩm:** **ERM SaleOps** (Enterprise Resource Management — Sales Operations).  
> Không trùng thương hiệu **pushsale.vn**; chỉ tham chiếu UI/nghiệp vụ tương đương.

## Mục tiêu

Clone đầy đủ luồng nghiệp vụ (Marketing → Telesale → Kho → Kế toán → CEO) theo đặc tả PDF + UI tham chiếu.

**Biến dữ liệu chi tiết:** xem [`docs/DATA-MODEL.md`](DATA-MODEL.md) — map từng cột ảnh pushsale.vn → field code.

## Tech stack

| Lớp | Công nghệ |
|-----|-----------|
| Backend | Laravel 13, Inertia, Reverb |
| Frontend | React 19, shadcn, Recharts, Echo |
| DB | PostgreSQL / MySQL / SQLite |

## Vai trò

| Role | Mã | Mô tả |
|------|-----|--------|
| Quản trị | `admin` | Toàn hệ thống |
| Telesale | `sales` | Xử lý khách hàng, chốt đơn |
| Marketing | `marketing` | Theo dõi chiến dịch / nguồn lead |
| Kho | `warehouse` | Duyệt kho, xuất nhập, vận đơn |
| Chia số | `allocator` | Phân lead cho telesale |
| Kế toán | `accounting` | Đối soát COD, tài chính |

Demo (sau seed):

- `admin@saleops.local` / `password`
- `sales@saleops.local` / `password`

## 10 nhóm màn hình (từ UI tham chiếu)

| # | Màn | File DATA-MODEL |
|---|-----|-----------------|
| 1 | Báo cáo CEO | §1 |
| 2 | Dashboard Marketing | §2 |
| 3 | BC doanh số Marketing | §3 |
| 4 | BC doanh số Sale | §4 |
| 5 | Sale tác nghiệp | §5 |
| 6 | Hồ sơ khách hàng | §6 |
| 7 | Kế toán tác nghiệp | §7 |
| 8 | Thủ kho tác nghiệp | §8 |
| 9 | Đơn hàng lỗi | §9 |
| 10 | Sản phẩm kho | §10 |

## Routes

| Route | Màn |
|-------|-----|
| `/admin/dashboard` | Dashboard chart + Live |
| `/admin/reports/ceo` | Báo cáo CEO |
| `/admin/marketing/dashboard` | Dashboard Marketing |
| `/admin/marketing/revenue` | BC doanh số MKT |
| `/admin/sales/revenue` | BC doanh số Sale |
| `/admin/accounting` | Kế toán tác nghiệp |
| `/admin/warehouse/operations` | Thủ kho tác nghiệp |
| `/admin/warehouse/inventory` | Sản phẩm kho |
| `/admin/orders/failed` | Đơn lỗi |
| `/sales/workspace` | Sale tác nghiệp |
| `/sales/customers` | Hồ sơ KH |
| `/settings` | Theme + thông báo |

Kiến trúc chi tiết: [`docs/ARCHITECTURE.md`](ARCHITECTURE.md)

## Theme & Realtime

- Theme: `brand`, `ocean`, `sunset`, `violet` — `config/saleops.php`
- WebSocket: Reverb, channel `dashboard.admin` / `dashboard.sales`
- `composer run dev` → serve + reverb + `dashboard:broadcast` + vite

## Trạng thái

- [x] Auth đa role, theme, noti, Reverb
- [x] Cấu trúc tổ chức đa vai trò + trưởng nhóm + phân nhánh team
- [x] Domain DB + seed demo (`SaleOpsDemoSeeder`)
- [x] 10 màn UI + Services/Repositories theo DATA-MODEL
- [x] Admin UI tích hợp nền tảng + nhật ký lead (`/admin/integrations`, `/admin/leads`)
- [x] Webhook xử lý lead → Order + Reverb `lead.ingested`
- [x] API webhook vận chuyển + màn đối soát (`/admin/shipping/reconciliation`)
- [x] Màn tổng hợp business + xếp hạng nhân sự (`/admin/reports/business`, `/admin/organization`)
- [ ] Vận chuyển thật (GHTK, GHN, VTP) + webhook ngược

## Lệnh dev

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
composer run dev
```
