<?php

namespace App\Services\Accurate;

use Illuminate\Support\Facades\Log;

class NumberMappingManager
{
    private ModuleFieldProvider $fieldProvider;

    public function __construct(ModuleFieldProvider $fieldProvider)
    {
        $this->fieldProvider = $fieldProvider;
    }

    public function storeNumberMappings(
        string $endpoint,
        array $originalData,
        array $responseData,
        int $accurateDatabaseId
    ): void {
        preg_match('/\/api\/([^\/]+)\//', $endpoint, $matches);
        $moduleSlug = $matches[1] ?? null;

        if (!$moduleSlug) {
            return;
        }

        $moduleSlug = $this->resolveEffectiveModuleSlug($moduleSlug, $originalData);

        Log::info('NUMBER_MAPPING_ATTEMPT', [
            'module' => $moduleSlug,
            'database_id' => $accurateDatabaseId,
            'items_count' => count($originalData),
            'response_s' => $responseData['s'] ?? null,
            'response_d_count' => is_array($responseData['d'] ?? null) ? count($responseData['d']) : gettype($responseData['d'] ?? null),
        ]);

        // Skip number mapping for master data modules
        if ($this->isMasterDataModule($moduleSlug)) {
            return;
        }

        $numberField = $this->fieldProvider->getNumberFieldForModule($moduleSlug, $originalData[0] ?? []);
        if (!$numberField || $numberField === 'id') {
            Log::warning('NUMBER_MAPPING_SKIPPED_NO_FIELD', [
                'module' => $moduleSlug,
                'number_field' => $numberField,
            ]);
            return;
        }

        $results = $responseData['d'] ?? [];
        if (!is_array($results)) {
            Log::warning('NUMBER_MAPPING_SKIPPED_NO_RESULTS', [
                'module' => $moduleSlug,
                'response_d_type' => gettype($responseData['d'] ?? null),
                'response_s' => $responseData['s'] ?? null,
            ]);
            $results = [];
        }
        if (isset($results['s']) && is_bool($results['s'])) {
            $results = [$results];
        }

        if (empty($results)) {
            Log::warning('NUMBER_MAPPING_SKIPPED_EMPTY_RESULTS', [
                'module' => $moduleSlug,
                'response_keys' => array_keys($responseData),
            ]);
            return;
        }

        foreach ($results as $index => $result) {
            if (!is_array($result)) {
                continue;
            }

            $normalizedResult = $result;
            if (!isset($normalizedResult['r']) && isset($normalizedResult['d']) && is_array($normalizedResult['d'])) {
                $normalizedResult['r'] = $normalizedResult['d'];
            }

            if (!isset($result['s']) || $result['s'] !== true) {
                continue;
            }

            $oldNumber = $originalData[$index]['_sourceNumber']
                ?? $originalData[$index][$numberField]
                ?? $originalData[$index]['charField1']  // preserved by handler before number field is stripped by DataCleaner
                ?? $originalData[$index]['charField2']
                ?? null;

            if (!$oldNumber || !isset($normalizedResult['r']) || !is_array($normalizedResult['r'])) {
                Log::warning('NUMBER_MAPPING_SKIPPED', [
                    'database_id'       => $accurateDatabaseId,
                    'module'            => $moduleSlug,
                    'index'             => $index,
                    'number_field'      => $numberField,
                    'has_old_number'    => !empty($oldNumber),
                    'has_response_r'    => isset($normalizedResult['r']) && is_array($normalizedResult['r']),
                    'result_keys'       => is_array($result) ? array_keys($result) : [],
                    'original_keys'     => array_keys($originalData[$index] ?? []),
                    'result_s'          => $result['s'] ?? null,
                ]);
                continue;
            }

            // Normalize number field in response to always be in r['number']
            if (!isset($normalizedResult['r']['number']) && isset($normalizedResult['r'][$numberField])) {
                $normalizedResult['r']['number'] = $normalizedResult['r'][$numberField];
            }
            if (!isset($normalizedResult['r']['number']) && isset($normalizedResult['d']) && is_array($normalizedResult['d']) && isset($normalizedResult['d'][$numberField])) {
                $normalizedResult['r']['number'] = $normalizedResult['d'][$numberField];
            }
            // Normalize module-specific alternate number fields to 'number'
            // e.g. receive-item returns 'receiveNumber', some modules return 'no'
            $altFields = ['receiveNumber', 'no'];
            foreach ($altFields as $altField) {
                if (!isset($normalizedResult['r']['number']) && !empty($normalizedResult['r'][$altField])) {
                    $normalizedResult['r']['number'] = $normalizedResult['r'][$altField];
                    break;
                }
                if (!isset($normalizedResult['r']['number']) && isset($normalizedResult['d']) && is_array($normalizedResult['d']) && !empty($normalizedResult['d'][$altField])) {
                    $normalizedResult['r']['number'] = $normalizedResult['d'][$altField];
                    break;
                }
            }

            // Always prioritize the injected custom preview number if available, 
            // as requested by the user, to ensure mapping matches the preview.
            if (!empty($originalData[$index]['_custom_number'])) {
                $customNumber = $originalData[$index]['number'] ?? $originalData[$index]['no'] ?? null;
                if ($customNumber) {
                    $normalizedResult['r']['number'] = $customNumber;
                }
            }

            \App\Models\TransactionNumberMapping::storeMapping(
                $accurateDatabaseId,
                $moduleSlug,
                $oldNumber,
                $normalizedResult
            );

            Log::info('NUMBER_MAPPING_STORED', [
                'database_id' => $accurateDatabaseId,
                'module'      => $moduleSlug,
                'old_number'  => $oldNumber,
                'new_number'  => $normalizedResult['r']['number'] ?? null,
            ]);
        }
    }

    public function getMappedNumber(string $moduleSlug, string $oldNumber, ?int $targetDbId = null): string
    {
        $accurateDatabaseId = $targetDbId ?? $this->resolveLocalAccurateDatabaseId();

        if ($accurateDatabaseId) {
            $newNumber = \App\Models\TransactionNumberMapping::getNewNumber(
                $accurateDatabaseId,
                $moduleSlug,
                $oldNumber
            );
            if ($newNumber) {
                return $newNumber;
            }
        }

        $fallback = \App\Models\TransactionNumberMapping::where('old_number', $oldNumber)
            ->when($accurateDatabaseId, fn($q) => $q->where('accurate_database_id', $accurateDatabaseId))
            ->first();

        return $fallback?->new_number ?? $oldNumber;
    }

    private function resolveLocalAccurateDatabaseId(): ?int
    {
        $localId = session('accurate_database._local_db_id');
        if ($localId) {
            return (int) $localId;
        }

        $sessionDbId = session('database_id') ?? session('accurate_database.id');
        if (!$sessionDbId) {
            return null;
        }

        $accurateDb = \App\Models\AccurateDatabase::where('db_id', $sessionDbId)->first();
        if ($accurateDb) {
            return (int) $accurateDb->id;
        }

        $accurateDbByPk = \App\Models\AccurateDatabase::find($sessionDbId);
        if ($accurateDbByPk) {
            return (int) $accurateDbByPk->id;
        }

        return null;
    }

    private function isMasterDataModule(string $moduleSlug): bool
    {
        $masterDataModules = [
            'customer', 'vendor', 'item', 'branch', 'department', 'employee', 'warehouse', 'project',
            'customer-category', 'vendor-category', 'item-category', 'price-category', 'data-classification',
            'vendor-price', 'glaccount', 'currency', 'tax', 'unit', 'fob', 'bill-of-material'
        ];

        return in_array($moduleSlug, $masterDataModules, true);
    }

    private function resolveEffectiveModuleSlug(string $moduleSlug, array $originalData): string
    {
        if (!in_array($moduleSlug, ['sales-invoice', 'purchase-invoice'], true)) {
            return $moduleSlug;
        }

        if ($this->isDownPaymentPayload($originalData)) {
            return 'down-payment-' . $moduleSlug;
        }

        return $moduleSlug;
    }

    private function isDownPaymentPayload(array $originalData): bool
    {
        foreach ($originalData as $item) {
            if (!is_array($item)) {
                continue;
            }

            $flag = $item['invoiceDp'] ?? $item['invoiceDP'] ?? null;
            if ($flag === true || $flag === 1 || $flag === '1' || $flag === 'true') {
                return true;
            }
        }

        return false;
    }
}
