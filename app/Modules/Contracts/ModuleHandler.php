<?php

namespace App\Modules\Contracts;

use App\Services\AccurateService;

interface ModuleHandler
{
    /**
     * Run once before capturing items for this module.
     * Use sharedContext to store data needed across items (e.g., branch list).
     */
    public function preCapture(AccurateService $accurate, array &$sharedContext): void;

    /**
     * Transform detail payload per item right after fetching detail.
     */
    public function transformDetail(array &$detailData, array $sharedContext, array $meta = []): void;
}
