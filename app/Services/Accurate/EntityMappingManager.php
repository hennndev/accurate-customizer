<?php

namespace App\Services\Accurate;

use Illuminate\Support\Facades\Log;

class EntityMappingManager
{
    private ModuleFieldProvider $fieldProvider;

    public function __construct(ModuleFieldProvider $fieldProvider)
    {
        $this->fieldProvider = $fieldProvider;
    }

    public function storeEntityMappings(
        string $module,
        array $originalData,
        array $responseData,
        int $accurateDatabaseId
    ): void {
        $numberField = $this->fieldProvider->getNumberFieldForModule($module, $originalData[0] ?? []);
        if (!$numberField) {
            return;
        }

        $results = $responseData['d'] ?? [];

        foreach ($results as $index => $result) {
            if (!isset($result['s']) || $result['s'] !== true) {
                continue;
            }

            if ($numberField === 'id' && isset($originalData[$index]['_sourceId'])) {
                $sourceIdentifier = $originalData[$index]['_sourceId'];
            } else {
                $sourceIdentifier = $originalData[$index][$numberField] ?? null;
            }

            $accurateId = $result['r']['id'] ?? $result['d']['id'] ?? null;
            $apiNumber = $result['r']['number'] ?? $result['r']['no'] ?? $result['r']['vendorNo'] ?? $result['r']['customerNo']
                ?? $result['d']['number'] ?? $result['d']['no'] ?? $result['d']['vendorNo'] ?? $result['d']['customerNo'] ?? null;

            $effectiveIdentifier = $sourceIdentifier ?? $apiNumber;
            $accurateNumber = $effectiveIdentifier;

            if (!$effectiveIdentifier || !$accurateId) {
                Log::warning('ENTITY_MAPPING_SKIP: missing identifier or accurateId', [
                    'module' => $module,
                    'index' => $index,
                    'effective_identifier' => $effectiveIdentifier,
                    'accurate_id' => $accurateId,
                    'result_keys' => array_keys($result),
                    'result_r_keys' => isset($result['r']) ? array_keys($result['r']) : null,
                    'result_d_keys' => isset($result['d']) ? array_keys((array) $result['d']) : null,
                ]);
                continue;
            }

            $wasUpdate = isset($originalData[$index]['_isUpdate']) && $originalData[$index]['_isUpdate'] === true;

            \App\Models\AccurateEntityMapping::storeMapping(
                $accurateDatabaseId,
                $module,
                $effectiveIdentifier,
                $accurateId,
                $accurateNumber,
                [
                    'synced_at' => now()->toIso8601String(),
                    'endpoint' => '/api/' . $module . '/bulk-save.do',
                    'operation' => $wasUpdate ? 'update' : 'create'
                ]
            );

            $this->updateTransactionStatus(
                $effectiveIdentifier,
                $module,
                $accurateDatabaseId,
                $wasUpdate ? \App\Models\Transaction::STATUS_PUSHED_UPDATE : \App\Models\Transaction::STATUS_PUSHED_CREATE
            );
        }
    }

    public function updateTransactionStatus(
        string $transactionNo,
        string $module,
        int $accurateDatabaseId,
        string $status
    ): void {
        try {
            $moduleRecord = \App\Models\Module::where('accurate_database_id', $accurateDatabaseId)
                ->where('slug', $module)
                ->first();
            
            if (!$moduleRecord) {
                return;
            }

            $transaction = \App\Models\Transaction::where('transaction_no', $transactionNo)
                ->where('module_id', $moduleRecord->id)
                ->where('accurate_database_id', $accurateDatabaseId)
                ->orderBy('id', 'desc')
                ->first();

            if ($transaction) {
                $transaction->update([
                    'push_status' => $status,
                    'last_pushed_at' => now(),
                    'push_count' => $transaction->push_count + 1
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Failed to update transaction status", [
                'transaction_no' => $transactionNo,
                'module' => $module,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
