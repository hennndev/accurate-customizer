<?php

namespace App\Modules\Handlers;

use App\Services\AccurateService;
use Illuminate\Support\Facades\Log;

class PurchaseOrderHandler extends BaseHandler
{
    protected array $allowedFields = [
        'number',
        'transDate',
        'vendorNo',
        'vendor',
        'branchName',    // hasil transform dari branchId
        'description',
        'detailItem',
        'currencyCode',
        'cashDiscount',
        'detailExpense',
        'fobName',
        'inclusiveTax',
        'paymentTermName',
        'rate',
        'shipmenName',
        'taxable',
        'toAddress',
    ];

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
    // Transform branchId → branchName
    $branchList = $sharedContext['branchList'] ?? [];
    if (isset($detailData['branchId']) && !empty($branchList)) {
      $branchId = $detailData['branchId'];
      if (isset($branchList[$branchId]['name'])) {
        $detailData['branchName'] = $branchList[$branchId]['name'];
      } else {
        Log::warning('PURCHASE_ORDER_BRANCH_NOT_FOUND_IN_LIST', [
          'item_id'            => $meta['itemId'] ?? null,
          'branch_id'          => $branchId,
          'available_branches' => array_keys($branchList),
        ]);
      }
    }

    $filteredData = [];
    foreach ($this->allowedFields as $field) {
      if (array_key_exists($field, $detailData)) {
        $filteredData[$field] = $detailData[$field];
      }
    }

    $detailData = $filteredData;
  }
}
