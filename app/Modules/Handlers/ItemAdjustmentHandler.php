<?php

namespace App\Modules\Handlers;

use App\Services\AccurateService;
use Illuminate\Support\Facades\Log;

class ItemAdjustmentHandler extends BaseHandler
{
    protected array $allowedFields = [
        'approvalStatus',
        'branch',
        'createdByUserName',
        'description',
        'detailItem',
        'id',
        'number',
        'totalAmount',
        'transDate',
    ];

    public function transformDetail(array &$detailData, array $sharedContext, array $meta = []): void
    {
        if (isset($detailData['branch']) && is_array($detailData['branch'])) {
            $detailData['branch'] = $detailData['branch']['name']
                ?? $detailData['branch']['no']
                ?? $detailData['branch']['id']
                ?? null;
        }

        if (isset($detailData['createdByUser']) && is_array($detailData['createdByUser'])) {
            $detailData['createdByUserName'] = $detailData['createdByUser']['name']
                ?? $detailData['createdByUser']['fullName']
                ?? $detailData['createdByUser']['username']
                ?? null;
        } elseif (isset($detailData['createdByUserName']) && is_array($detailData['createdByUserName'])) {
            $detailData['createdByUserName'] = $detailData['createdByUserName']['name']
                ?? $detailData['createdByUserName']['fullName']
                ?? $detailData['createdByUserName']['username']
                ?? null;
        }

        if (isset($detailData['transDate']) && $detailData['transDate'] !== null && $detailData['transDate'] !== '') {
            $transDateValue = is_scalar($detailData['transDate']) ? (string) $detailData['transDate'] : json_encode($detailData['transDate']);
            $transDateText = 'TransDate: ' . $transDateValue;

            if (isset($detailData['description']) && is_string($detailData['description']) && trim($detailData['description']) !== '') {
                if (!str_contains($detailData['description'], $transDateText)) {
                    $detailData['description'] = trim($detailData['description']) . ' | ' . $transDateText;
                }
            } else {
                $detailData['description'] = $transDateText;
            }
        }

        $filteredData = [];
        foreach ($this->allowedFields as $field) {
            if (array_key_exists($field, $detailData)) {
                $filteredData[$field] = $detailData[$field];
            }
        }

        $missingFields = array_diff($this->allowedFields, array_keys($filteredData));
        if (!empty($missingFields)) {
            Log::warning('ITEM_ADJUSTMENT_MISSING_FIELDS', [
                'item_id' => $meta['itemId'] ?? $detailData['id'] ?? null,
                'missing_fields' => $missingFields,
            ]);
        }

        $detailData = $filteredData;

        Log::info('ITEM_ADJUSTMENT_DETAIL_FILTERED', [
            'item_id' => $detailData['id'] ?? null,
            'number' => $detailData['number'] ?? null,
            'fields_count' => count($filteredData),
            'fields' => array_keys($filteredData),
        ]);
    }
}
