<?php

namespace App\Modules\Handlers;

use App\Services\AccurateService;
use Illuminate\Support\Facades\Log;

class UnitHandler extends BaseHandler
{
    protected array $allowedFields = [
        'id',
        'name',
    ];

    public function preCapture(AccurateService $accurate, array &$sharedContext): void
    {
        // No pre-fetch needed for unit
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
            Log::warning('UNIT_MISSING_FIELDS', [
                'item_id'        => $meta['itemId'] ?? $detailData['id'] ?? null,
                'missing_fields' => $missingFields,
            ]);
        }

        $detailData = $filteredData;

        Log::info('UNIT_DETAIL_FILTERED', [
            'item_id'      => $detailData['id'] ?? null,
            'name'         => $detailData['name'] ?? null,
            'fields_count' => count($filteredData),
            'fields'       => array_keys($filteredData),
        ]);
    }
}
