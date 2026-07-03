<?php

namespace App\Enums;

/** Mức quyền cho mỗi khu vực chức năng: không có / chỉ xem / toàn quyền. */
enum PermissionLevel: string
{
    case None = 'none';
    case View = 'view';
    case Full = 'full';

    public function rank(): int
    {
        return match ($this) {
            self::None => 0,
            self::View => 1,
            self::Full => 2,
        };
    }

    /** Mức hiện tại có đáp ứng mức yêu cầu tối thiểu không. */
    public function allows(self $required): bool
    {
        return $this->rank() >= $required->rank();
    }

    public static function fromNullable(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::None;
    }
}
