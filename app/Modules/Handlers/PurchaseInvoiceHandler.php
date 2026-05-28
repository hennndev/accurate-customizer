<?php

namespace App\Modules\Handlers;

use App\Services\AccurateService;
use Illuminate\Support\Facades\Log;

class PurchaseInvoiceHandler extends BaseHandler
{
    protected array $allowedFields = [
        'number',
        'vendor',
        'billNumber',
        'cashDiscount',
        'charField2',
        'transDate',
        'vendorNo',
        'branchName',    // hasil transform dari branchId
        'description',
        'currencyCode',
        'detailItem',
        // 'detailDownPayment',
        'detailExpense',
        'documentCode',
        'documentTransaction',
        'fillPriceByVendorPrice',
        'fobName',
        'fiscalRate',
        'inclusiveTax',
        'inputDownPayment',
        'invoiceDP',
        'orderDownPaymentNumber',
        'paymentTermName',
        'rate',
        'reverseInvocie',
        'shipDate',
        'shipmenName',
        'tax1Name',
        'taxDate',
        'taxNumber',
        'taxable',
        'toAddress',
        'vendorTaxType',
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
      }
    }

    if (isset($detailData['detailItem']) && is_array($detailData['detailItem'])) {
      foreach ($detailData['detailItem'] as $index => $item) {
        if (is_array($item) && array_key_exists('receiveItemId', $item)) {
          unset($detailData['detailItem'][$index]['receiveItemId']);
        }
        if (is_array($item) && array_key_exists('receiveItemDetail', $item)) {
          unset($detailData['detailItem'][$index]['receiveItemDetail']);
          unset($detailData['detailItem'][$index]['receiveItemDetailId']);
        }
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
