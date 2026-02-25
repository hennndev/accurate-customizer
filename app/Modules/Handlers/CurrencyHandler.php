<?php

namespace App\Modules\Handlers;

use App\Services\AccurateService;
use Illuminate\Support\Facades\Log;

class CurrencyHandler extends BaseHandler
{
    protected array $allowedFields = [
        'id',
        'code',
    ];

    public function preCapture(AccurateService $accurate, array &$sharedContext): void
    {
        // No pre-fetch needed for currency
    }

    public function transformDetail(array &$detailData, array $sharedContext, array $meta = []): void
    {
        $filteredData = [];
        foreach ($this->allowedFields as $field) {
            if (array_key_exists($field, $detailData)) {
                $filteredData[$field] = $detailData[$field];
            }
        }

        $missingFields = array_diff($this->allowedFields, array_keys($filteredData));
        if (!empty($missingFields)) {
            Log::warning('CURRENCY_MISSING_FIELDS', [
                'item_id'        => $meta['itemId'] ?? $detailData['id'] ?? null,
                'missing_fields' => $missingFields,
            ]);
        }

        $detailData = $filteredData;

        Log::info('CURRENCY_DETAIL_FILTERED', [
            'item_id'      => $detailData['id'] ?? null,
            'code'         => $detailData['code'] ?? null,
            'fields_count' => count($filteredData),
            'fields'       => array_keys($filteredData),
        ]);
    }
}
