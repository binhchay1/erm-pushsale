# Project Version Log — ERM Pushsale

File này là bản tổng hợp ngắn để không phải đọc toàn bộ `CONTEXT_HANDOFF_V*.md` mỗi lần chuyển context. Các file handoff cũ vẫn giữ làm bằng chứng chi tiết theo từng đợt.

## Nhóm nền tảng

- V12–V24: landing/upsell, historical reporting, Horizon/Redis, UI consolidation, security, staging validation.
- V25–V30: cleanup UI, CSS recovery, staging seed/test, remote staging smoke.
- V32–V40: Horizon/report menu, login/modal/table, sale ranking, unit menu, locale, auth, table action, UI architecture.

## Nhóm UI Pushsale/AdminLTE

- V41–V44: warehouse operations parity, CSS scope, feature settings, shipping config UI/backend.
- V45–V52: marketing leader stats, customer management/profile, modal system, role workspaces, checkbox normalization.
- V53–V60: modal action, customer profile links, sale workspace parity, menu/modal parity, dialog architecture.
- V61–V69: source deploy cleanup, dialog CSS cleanup, warehouse ops parity, menu icon parity, PNPM-only, frontend shell/vendor restore.
- V70–V84: table/filter/dialog polish, combo, login history, operation categories, teams, accounting ops, shared filters, content shell, menu isolation, universal page contract, full project audit.

## Nhóm báo cáo/upsale gần nhất

- V85–V89: page/menu contract, header/menu/dialog polish, pagination/action/upsale, filter/info, page pagination fixes.
- V90: reports + feature/ecommerce.
- V91: customer profile/product/money columns shared with operation pages.
- V92: dashboard/menu/sales report.
- V93: system business revenue reports + menu scroll.
- V94: revenue detail marketing report.
- V95: marketing revenue summary V2.
- V96: marketing work + leader reports.
- V97: marketing upsale report, menu 2.8/8.1, demo endpoint.
- V98: warehouse 5.3.1–5.3.3 templates + backend voucher/inventory/movement linkage + tests.

## Nguyên tắc duy trì từ V98 trở đi

1. CSS version mới phải được scope theo page/root cụ thể, không dùng selector global như `table`, `.btn`, `select` trực tiếp.
2. Template chỉ giữ layout/header/filter/table header/action structure. Dữ liệu luôn lấy từ backend thật.
3. Các trang cùng nghiệp vụ phải đọc cùng source business, không tự tính riêng từng màn.
4. Mọi thay đổi làm phát sinh tồn kho/doanh số/phân bổ phải có test transaction và rollback case lỗi.
5. Khi thêm context mới, update file này ngắn gọn trước, sau đó mới tạo handoff chi tiết nếu cần.
