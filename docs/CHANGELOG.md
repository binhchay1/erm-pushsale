# ERM SaleOps — Changelog

Mới nhất trước. Chi tiết living: [PROJECT_CONTRACT.md](./PROJECT_CONTRACT.md), [OPERATIONS.md](./OPERATIONS.md).

---

## 2026-08-02 — Report/admin mobile completion sweep

- Làm tiếp trên bản đã gộp đủ các task trước; lần này mở rộng responsive sang toàn bộ nhóm báo cáo Sale/Marketing/Kế toán/Quản trị và menu lớn 1.x Quản trị đơn vị.
- Bổ sung runtime guard trong `uiShellStyles.js` để mọi bảng thực tế sau khi Inertia render đều được đánh dấu/wrap bằng shell scroll mobile, kể cả bảng nằm trong report page, admin page, dialog và template capture chưa có wrapper.
- Mở rộng contract scroll/drag cho các wrapper report/admin còn thiếu như `ps85-*`, `ps-sales-leader-*`, `ps-operation-conversion-*`, `ps-revenue-*`, `ps-power-*`, `psdd-*`, `ps-facebook-*`, `pslc-*`, `ps-pc-*`; bảng giữ đủ cột nghiệp vụ và kéo ngang thay vì bị bóp chữ dọc.
- Bổ sung mobile layout cho filter/action toolbar của các báo cáo và nhóm trang quản trị 1.x: tablet về 2 cột, mobile về 1 cột; các cụm body nhiều panel như phân bổ data, Facebook đơn vị, import lead, cấu hình HR, chi phí, power report xếp một cột trên màn hình nhỏ.

## v140 - 2026-08-02

- Hoàn thiện responsive mobile thật cho các trang bảng xếp hạng: Marketing ranking, Sales ranking và role rankings; bảng/podium giữ dạng wide table có scroll ngang thay vì bị bóp header.
- Hoàn thiện mobile cho 3 trang tác nghiệp chính: Sale 4.1, Thủ kho 5.1, Kế toán 6.1; filter về 1 cột trên mobile, status tabs scroll ngang, bảng đầy đủ cột và kéo ngang/drag-scroll được.
- Bổ sung drag-to-scroll cho các wrapper bảng thực tế: psr-table-scroll, ps-sale-table-wrap, ps-wh-table-shell, ps-acc-table-wrap.


## 2026-08-02 — Mobile PageHeader + Marketing dashboard responsive correction

- Corrected the previous responsive sweep gap: `PageHeader` is rendered through the header portal, so page-shell-only rules did not affect the actual mobile header/filter DOM.
- Added a real phone/tablet PageHeader contract: title/actions stay usable, primary filters move below, and advanced filters collapse to two columns on tablet / one column on phone.
- Fixed `/admin/marketing/dashboard` specifically:
  - scoped advanced filter rules to `.ps-page-extra-filters.psm-dashboard-header` / `data-page-code=2.1`;
  - forced primary filters and UTM/filter rows to one column on phone;
  - gave the dashboard table explicit column widths and a wide scroll surface so headers are not squeezed vertically;
  - enabled the same drag-to-scroll behavior for `.psm-table-scroll`.
- No new CSS architecture or page-level pattern was introduced; changes remain in the existing shared contracts and the existing marketing dashboard contract.

---

## 2026-08-02 — Small-screen table usability sweep

- Hardened the shared Pushsale table scroll contract for 13"/14" laptop screens:
  - visible horizontal scrollbar styling for all existing table wrappers;
  - constrained table viewport height on small laptop viewports so the horizontal scrollbar remains reachable;
  - drag-to-scroll support for `.ps-table-scroll`, `.table-responsive`, legacy `dragscroll1/tableFixHead`, and the template auto-wrapped table shells.
- Fixed `/admin/marketing/reports/revenue-detail` and sibling revenue-detail reports:
  - increased the revenue table minimum width so dense two-level headers are not squeezed on 14" displays;
  - kept the first two context columns sticky while horizontally scrolling;
  - made the report table body scroll inside the visible viewport on shorter screens.
- Kept the implementation inside existing contracts/runtime shell (`pushsale-page-frame-contract.css`, `pushsale-revenue-detail-report-contract.css`, `uiShellStyles.js`) without introducing a new styling architecture.

---

## 2026-08-02 — Full mobile responsive sweep

- Rà soát lại toàn bộ nhóm trang Inertia/React trong `resources/js/pages` và template-host dùng `PushsaleBusinessPage`; giữ nguyên các task mobile, `/platform/companies` và result-dialog trước đó trong cùng source.
- Bổ sung guard responsive chung cho raw Pushsale/AdminLTE tables: tự đánh dấu/wrap bảng capture chưa có `table-responsive`, đặt min-width theo số cột và cho scroll ngang an toàn trên mobile.
- Bổ sung rule mobile cho filter/header/form controls/dialog footer/action bar; riêng `SaleOrderDialog` chuyển layout cập nhật/chốt đơn sang một cột trên tablet/mobile và giữ bảng sản phẩm scroll ngang trong dialog.

---

## 2026-08-02 — Sale workspace result dialog routing

- Đổi thao tác chọn kết quả ở cột `Kết quả` của `/admin/sales/workspace` và `/sales/workspace` sang mở dialog cập nhật đơn đầy đủ thay vì dialog cập nhật tác nghiệp rút gọn.
- Bổ sung trường `Kết quả` trong `SaleOrderDialog`; giá trị được prefill theo lựa chọn ở bảng và vẫn cho phép đổi lại trước khi lưu/chốt đơn.
- Khi lưu đơn chưa chốt, hệ thống đồng bộ kết quả tác nghiệp qua endpoint hiện có, giữ nguyên order interaction lock và business rule hiện tại.

---

## 2026-08-02 — Platform companies UI refresh

- Rebuilt `/platform/companies` on the shared `PushsalePageShell` contract instead of a loose page header/body split.
- Restyled summary cards, company creation form and company table using the existing `pushsale-platform-companies-contract.css` file.
- Added scoped responsive rules and i18n keys for the platform company sections/search/table title.

---

## 2026-08-02 — Mobile responsive audit

- Bổ sung responsive contract cho PageHeader/PageFrame/AdminLTE chrome: header filters/actions tự xếp cột, toolbar không tràn, topbar co gọn trên mobile.
- Chuẩn hóa horizontal scroll cho bảng lớn ở Sale 4.1, Kho 5.1, Kế toán 6.1, hồ sơ khách hàng, báo cáo/ranking và các wrapper `table-*`.
- Bổ sung mobile sizing cho dialog/modal, system monitor/settings, bảng lịch sử thao tác và floating action button.

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
