# ERM SaleOps — Changelog

Mới nhất trước. Chi tiết living: [PROJECT_CONTRACT.md](./PROJECT_CONTRACT.md), [OPERATIONS.md](./OPERATIONS.md).

---

## 2026-07-30 — Docs slim + ops UI parity + sales 4.6 Excel

- Gom `docs/` còn bộ living: README, PROJECT_CONTRACT, ARCHITECTURE, OPERATIONS, INTEGRATIONS, DEPLOY, CHANGELOG. Xóa `reference`/`references`/`archive`/`CONTEXT_*`/`RELEASE_VALIDATION_*` và MD versioned trùng ý.
- Sale 4.1 / Kho 5.1 / KT 6.1: bảng + icon theo layout Pushsale; shared `OpsTableCells` / `OrderLineBreakdown` (`tb-in-sp`); upsale giữ thêm.
- Sidebar: font/màu/animation khớp skin-blue-light (`#6C7D8B` / `#007BFF`, slide `.3s`, flyout `.5s`).
- Báo cáo 4.6.1–4.6.5: filter 1 hàng, sticky header, Excel HTML khớp UI (`SalesLeaderReportExcelLayout`).
- Landing ingest: không URL→tên SP / giá ảo; hold upsell 15 phút.

---

## 2026-07-23 — Warehouse 5.3 voucher / inventory

- Phiếu nhập/xuất + movement `reference_type=warehouse_voucher` trong transaction.
- CSS `pushsale-warehouse-flow-contract.css`; test `WarehouseVoucherBusinessLinkTest`.

---

## 2026-07-14 — Landing Connection + shell

- Menu 2.4.1 Kết nối Landing; duyệt gắn SP; public submit + hold/upsale.
- Shell/modal viewport; `LandingConnectionFlowTest`.

---

## Trước đó (rút gọn)

- Phân quyền org tree / team revenue / đơn hoàn kho.
- Horizon + Reverb deploy hooks.
- Report toolbar / PageHeader / sidebar canonical contracts.
