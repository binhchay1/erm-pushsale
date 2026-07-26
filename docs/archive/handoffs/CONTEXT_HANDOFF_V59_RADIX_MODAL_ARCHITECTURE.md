# V59 - Radix modal architecture

## Decision

Project dùng **Radix Dialog** làm nền tảng duy nhất cho modal/dialog trong React/Inertia.

Lý do:
- React/Inertia đang là frontend runtime chính, Radix đã có sẵn trong project.
- Radix xử lý portal, focus trap, ESC close, outside close và aria tốt hơn tự viết legacy modal.
- Pushsale chỉ là chuẩn UI/UX hiển thị; không bắt buộc copy Bootstrap modal/iframe raw.
- `PushsaleModal` giờ là wrapper visual quanh Radix Dialog, không còn tự render portal riêng.

## Rule mới

- Không tạo modal kiểu page-local `ps-modal-backdrop`, `ps-employee-modal-backdrop`, `legacy-modal-layer` nữa.
- Modal mới phải dùng:
  - `PushsaleModal` cho UI AdminLTE/Pushsale style.
  - hoặc `Dialog / DialogContent` khi cần primitive thấp hơn.
- `DialogTitle` chỉ dùng bên trong `DialogContent` thuộc một `Dialog` root.
- Không mix `DialogTitle` vào custom portal.

## Files changed

- `resources/js/components/ui/dialog.jsx`
  - Standard Radix primitive wrapper.
  - Overlay/content z-index và slot classes đồng bộ.

- `resources/js/components/ui/pushsale-modal.jsx`
  - Rewritten as Radix Dialog wrapper.
  - Owns Pushsale header/body/footer visual contract.
  - Always renders a valid `DialogTitle`, including sr-only fallback.

- Migrated old local modals to `PushsaleModal`:
  - `resources/js/pages/Admin/Users/Index.jsx`
  - `resources/js/pages/Admin/Products/Index.jsx`
  - `resources/js/pages/Admin/Warehouse/Index.jsx`
  - `resources/js/pages/Admin/Warehouse/Inventory.jsx`
  - `resources/js/pages/Customers/Management.jsx`
  - `resources/js/pages/Legacy/Index.jsx`

- `resources/css/pushsale.css`
  - Added final V59 Radix-only modal contract.
  - Keeps compatibility styling for old generated legacy shells only.

## What this fixes

- JS crash: `DialogTitle must be used within Dialog`.
- Modal/dialog drifting left/top because of mixed portal/legacy shells.
- Employee modals and generated legacy modals using a different overlay system.
- Conflicting modal animations from multiple CSS blocks.

## Build

`npm run build` passes.
