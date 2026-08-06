# ERM SaleOps — Changelog

Mới nhất trước. Chi tiết living: [PROJECT_CONTRACT.md](./PROJECT_CONTRACT.md), [OPERATIONS.md](./OPERATIONS.md).

---

## 2026-08-06 — Keep-account business data reset command

- Sửa command `data:clear-all-keep-accounts` đúng business mới: giữ nguyên toàn bộ tài khoản hiện có, company, team, preference/token/session đăng nhập; chỉ xóa dữ liệu nghiệp vụ ở các bảng còn lại.
- Bổ sung `--dry-run` để xem trước danh sách bảng/số dòng sẽ xóa, `--force` để chạy thật không hỏi lại và `--flush-sessions` nếu muốn đăng xuất toàn bộ phiên hiện tại.
- Không còn truncate users rồi tạo lại riêng superadmin; tránh mất tài khoản khách hàng/demo/staging đang có.

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
### v140 - Pushsale mobile header composition
- Chuẩn hóa PageHeader mobile theo mẫu Pushsale thật: tiêu đề ở dòng đầu, filter chính xếp dọc bên dưới, action/search/icon nằm dòng cuối gọn bên trái.
- Áp dụng ở contract dùng chung cho tất cả route dùng `PushsalePageShell`/`PageHeader`, không sửa lẻ từng trang và không tạo pattern CSS mới.
- Ép control/header action về chiều cao 30px trên mobile để giảm độ dài header và tránh bóp layout bảng bên dưới.


- 2026-08-03: Polish customer profile table header parity, restore wrapped operation status chips, and clarify Pancake customer chat diagnostics/API v2 default.

- 2026-08-03: Hoàn thiện responsive cho `/admin/shipping-partners` và toàn bộ tab content `/admin/settings/features`; form giao vận xếp 1 cột trên mobile, tab provider/config không tràn, bảng cấu hình chức năng chuyển sang dạng card dễ đọc trên điện thoại.

## 2026-08-04 - Marketing dashboard customer type filter placeholder

- Updated `/admin/marketing/dashboard` customer type filter to show the empty option as `--Tất cả--` instead of defaulting visually to `Khách mới`, keeping it consistent with other Pushsale-style filters.

## 2026-08-05 - Marketing landing packet dialog + upsale contact parity

- Replaced the marketing dashboard row `+` action with a landing packet dialog showing the exact packets behind the selected source/UTM row under the active filter.
- Marketing contact metrics now count valid landing traffic as `primary packets + upsale packets`; duplicate, failed and follow-up packets remain excluded.
- Added packet breakdown to the marketing dashboard table/export/chart, campaign report, marketing revenue detail, marketing work matrix, upsale-source report and marketer revenue report contact cell.
- Kept global `LeadContactMetrics` unchanged so CEO/Sale contact ratios still use the customer-primary contact contract.
- Added regression coverage for packet-dialog totals and global-vs-marketing contact parity.

## 2026-08-06 - Marketing upsale contract audit

- Tightened `MarketingPacketMetrics`: marketing contact now counts primary packets plus only processed upsale packets already attached to an effective order. Duplicate, failed, pending, needs-review and orphan/unmerged upsale packets are excluded from contact totals.
- Centralized customer type filtering for marketing packets so `/admin/marketing/dashboard` keeps the invariant `Tất cả = Khách mới + Khách cũ`.
- Added regression coverage for valid/invalid upsale packets and customer-type partitioning on the Pushsale Marketing dashboard.

## 2026-08-06 - Landing tick indicator + upsale audit command

- Fixed Landing Connections `Nhập TC` / `Duyệt` unchecked indicator so the inactive state remains an 18px round circle instead of collapsing into a thin oval.
- Hardened `MarketingPacketMetrics` against legacy packet type aliases (`upsale`, `late_upsale`, `orphan_upsale`) while keeping the canonical enum values (`upsell`, `late_upsell`, `orphan_upsell`).
- Added `landing:upsale-audit` for production checks: packet type/status distribution, valid counted upsale, invalid review-only states, and `Tất cả = Khách mới + Khách cũ` partition validation.

## 2026-08-06 - Marketing dashboard raw landing packet contract

- Changed Pushsale Marketing dashboard `/admin/marketing/dashboard` to use `inbound_events` (`source=landing_webhook`) as the primary landing packet count, so source/UTM totals match raw landing sheet rows instead of post-allocation contact dedupe.
- Kept processed `lead_ingestions` as a secondary `validContacts` metric for sale allocation/customer handling, with duplicate/raw phone breakdown visible next to the raw packet count.
- Updated the landing packet dialog opened by `+` to list raw inbound packets, while still showing the valid post-processing count for investigation.
- Preserved real UTM campaign values from landing payload in `LandingConnectionPayloadMapper`; marketing-source config is now only the fallback.

## 2026-08-06 - Marketing reports raw packet sync

- Added `MarketingRawPacketMetrics` as the shared source-of-truth for Marketing packet totals from `inbound_events` (`source=landing_webhook`).
- Synchronized Marketing dashboard, campaign report, team tree, marketing work matrix, marketer revenue detail and upsale-source report to prefer raw landing packet counts; legacy post-processing contacts remain fallback only when a source has no raw landing events.
- Kept `lead_ingestions` metrics visible as secondary valid/contact-processing figures and preserved Sale/Customer anti-duplicate behavior.
- Expanded the Pushsale Marketing `+` packet dialog summary with raw/valid/unique/duplicate/rejected/failed counters.
