<?php

namespace App\Modules\Handlers;

use App\Modules\Contracts\ModuleHandler;
use App\Services\AccurateService;

class BaseHandler implements ModuleHandler
{
    public function preCapture(AccurateService $accurate, array &$sharedContext): void
    {
        // no-op
    }

    public function transformDetail(array &$detailData, array $sharedContext, array $meta = []): void
    {
        // no-op by default
    }
}
