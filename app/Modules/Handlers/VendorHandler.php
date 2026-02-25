<?php

namespace App\Modules\Handlers;

use App\Services\AccurateService;
use Illuminate\Support\Facades\Log;

class VendorHandler extends BaseHandler
{
    protected array $allowedFields = [
        'id',
        'transDate',
        'vendorNo',
        'name',
        'address',
        'mobilePhone',
        'workPhone',
        'email',
        'fax',
        'taxNumber',
        'npwpNo',
        'billCity',
        'billProvince',
        'billCountry',
        'billStreet',
        'billZipCode',
        'shipCity',
        'shipProvince',
        'shipCountry',
        'shipStreet',
        'shipZipCode',
        'shipSameAsBill',
        'categoryName',
        'currencyCode',
        'description',
        'detailContact',
        'detailOpenBalance',
        'website',
        'vendorTaxType',
        'taxCountry',
        'taxProvince',
        'taxCity',
        'taxStreet',
        'taxZipCode',
        'taxSameAsBill',
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
                Log::info('VENDOR_BRANCH_TRANSFORMED', [
                    'item_id'          => $meta['itemId'] ?? null,
                    'old_branch_id'    => $branchId,
                    'new_branch_name'  => $detailData['branchName'],
                ]);
            } else {
                Log::warning('VENDOR_BRANCH_NOT_FOUND_IN_LIST', [
                    'item_id'             => $meta['itemId'] ?? null,
                    'branch_id'           => $branchId,
                    'available_branches'  => array_keys($branchList),
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
            Log::warning('VENDOR_MISSING_FIELDS', [
                'item_id'        => $meta['itemId'] ?? $detailData['id'] ?? null,
                'missing_fields' => $missingFields,
            ]);
        }

        $detailData = $filteredData;

        Log::info('VENDOR_DETAIL_FILTERED', [
            'item_id'      => $detailData['id'] ?? null,
            'vendor_no'    => $detailData['vendorNo'] ?? null,
            'fields_count' => count($filteredData),
            'fields'       => array_keys($filteredData),
        ]);
    }
}
