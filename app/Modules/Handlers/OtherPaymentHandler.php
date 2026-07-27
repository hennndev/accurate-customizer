<?php

namespace App\Modules\Handlers;

use App\Services\AccurateService;
use Illuminate\Support\Facades\Log;

class OtherPaymentHandler extends BaseHandler
{
  protected array $allowedFields = [
    'number',
    'transDate',
    'branchId',
    'branchName',
    'description',
    'payee',
    'detailItem',
    'detailAccount',
    'bank',
    'bankNo',
    'chequeAmount',
    'chequeDate',
    'chequeNo',
    'currencyCode',
    'rate',
    'paymentMethod',
    'approvalStatus',
    'lastUpdate',
    'optLock',
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
      Log::error('OTHER_PAYMENT_FAILED_TO_FETCH_BRANCH_LIST', ['error' => $e->getMessage()]);
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
        Log::warning('OTHER_PAYMENT_BRANCH_NOT_FOUND', [
          'item_id'   => $meta['itemId'] ?? null,
          'branch_id' => $branchId,
        ]);
      }
    }

    if (isset($detailData['bank']['no']) && empty($detailData['bankNo'])) {
      $detailData['bankNo'] = $detailData['bank']['no'];
    }

    // Filter to allowed fields only
    $filteredData = [];
    foreach ($this->allowedFields as $field) {
      if (array_key_exists($field, $detailData)) {
        $filteredData[$field] = $detailData[$field];
      }
    }

    $missingFields = array_diff($this->allowedFields, array_keys($filteredData));
    if (!empty($missingFields)) {
      Log::warning('OTHER_PAYMENT_MISSING_FIELDS', [
        'item_id'        => $meta['itemId'] ?? null,
        'missing_fields' => array_values($missingFields),
      ]);
    }

    Log::info('OTHER_PAYMENT_DETAIL_FILTERED', [
      'item_id'      => $meta['itemId'] ?? null,
      'number'       => $filteredData['number'] ?? null,
      'fields_count' => count($filteredData),
      'fields'       => array_keys($filteredData),
    ]);

    $detailData = $filteredData;
  }
}
