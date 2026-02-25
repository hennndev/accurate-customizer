<?php

namespace App\Modules\Handlers;

use App\Services\AccurateService;
use Illuminate\Support\Facades\Log;

class VendorPriceHandler extends BaseHandler
{
    protected array $allowedFields = [
        'id',
        'transDate',
        'branchName',
        'currencyCode',
        'vendorNo',
        'description',
        'detailItem',

    ];

    public function preCapture(AccurateService $accurate, array &$sharedContext): void
    {
        // No pre-fetch needed for vendor price
    }

    public function transformDetail(array &$detailData, array $sharedContext, array $meta = []): void
    {
        // Extract nested vendor.vendorNo → vendorNo
        if (!isset($detailData['vendorNo']) && isset($detailData['vendor']['vendorNo'])) {
            $detailData['vendorNo'] = $detailData['vendor']['vendorNo'];
        }

        // Extract nested currency.code → currencyCode
        if (!isset($detailData['currencyCode']) && isset($detailData['currency']['code'])) {
            $detailData['currencyCode'] = $detailData['currency']['code'];
        }

        // NOTE: detailItem sub-item transformation (item.no → itemNo, itemUnit → itemUnitName)
        // is handled by AccurateService::cleanDataItem — do NOT pre-flatten here.

        $filteredData = [];
        foreach ($this->allowedFields as $field) {
            if (array_key_exists($field, $detailData)) {
                $filteredData[$field] = $detailData[$field];
            }
        }

        $missingFields = array_diff($this->allowedFields, array_keys($filteredData));
        if (!empty($missingFields)) {
            Log::warning('VENDOR_PRICE_MISSING_FIELDS', [
                'item_id'        => $meta['itemId'] ?? $detailData['id'] ?? null,
                'missing_fields' => $missingFields,
            ]);
        }

        $detailData = $filteredData;

        Log::info('VENDOR_PRICE_DETAIL_FILTERED', [
            'item_id'      => $detailData['id'] ?? null,
            'vendor_no'    => $detailData['vendorNo'] ?? null,
            'fields_count' => count($filteredData),
            'fields'       => array_keys($filteredData),
        ]);
    }
}
