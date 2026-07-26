# Context Handoff V26

V26 bắt đầu từ V24, không dùng V25 làm nền vì V25 đã xóa nhiều CSS module khiến site mất style.

Thay đổi chính:

1. Khôi phục toàn bộ CSS từ V24.
2. Gộp CSS nội bộ vào `resources/css/pushsale.css`, giữ nguyên các file module để đối chiếu.
3. Patch built CSS hiện tại: `public/build/assets/pushsale-VZglJWi2.css`.
4. Sửa `resources/js/layouts/AppLayout.jsx` để menu mở mặc định và không đọc localStorage collapsed cũ.
5. Patch built JS hiện tại: `public/build/assets/AppLayout-C6T4YjHa.js`.
6. Thêm guard cho `BasePushsalePageController@index` để các trang table như `/admin/accounting/expenses` không chết 500 khi production chưa đồng bộ dữ liệu/migration/cache.
7. Patch `BusinessPage` và built chunk để hiển thị `pageRuntimeError`.

Sau deploy nên build lại frontend thay vì phụ thuộc built asset đã patch tay.
