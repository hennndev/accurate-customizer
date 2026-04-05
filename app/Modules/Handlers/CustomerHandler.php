<?php

namespace App\Modules\Handlers;

use App\Services\AccurateService;
use Illuminate\Support\Facades\Log;

class CustomerHandler extends BaseHandler
{
    /**
     * Fields yang ingin disimpan untuk module Customer
     */
    protected array $allowedFields = [
        'id',
        'transDate',
        'billCity',
        'billProvince',
        'billCountry',
        'billStreet',
        'billZipCode',
        'categoryName',
        'customerTaxType',
        'description',
        'detailContact',
        'detailOpenBalance',
        'detailShipAddress',
        'fax',
        'mobilPhone',
        'npwpNo',
        'shipCity',
        'shipProvince',
        'shipCountry',
        'shipStreet',
        'shipZipCode',
        'shipSameAsBill',
        'customerNo',
        'name',
        'address',
        'workPhone',
        'email',
        'taxNumber',
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
        // Transform contactInfo object -> single-item detailContact array
        if (isset($detailData['contactInfo']) && is_array($detailData['contactInfo'])) {
            $contactInfo = $detailData['contactInfo'];

            $detailData['detailContact'] = [[
                'id' => $contactInfo['id'] ?? null,
                'name' => $contactInfo['name'] ?? null,
                'email' => $contactInfo['email'] ?? null,
                'mobilePhone' => $contactInfo['mobilePhone'] ?? null,
                'workPhone' => $contactInfo['workPhone'] ?? null,
                'homePhone' => $contactInfo['homePhone'] ?? null,
                'fax' => $contactInfo['fax'] ?? null,
                'companyName' => $contactInfo['companyName'] ?? null,
                'position' => $contactInfo['position'] ?? null,
                'salutation' => $contactInfo['salutation'] ?? null,
                'website' => $contactInfo['website'] ?? null,
                'notes' => $contactInfo['notes'] ?? null,
                'customer' => $contactInfo['customer'] ?? null,
                'vendor' => $contactInfo['vendor'] ?? null,
                'project' => $contactInfo['project'] ?? null,
                'salesman' => $contactInfo['salesman'] ?? null,
                'branchId' => $contactInfo['branchId'] ?? null,
                'seq' => $contactInfo['seq'] ?? null,
                'optLock' => $contactInfo['optLock'] ?? null,
            ]];
        }

        // Transform billAddress object -> flat bill* fields
        if (isset($detailData['billAddress']) && is_array($detailData['billAddress'])) {
            $billAddress = $detailData['billAddress'];

            $detailData['billCity'] = $billAddress['city'] ?? ($detailData['billCity'] ?? null);
            $detailData['billCountry'] = $billAddress['country'] ?? ($detailData['billCountry'] ?? null);
            $detailData['billProvince'] = $billAddress['province'] ?? ($detailData['billProvince'] ?? null);
            $detailData['billStreet'] = $billAddress['street'] ?? ($detailData['billStreet'] ?? null);
            $detailData['billZipCode'] = $billAddress['zipCode'] ?? ($detailData['billZipCode'] ?? null);
        }

        // Transform shipAddress object -> flat ship* fields
        if (isset($detailData['shipAddress']) && is_array($detailData['shipAddress'])) {
            $shipAddress = $detailData['shipAddress'];

            $detailData['shipCity'] = $shipAddress['city'] ?? ($detailData['shipCity'] ?? null);
            $detailData['shipCountry'] = $shipAddress['country'] ?? ($detailData['shipCountry'] ?? null);
            $detailData['shipProvince'] = $shipAddress['province'] ?? ($detailData['shipProvince'] ?? null);
            $detailData['shipStreet'] = $shipAddress['street'] ?? ($detailData['shipStreet'] ?? null);
            $detailData['shipZipCode'] = $shipAddress['zipCode'] ?? ($detailData['shipZipCode'] ?? null);
        }

        $detailData['shipSameAsBill'] = $detailData['shipSameAsBill'] ?? false; 
        // Transform branchId → branchName
        $branchList = $sharedContext['branchList'] ?? [];
        if (isset($detailData['branchId']) && !empty($branchList)) {
            $branchId = $detailData['branchId'];
            if (isset($branchList[$branchId]['name'])) {
                $detailData['branchName'] = $branchList[$branchId]['name'];
                Log::info('CUSTOMER_BRANCH_TRANSFORMED', [
                    'item_id' => $meta['itemId'] ?? null,
                    'old_branch_id' => $branchId,
                    'new_branch_name' => $detailData['branchName'],
                ]);
            } else {
                Log::warning('CUSTOMER_BRANCH_NOT_FOUND_IN_LIST', [
                    'item_id' => $meta['itemId'] ?? null,
                    'branch_id' => $branchId,
                    'available_branches' => array_keys($branchList),
                ]);
            }
        }

        // Filter hanya field yang dibutuhkan
        $filteredData = [];
        foreach ($this->allowedFields as $field) {
            if (array_key_exists($field, $detailData)) {
                $filteredData[$field] = $detailData[$field];
            }
        }
        $detailData = $filteredData;
        Log::info("data customer pushed", ['data' => $detailData]);
    }
}
