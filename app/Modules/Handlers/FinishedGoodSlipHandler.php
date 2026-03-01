<?php

namespace App\Modules\Handlers;

use App\Services\AccurateService;

class FinishedGoodSlipHandler extends BaseHandler
{
  public function transformDetail(array &$detailData, array $sharedContext, array $meta = []): void
  {
    // Extract workOrder number and remove the full workOrder object
    if (isset($detailData['workOrder']) && is_array($detailData['workOrder'])) {
      $detailData['workOrderNumber'] = $detailData['workOrder']['number'] ?? null;
      unset($detailData['workOrder']);
    }
  }
}
