<?php

namespace App\Modules\Handlers;

use App\Services\AccurateService;
use Illuminate\Support\Facades\Log;

class PurchasePaymentHandler extends BaseHandler
{
  protected array $allowedFields = [
    'bank',
    'charField2',
    'detailItem',
    'bankNo',
    'chequeAmount',
    'detailInvoice',
    'transDate',
    'vendor',
    'vendorNo',
    'branchName',
    'chequeDate',
    'chequeNo',
    'currencyCode',
    'rate',
    'paymentMethod',
    'number',
    'description',
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
    if (!empty($detailData['number'])) {
      $detailData['charField2'] = (string) $detailData['number'];
    }

    // Transform branchId → branchName
    $branchList = $sharedContext['branchList'] ?? [];
    if (isset($detailData['branchId']) && !empty($branchList)) {
      $branchId = $detailData['branchId'];
      if (isset($branchList[$branchId]['name'])) {
        $detailData['branchName'] = $branchList[$branchId]['name'];
      } else {
        Log::warning('PURCHASE_PAYMENT_BRANCH_NOT_FOUND_IN_LIST', [
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
