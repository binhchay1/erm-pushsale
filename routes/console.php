<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hot window: hôm nay được tổng hợp lại mỗi 5 phút, dashboard vẫn live nhưng không quét toàn bộ lịch sử.
Schedule::command('reports:aggregate-daily --queue')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->onOneServer();

// Late webhook/COD/đơn hoàn có thể sửa một ngày cũ. Dirty-date queue chỉ rebuild đúng ngày bị ảnh hưởng.
Schedule::command('reports:process-dirty --queue')
    ->everyTenMinutes()
    ->withoutOverlapping(20)
    ->onOneServer();

// Đóng sổ ngày hôm qua sau khi qua thời điểm chuyển ngày; job idempotent nên chạy lại an toàn.
Schedule::command('reports:aggregate-daily yesterday --close --queue')
    ->dailyAt('00:20')
    ->timezone(config('reporting.timezone'))
    ->withoutOverlapping(30)
    ->onOneServer();

// Sau khi facts đã đóng, tạo snapshot bền vững cho dashboard/report mặc định.
Schedule::command('reports:warm-snapshots --queue')
    ->dailyAt('00:45')
    ->timezone(config('reporting.timezone'))
    ->withoutOverlapping(120)
    ->onOneServer();

// Kiểm tra metadata/checksum hằng ngày, không tự sửa để tránh che lỗi vận hành.
Schedule::command('reports:verify-facts --days=14 --queue')
    ->dailyAt('01:20')
    ->timezone(config('reporting.timezone'))
    ->withoutOverlapping(60)
    ->onOneServer();

// Ngày 2 hàng tháng: copy tháng trước sang bảng vật lý *_YYYY_MM và verify checksum.
Schedule::command('reports:archive-month --queue')
    ->monthlyOn(2, '02:00')
    ->timezone(config('reporting.timezone'))
    ->withoutOverlapping(240)
    ->onOneServer();

// Late updates to a month already archived mark its manifest stale; refresh in small queued batches.
Schedule::command('reports:refresh-stale-archives --queue --limit=12')
    ->dailyAt('02:40')
    ->timezone(config('reporting.timezone'))
    ->withoutOverlapping(240)
    ->onOneServer();

// Durable result snapshots are bounded by retention and pruned in small batches.
Schedule::command('reports:prune-snapshots --limit=5000 --queue')
    ->dailyAt('03:20')
    ->timezone(config('reporting.timezone'))
    ->withoutOverlapping(30)
    ->onOneServer();

// Populate Horizon throughput/runtime graphs. The scheduler itself should run every minute.
Schedule::command('horizon:snapshot')->everyFiveMinutes()->withoutOverlapping();
