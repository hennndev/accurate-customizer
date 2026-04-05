<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionNumberMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'accurate_database_id',
        'module_slug',
        'old_number',
        'new_number',
        'response_data',
    ];

    protected $casts = [
        'response_data' => 'array',
    ];

    /**
     * Get the new number for a given old number
     */
    public static function getNewNumber(int $databaseId, string $moduleSlug, string $oldNumber): ?string
    {
        $mapping = self::where('accurate_database_id', $databaseId)
            ->where('module_slug', $moduleSlug)
            ->where('old_number', $oldNumber)
            ->first();
        
        return $mapping?->new_number;
    }

    /**
     * Store mapping from migration response
     */
    public static function storeMapping(int $databaseId, string $moduleSlug, string $oldNumber, array $response): void
    {
        // Extract new number from response
        $newNumber = $response['r']['number']
            ?? $response['r']['receiveNumber']
            ?? $response['r']['no']
            ?? $response['d']['number']
            ?? $response['d']['receiveNumber']
            ?? $response['d']['no']
            ?? null;
        
        if ($newNumber) {
            $responseSummary = [
                's' => $response['s'] ?? null,
                'd' => is_string($response['d'] ?? null) ? $response['d'] : null,
                'r' => [
                    'id' => $response['r']['id'] ?? $response['d']['id'] ?? null,
                    'number' => $response['r']['number'] ?? $response['d']['number'] ?? null,
                    'receiveNumber' => $response['r']['receiveNumber'] ?? $response['d']['receiveNumber'] ?? null,
                    'no' => $response['r']['no'] ?? $response['d']['no'] ?? null,
                    'transDate' => $response['r']['transDate'] ?? $response['d']['transDate'] ?? null,
                ],
            ];

            self::updateOrCreate(
                [
                    'accurate_database_id' => $databaseId,
                    'module_slug' => $moduleSlug,
                    'old_number' => $oldNumber,
                ],
                [
                    'new_number' => $newNumber,
                    'response_data' => $responseSummary,
                ]
            );
        }
    }
}
