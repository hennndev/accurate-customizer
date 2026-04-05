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
        if (!isset($responseData['s']) || $responseData['s'] !== true) {
            return;
        }

        preg_match('/\/api\/([^\/]+)\//', $endpoint, $matches);
        $moduleSlug = $matches[1] ?? null;

        if (!$moduleSlug) {
            return;
        }

        $numberField = $this->fieldProvider->getNumberFieldForModule($moduleSlug, $originalData[0] ?? []);
        if (!$numberField || $numberField === 'id') {
            return;
        }

        $results = $responseData['d'] ?? [];
        if (!is_array($results)) {
            $results = [];
        }
        if (isset($results['s']) && is_bool($results['s'])) {
            $results = [$results];
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

            $oldNumber = $originalData[$index][$numberField]
                ?? $originalData[$index]['_sourceNumber']
                ?? null;

            if ($oldNumber && isset($normalizedResult['r']) && is_array($normalizedResult['r'])) {
                if (!isset($normalizedResult['r']['number']) && isset($normalizedResult['r'][$numberField])) {
                    $normalizedResult['r']['number'] = $normalizedResult['r'][$numberField];
                }
                if (!isset($normalizedResult['r']['number']) && isset($normalizedResult['d']) && is_array($normalizedResult['d']) && isset($normalizedResult['d'][$numberField])) {
                    $normalizedResult['r']['number'] = $normalizedResult['d'][$numberField];
                }

                \App\Models\TransactionNumberMapping::storeMapping(
                    $accurateDatabaseId,
                    $moduleSlug,
                    $oldNumber,
                    $normalizedResult
                );

                Log::info('NUMBER_MAPPING_STORED', [
                    'database_id' => $accurateDatabaseId,
                    'module' => $moduleSlug,
                    'old_number' => $oldNumber,
                    'new_number' => $normalizedResult['r']['number'] ?? $normalizedResult['r']['receiveNumber'] ?? $normalizedResult['r']['no'] ?? null,
                ]);
            } else {
                Log::warning('NUMBER_MAPPING_SKIPPED', [
                    'database_id' => $accurateDatabaseId,
                    'module' => $moduleSlug,
                    'index' => $index,
                    'number_field' => $numberField,
                    'has_old_number' => !empty($oldNumber),
                    'has_response_r' => isset($normalizedResult['r']) && is_array($normalizedResult['r']),
                    'result_keys' => is_array($result) ? array_keys($result) : [],
                ]);
            }
        }
    }

    public function getMappedNumber(string $moduleSlug, string $oldNumber): string
    {
        $accurateDatabaseId = session('accurate_database.id') ?? null;

        if (!$accurateDatabaseId) {
            $dbId = session('database_id');
            if ($dbId && ($moduleSlug !== "employee")) {
                $accurateDb = \App\Models\AccurateDatabase::where('db_id', $dbId)->first();
                $accurateDatabaseId = $accurateDb?->id;
            }
        }

        if (!$accurateDatabaseId) {
            return $oldNumber;
        }

        $newNumber = \App\Models\TransactionNumberMapping::getNewNumber(
            $accurateDatabaseId,
            $moduleSlug,
            $oldNumber
        );
        return $newNumber ?? $oldNumber;
    }
}
