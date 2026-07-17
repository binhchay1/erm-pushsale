# CONTEXT HANDOFF V28

V28 bổ sung khả năng remote QA trên domain staging `erm-pushsale.duckdns.org`.

## File mới

- `config/staging_test.php`
- `.env.staging-test.example`
- `app/Http/Controllers/Testing/StagingTestController.php`
- `app/Services/Testing/StagingTestService.php`
- `app/Console/Commands/StagingSmokeTestCommand.php`
- `deploy/staging-enable-test-mode.sh`
- `deploy/staging-smoke-test.sh`
- `docs/STAGING_REMOTE_TEST_V28.md`

## Endpoint mới

- `/__erm-test/health?secret=...`
- `/__erm-test/bootstrap?secret=...&reset=1&campaigns=2&per_campaign=8`
- `/__erm-test/pages?secret=...`
- `/__erm-test/landing-flow?secret=...`
- `/__erm-test/flow?secret=...`
- `/__erm-test/audit?secret=...`

Tất cả endpoint bị khóa bởi:

- `ERM_STAGING_TEST_MODE=true`
- `ERM_STAGING_TEST_SECRET`
- `ERM_STAGING_TEST_HOSTS`

## Luồng test chính

Sau deploy, người vận hành bật `.env`, chạy migrate/seed/build. Sau đó ChatGPT hoặc QA có thể mở endpoint public để lấy JSON trạng thái. Endpoint `pages` quét các route quan trọng từ domain thật để bắt lỗi 500 hàng loạt. Endpoint `landing-flow` tạo và test luồng Landing Connection chính + upsale thật qua HTTP public.
