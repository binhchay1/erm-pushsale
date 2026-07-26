# V58 - Menu, modal/dialog, marketing dashboard fix

## Scope
- Quy hoạch lại menu trái và flyout cấp 3 bằng block CSS cuối cùng thay vì vá theo từng page.
- Sửa lỗi modal/dialog bị lệch do animation transform ghi đè Radix `translate(-50%, -50%)`.
- Sửa lỗi `DialogTitle must be used within Dialog` trong PushsaleModal.
- Cân lại bảng Marketing dashboard: cột nguồn dữ liệu/sản phẩm đủ rộng nhưng không chiếm quá nhiều, cột số liệu có width tối thiểu dễ đọc.

## Menu
- Flyout cấp 3 tự đóng khi rời item/submenu, click ra ngoài, scroll/resize, hoặc chuyển route.
- Submenu cấp 3 nhỏ hơn, hover xanh thẫm, margin/bo góc.
- Bỏ focus outline/border xanh sót lại trên item có submenu.
- Menu dùng scroll tổng, không tạo scrollbar riêng.

## Modal/Dialog
- PushsaleModal không dùng Radix DialogTitle/DialogDescription nữa vì component này render bằng portal riêng, không nằm trong Radix Dialog.Root.
- Radix DialogContent có keyframe riêng `ps-radix-dialog-v58-in` giữ nguyên transform center.
- PushsaleModal có keyframe riêng `ps-modal-surface-v58-in` không dùng selector Radix.
- Employee modals render qua body portal để tránh bị ảnh hưởng bởi sidebar/content wrapper.

## Built assets patched
- `public/build/assets/pushsale-Dcd-K7SL.css`
- `public/build/assets/pushsale-modal-DLvfPyzQ.js`
- `public/build/assets/AppLayout-D65iIoi_.js` (timer/height flyout)

Do not reintroduce generic animation on `[data-slot='dialog-content']` without preserving `translate(-50%, -50%)`.
