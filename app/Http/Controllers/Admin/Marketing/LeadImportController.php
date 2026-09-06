<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Admin\Pushsale\BasePushsalePageController;

final class LeadImportController extends BasePushsalePageController
{
    protected string $pageCode = '2.6.1';

    /** Import Excel chỉ cần form upload — không tải catalog/filter nặng. */
    protected bool $lightweightIndex = true;
}
