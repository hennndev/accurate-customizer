<?php

namespace App\Modules\Handlers;

use App\Services\AccurateService;
use Illuminate\Support\Facades\Log;

class ReceiveItemHandler extends BaseHandler
{
  protected array $allowedFields = [
    'receiveNumber',
    'transDate',
    'vendorNo',
    'vendor',
    'branchName',    // hasil transform dari branchId
    'description',
    'detailItem',
    'purchaseOrder',
    'fobName',
    'cashDiscount',
    'currencyCode',
    'inclusiveTax',
    'paymentTermName',
    'rate',
    'shipDate',
    'shipmentName',
    'taxDate',
    'taxable',
    'toAddress',
    'charField1',
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
        Log::warning('RECEIVE_ITEM_BRANCH_NOT_FOUND_IN_LIST', [
          'item_id'            => $meta['itemId'] ?? null,
          'branch_id'          => $branchId,
          'available_branches' => array_keys($branchList),
        ]);
      }
    }

    if (!empty($detailData['number'])) {
      $detailData['charField1'] = (string) $detailData['number'];
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

