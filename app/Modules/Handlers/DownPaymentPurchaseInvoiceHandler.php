<?php

namespace App\Modules\Handlers;

use App\Services\AccurateService;

class DownPaymentPurchaseInvoiceHandler extends BaseHandler
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
        $sourceNumber = $detailData['number'] ?? $detailData['receiveNumber'] ?? null;

        if ($sourceNumber !== null && $sourceNumber !== '') {
            $detailData['charField1'] = (string) $sourceNumber;
        }

        if (empty($detailData['vendorNo']) && isset($detailData['vendor']['vendorNo'])) {
            $detailData['vendorNo'] = (string) $detailData['vendor']['vendorNo'];
        }

        $detailData['invoiceDp'] = true;
        $detailData['invoiceDP'] = true;

        $branchList = $sharedContext['branchList'] ?? [];
        if (isset($detailData['branchId']) && !empty($branchList)) {
            $branchId = $detailData['branchId'];
            if (isset($branchList[$branchId]['name'])) {
                $detailData['branchName'] = $branchList[$branchId]['name'];
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

        // Keep full payload for down payment purchase invoice capture.
    }
}
