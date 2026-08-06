# Marketing raw landing packet contract

## Chốt business

Marketing dashboard và các báo cáo Marketing dùng **gói tin raw server nhận được** làm số liệu chính để đối soát với landing/Google Sheet/ads.

- Nguồn số liệu chính: `inbound_events` với `source = landing_webhook`.
- Thời gian đếm: `inbound_events.created_at` — thời điểm server nhận request landing.
- Không trừ số trùng điện thoại.
- Không loại gói `duplicate`, `review`, `failed` ở tầng lead xử lý sau.
- Không phụ thuộc việc đã chia data cho Sale hay chưa.
- Không phụ thuộc việc upsale đã merge vào đơn hay chưa.

## Tầng xử lý sau

`lead_ingestions`, `orders`, sale allocation và customer profile vẫn giữ logic nghiệp vụ riêng:

- chống chia trùng SĐT cho nhiều Sale;
- gộp/đánh dấu duplicate;
- review orphan/late upsale;
- tạo hoặc merge đơn;
- tính `validContacts` phục vụ sale/customer operation.

Vì vậy báo cáo Marketing có thể hiển thị đồng thời:

- `Gói tin raw`: đối soát sheet/landing;
- `Contact hợp lệ`: số đã qua xử lý đủ điều kiện vận hành Sale;
- `SĐT duy nhất`, `Trùng raw`, `Rejected raw`, `Failed raw`: chỉ số audit.

## Lý do đổi

Trước đây một số báo cáo Marketing dùng `lead_ingestions` sau xử lý làm cột contact. Khi một khách submit nhiều lần hoặc bị review/duplicate, số hiển thị thấp hơn sheet dù server có nhận raw. Contract mới tách rõ tầng Marketing raw và tầng Sale operation để khách hàng đối soát minh bạch.
