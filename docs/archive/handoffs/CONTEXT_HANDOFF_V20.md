# Context handoff V20

## Mục tiêu

Khóa luồng vận hành cho trường hợp cùng số điện thoại phát sinh nhiều đơn ở nhiều kết nối landing khác nhau. Không được quay lại duplicate toàn hệ thống vì sẽ làm mất đơn/nguồn/doanh thu, nhưng cũng không được để hai Sale cùng gọi một khách.

## Thay đổi chính

- Thêm bảng `customer_phone_locks`.
- Thêm model `App\Models\CustomerPhoneLock`.
- Thêm service `App\Services\Customers\CustomerPhoneAssignmentService`.
- `LeadIngestionService::allocateFromNormalized()` kiểm phone owner trước khi tạo order.
- `ManualLeadAllocationService` cũng bắt buộc đi qua phone owner, tránh chia tay phá lock.
- `CustomerProfileBulkActionController::reallocateNow()` dùng phone owner khi phân bổ lại.
- Thêm cột audit:
  - `orders.phone_lock_conflict`
  - `orders.phone_lock_note`
  - `lead_ingestions.phone_lock_conflict`
  - `lead_ingestions.phone_lock_owner_user_id`
- Thêm test: cùng phone ở hai landing connection khác nhau vẫn tạo hai order nhưng cùng một Sale owner.

## Migration

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan horizon:terminate
```

## Env mới

```dotenv
CUSTOMER_PHONE_LOCK_ACTIVE_DAYS=30
```

## Cần test trên staging

1. Tạo Sale A và Sale B.
2. Tạo hai kết nối landing khác nhau, mỗi kết nối chỉ định một Sale khác nhau.
3. Submit cùng một SĐT vào kết nối 1.
4. Submit cùng một SĐT vào kết nối 2.
5. Kỳ vọng:
   - có 2 order;
   - cả 2 order cùng `sale_user_id` của Sale A;
   - order thứ hai có `phone_lock_conflict = 1`;
   - bảng `customer_phone_locks` owner là Sale A.
6. Submit lại cùng SĐT vào cùng kết nối 1.
7. Kỳ vọng: không tạo order thứ ba, lead vào duplicate review.
