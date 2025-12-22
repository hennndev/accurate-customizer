<?php

namespace App\Modules\Handlers;

use App\Services\AccurateService;
use Illuminate\Support\Facades\Log;

class PurchaseRequisitionHandler extends BaseHandler
{
  public function preCapture(AccurateService $accurate, array &$sharedContext): void
  {
    try {
      $branchListData = $accurate->fetchModuleData('/api/branch/list.do', []);
      $map = [];
      foreach ($branchListData as $branch) {
        if (isset($branch['id'])) {
          $map[$branch['id']] = $branch;
        }
      }
      $sharedContext['branchList'] = $map;
    } catch (\Exception $e) {
      Log::error('FAILED_TO_FETCH_BRANCH_LIST', [
        'error' => $e->getMessage(),
      ]);
    }
  }
  public function transformDetail(array &$detailData, array $sharedContext, array $meta = []): void
  {
    $branchList = $sharedContext['branchList'] ?? [];
    if (isset($detailData['branchId']) && !empty($branchList)) {
      $branchId = $detailData['branchId'];
      if (isset($branchList[$branchId]['name'])) {
        $branchName = $branchList[$branchId]['name'];
        unset($detailData['branchId']);
        $detailData['branchName'] = $branchName;
      } else {
        Log::warning('PURCHASE_INVOICE_BRANCH_NOT_FOUND_IN_LIST', [
          'item_id' => $meta['itemId'] ?? null,
          'branch_id' => $branchId,
          'available_branches' => array_keys($branchList),
        ]);
      }
    }
    // if (isset($detailData['number'])) {
    //   unset($detailData['number']);
    // }
  }
}
