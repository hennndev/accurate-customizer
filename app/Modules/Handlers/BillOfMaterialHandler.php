<?php

namespace App\Modules\Handlers;

use App\Services\AccurateService;

class BillOfMaterialHandler extends BaseHandler
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
      }
    }
  }
}
