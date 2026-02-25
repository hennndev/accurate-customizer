<?php

namespace App\Modules\Handlers;

use App\Services\AccurateService;
use Illuminate\Support\Facades\Log;

class WarehouseHandler extends BaseHandler
{
    protected array $allowedFields = [
        'id',
        'name',
        'city',
        'country',
        'pic',
        'province',
        'scrapWarehouse',
        'street',
        'suspended',
        'zipCode',
        'description',
        'branchName', // hasil transform dari branchId
    ];

    public function preCapture(AccurateService $accurate, array &$sharedContext): void
    {
        // Fetch branch list once
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
        // Transform branchId → branchName
        $branchList = $sharedContext['branchList'] ?? [];
        if (isset($detailData['branchId']) && !empty($branchList)) {
            $branchId = $detailData['branchId'];
            if (isset($branchList[$branchId]['name'])) {
                $detailData['branchName'] = $branchList[$branchId]['name'];
                Log::info('WAREHOUSE_BRANCH_TRANSFORMED', [
                    'item_id'         => $meta['itemId'] ?? null,
                    'old_branch_id'   => $branchId,
                    'new_branch_name' => $detailData['branchName'],
                ]);
            } else {
                Log::warning('WAREHOUSE_BRANCH_NOT_FOUND_IN_LIST', [
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

        $missingFields = array_diff($this->allowedFields, array_keys($filteredData));
        if (!empty($missingFields)) {
            Log::warning('WAREHOUSE_MISSING_FIELDS', [
                'item_id'        => $meta['itemId'] ?? $detailData['id'] ?? null,
                'missing_fields' => $missingFields,
            ]);
        }

        $detailData = $filteredData;

        Log::info('WAREHOUSE_DETAIL_FILTERED', [
            'item_id'      => $detailData['id'] ?? null,
            'name'         => $detailData['name'] ?? null,
            'fields_count' => count($filteredData),
            'fields'       => array_keys($filteredData),
        ]);
    }
}
