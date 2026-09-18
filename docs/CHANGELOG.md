# ERM SaleOps — Changelog

Mới nhất trước. Living: [PROJECT_CONTRACT.md](./PROJECT_CONTRACT.md), [OPERATIONS.md](./OPERATIONS.md), [REPORTING.md](./REPORTING.md).

---

## 2026-09-17 — Docs slim + dead-asset cleanup

- Gom docs reporting/marketing packet/upsale vào `OPERATIONS.md` + `REPORTING.md`; xóa MD trùng/deprecated.
- Chuyển conventions vào `docs/AGENTS.md` (không còn file root).
- Phương án B: xóa CSS `_archive/*` (giữ README), xóa test template chết + nới `PushsalePageRegistryTest`, xóa `deploy/security-audit-remote*.sh`.
- Xóa asset chết (A): `config/pushsale_page_merges.php`, orphan audit/i18n scripts, React page trùng, `tests/Unit/ExampleTest.php`.
- Giữ living scripts: `audit-pushsale-contract.mjs`, `enforce-pnpm.cjs`.

## 2026-09-17 — Voucher/CEO harden + yearly UX

- Phiếu kho: mutation bắt lỗi → toast/422 thân thiện; empty kho/SP banner.
- CEO yearly: try/catch load actuals, SQL aggregate, filter tháng click + cột theo tháng; i18n `ceo.*`.
- E2E: `e2e/flows/16-warehouse-voucher-inbound.spec.ts`, `17-ceo-menu7.spec.ts`.

## 2026-09-06 — Sửa lỗi 500 tạo kho, trang thiếu template, sổ địa chỉ 2 cấp 2025

- **Tạo kho lỗi 500**: `WarehouseRequest` quy `sort_order`/`use_two_level_address` về mặc định.
- **Trang thiếu template**: `BusinessPage` fallback từ `display_columns`.
- **Sổ địa chỉ**: mặc định 2 cấp 2025 (Tỉnh/TP → Phường/Xã).
- Hardening: `RevenueBonusRule` tenant, shipping print/sync 422, `LegacyLeadPacketType`, Windows `sys_getloadavg`.

---

Milestone cũ hơn (mobile sweeps, FB upsell, report i18n, …) đã gỡ khỏi file này để docs gọn — tra git history `docs/CHANGELOG.md` nếu cần.
