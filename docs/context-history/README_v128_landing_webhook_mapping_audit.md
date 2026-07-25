# v128 — Landing webhook mapping audit & upsell fallback

## Mục tiêu

Flow tạo kết nối landing vẫn đơn giản: Marketing khai báo URL landing chính và URL upsale/thank-you. Sản phẩm/gói sản phẩm có thể được cấu hình khi duyệt, nhưng webhook không được rơi dữ liệu nếu payload LadiPage không map được vào catalog.

## Contract nghiệp vụ

1. Webhook là request độc lập, không có session server-side giữa trang landing và trang cám ơn.
2. Ưu tiên match bằng `ps_flow`, `saleops_session`, `session_id`, `saleops_client_ref`.
3. Nếu trang upsale không gửi flow token nhưng có `phone`, `landing_phone` hoặc field điện thoại lấy từ URL, hệ thống fallback tìm session/order gần nhất trong `LEAD_PHONE_MERGE_WINDOW_MINUTES`.
4. Fallback theo SĐT chỉ auto-append khi order còn cửa sổ upsell hold; ngoài cửa sổ hoặc không đủ mapping sẽ đưa vào review, không tạo đơn rác.
5. Payload nào nhận được cũng phải lưu đủ audit map:
   - field khách hàng,
   - field match phiên,
   - field nghi là sản phẩm/combo/upsale,
   - item đã map vào catalog,
   - field sản phẩm chưa map.
6. Nếu không map được sản phẩm/gói, hệ thống tạo `lead_ingestions.status=needs_review`, giữ raw payload và `_landing_webhook_mapping`, không trả hard 422/500.

## File chính

- `app/Services/Marketing/LandingConnectionPayloadMapper.php`
- `app/Http/Controllers/Api/V1/LandingConnectionSubmissionController.php`
- `app/Http/Controllers/Admin/LeadsLogController.php`
- `config/saleops.php`
- `tests/Feature/Leads/LandingConnectionFlowTest.php`

## Test liên quan

- `test_upsell_without_flow_token_can_fallback_to_recent_phone_session_and_merge`
- `test_unmapped_landing_payload_is_kept_for_review_with_full_field_mapping_report`
