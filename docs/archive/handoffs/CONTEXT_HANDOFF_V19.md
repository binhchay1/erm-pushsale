# Context handoff V19

## Thay đổi chính

- Thêm `LeadDuplicatePolicy` để scope duplicate theo Kết nối Landing thay vì toàn hệ thống.
- `LeadIngestionService` lưu `landing_connection_id` và `landing_connection_source_id` ngay khi nhận lead, không chờ controller forceFill sau đó.
- `LeadOrderFactory` ghi snapshot landing connection/source trực tiếp lên order khi tạo đơn.
- Duplicate cùng Kết nối Landing hiện `counts_as_lead = false` và `requires_review = true`.
- Khách cùng số đi qua hai Kết nối Landing khác nhau tạo hai order độc lập.
- `PancakeCustomerChatService` có fallback match webhook message theo số điện thoại trong cùng company, sau đó tạo `PancakeSyncRecord` loại `conversation` để chat modal mở được đúng hội thoại.
- Thêm `BusinessFlowContractService` và command `audit:business-flow` để audit cấu hình end-to-end.
- Thêm test hồi quy `LandingConnectionDuplicateScopeTest`.

## Files chính

- `app/Services/Leads/LeadDuplicatePolicy.php`
- `app/Services/Leads/LeadIngestionService.php`
- `app/Services/Leads/LeadOrderFactory.php`
- `app/Services/Pancake/PancakeCustomerChatService.php`
- `app/Services/BusinessFlow/BusinessFlowContractService.php`
- `app/Console/Commands/AuditBusinessFlowContractCommand.php`
- `tests/Feature/Leads/LandingConnectionDuplicateScopeTest.php`
- `docs/BUSINESS_FLOW_V19.md`

## Deploy

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan audit:business-flow
php artisan horizon:terminate
```

Không có migration mới trong V19 vì các cột cần dùng đã có từ V13/V14.

## Lưu ý vận hành

- Sau deploy nên chạy `audit:business-flow` cho từng công ty đang active.
- Nếu có dữ liệu cũ chưa có `landing_connection_id` trên order, duplicate policy vẫn fallback theo `marketing_source_id` nên không vỡ luồng.
- Pancake chỉ tự link được khi webhook/tin nhắn có số điện thoại hoặc đã có conversation record trước đó.

