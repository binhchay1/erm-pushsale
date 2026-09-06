<?php

namespace App\Http\Controllers\Admin\Integrations;

use App\Http\Controllers\Admin\Pushsale\BasePushsalePageController;

final class LeadImportPageController extends BasePushsalePageController
{
    protected string $pageCode = '1.10';

    /** Import Excel chỉ cần form upload — không tải 1000 leads + filter catalog. */
    protected bool $lightweightIndex = true;
}
