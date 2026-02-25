<?php

namespace App\Modules\Handlers;

use App\Services\AccurateService;
use Illuminate\Support\Facades\Log;

class ItemHandler extends BaseHandler
{
    /**
     * Fields yang ingin disimpan untuk module Item
     */
    protected array $allowedFields = [
        'id',
        'no',
        'name',
        'materialProduced',
        'itemProduced',
        'upcNo',
        'itemType',
        'unitPrice',
        'unit1Name'
    ];

    public function preCapture(AccurateService $accurate, array &$sharedContext): void
    {
        // Tidak perlu fetch data lain untuk item
        // Bisa ditambahkan nanti jika perlu (misal: category list, unit list)
    }

    public function transformDetail(array &$detailData, array $sharedContext, array $meta = []): void
    {
        // Filter hanya field yang dibutuhkan
        $filteredData = [];
        
        foreach ($this->allowedFields as $field) {
            if (array_key_exists($field, $detailData)) {
                $filteredData[$field] = $detailData[$field];
            }
        }
        
        // Log jika ada field penting yang missing
        $missingFields = array_diff($this->allowedFields, array_keys($filteredData));
        if (!empty($missingFields)) {
            Log::warning('ITEM_MISSING_FIELDS', [
                'item_id' => $meta['itemId'] ?? $detailData['id'] ?? null,
                'missing_fields' => $missingFields,
            ]);
        }
        
        // Replace detailData dengan filtered data
        $detailData = $filteredData;
        
        Log::info('ITEM_DETAIL_FILTERED', [
            'item_id' => $detailData['id'] ?? null,
            'item_no' => $detailData['no'] ?? null,
            'fields_count' => count($filteredData),
            'fields' => array_keys($filteredData)
        ]);
    }
}
