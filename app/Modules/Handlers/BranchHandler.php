<?php

namespace App\Modules\Handlers;

use App\Services\AccurateService;
use Illuminate\Support\Facades\Log;

class BranchHandler extends BaseHandler
{
    protected array $allowedFields = [
        'id',
        'name',
        'city',
        'province',
        'country',
        'zipCode',
        'street',
    ];

    public function preCapture(AccurateService $accurate, array &$sharedContext): void
    {
        // No pre-fetch needed for branch
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
            Log::warning('BRANCH_MISSING_FIELDS', [
                'item_id'        => $meta['itemId'] ?? $detailData['id'] ?? null,
                'missing_fields' => $missingFields,
            ]);
        }

        $detailData = $filteredData;

        Log::info('BRANCH_DETAIL_FILTERED', [
            'item_id'      => $detailData['id'] ?? null,
            'name'         => $detailData['name'] ?? null,
            'fields_count' => count($filteredData),
            'fields'       => array_keys($filteredData),
        ]);
    }
}
