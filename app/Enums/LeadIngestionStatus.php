<?php

namespace App\Enums;

enum LeadIngestionStatus: string
{
    case Pending = 'pending';
    case Processed = 'processed';
    case Duplicate = 'duplicate';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ xử lý',
            self::Processed => 'Đã tạo lead',
            self::Duplicate => 'Trùng số',
            self::Failed => 'Lỗi',
        };
    }
}
