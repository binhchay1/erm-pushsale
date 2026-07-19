# V60 — Dialog-only architecture

## Quyết định kiến trúc
- Frontend React/Inertia chỉ dùng Radix `Dialog`.
- Wrapper dùng chung là `resources/js/components/ui/pushsale-dialog.jsx` với export `PushsaleDialog`.
- Không còn `PushsaleModal`, không còn import `@/components/ui/pushsale-modal`, không còn file `pushsale-modal.jsx`.
- Các component/page trước đây có tên `*Modal` đã đổi sang `*Dialog` ở source React.

## Quy tắc code
- Modal/dialog mới chỉ được viết bằng:
  - `PushsaleDialog` nếu cần giao diện Pushsale/AdminLTE.
  - Primitive từ `resources/js/components/ui/dialog.jsx` nếu là dialog shadcn/Radix thuần.
- Không viết thêm overlay thủ công dạng `div + backdrop + position fixed` trong page.
- Không dùng lại class `.modal`, `.ps-modal-*`, `.modal-*`, `.pushsale-modal-*` trong source React/CSS.

## Những phần đã migrate
- Employee account dialogs: thêm tài khoản, thêm nhiều tài khoản, đổi mật khẩu.
- Product / warehouse / inventory dialogs.
- Legacy generated editor dialog.
- Pushsale business editor dialog.
- Customer 360 dialogs.
- Customer profile Pushsale dialogs.
- Marketing dashboard daily/chart/help dialogs.
- Landing connection page dialog.
- CEO report help dialog.
- Shipping order detail dialog.
- Campaign approval detail dialog.

## CSS
- `resources/css/pushsale.css` và các CSS source đã được đổi sang naming `dialog`.
- Contract cuối cùng vẫn nằm ở Pushsale CSS, nhưng selector dùng `ps-dialog-*`.
- Built asset mới: `public/build/assets/pushsale-DzONyVAB.css`.

## Verify
- `npm run build` pass.
- Search source không còn `Modal`, `modal`, `ps-modal`, `pushsale-modal` trong `resources/js` và `resources/css`.
