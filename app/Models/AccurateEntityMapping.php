<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccurateEntityMapping extends Model
{
    protected $fillable = [
        'accurate_database_id',
        'module_slug',
        'source_identifier',
        'accurate_id',
        'accurate_number',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'accurate_id' => 'integer'
    ];

    /**
     * Relationship to AccurateDatabase
     */
    public function accurateDatabase(): BelongsTo
    {
        return $this->belongsTo(AccurateDatabase::class);
    }

    /**
     * Store or update entity mapping after successful save to Accurate.
     * Uses accurate_number as the unique key to prevent duplicate rows.
     */
    public static function storeMapping(
        int $accurateDatabaseId,
        string $moduleSlug,
        string $sourceIdentifier,
        int $accurateId,
        ?string $accurateNumber = null,
        array $metadata = []
    ): self {
        $mapping = self::query()
            ->where('accurate_database_id', $accurateDatabaseId)
            ->where('module_slug', $moduleSlug)
            ->where(function ($query) use ($sourceIdentifier, $accurateNumber) {
                $query->where('source_identifier', $sourceIdentifier);

                if ($accurateNumber !== null && $accurateNumber !== '') {
                    $query->orWhere('accurate_number', $accurateNumber);
                }
            })
            ->first();

        $payload = [
            'accurate_database_id' => $accurateDatabaseId,
            'module_slug' => $moduleSlug,
            'source_identifier' => $sourceIdentifier,
            'accurate_id' => $accurateId,
            'accurate_number' => $accurateNumber ?? $sourceIdentifier,
            'metadata' => array_merge(
                $metadata,
                ['last_synced_at' => now()->toIso8601String()]
            ),
        ];

        if ($mapping) {
            $mapping->update($payload);
            return $mapping;
        }

        return self::create($payload);
    }

    /**
     * Check if entity already exists in Accurate
     */
    public static function exists(
        int $accurateDatabaseId,
        string $moduleSlug,
        string $number
    ): bool {
        return self::where('accurate_database_id', $accurateDatabaseId)
            ->where('module_slug', $moduleSlug)
            ->where('accurate_number', $number)
            ->exists();
    }

    /**
     * Get mapping details
     */
    public static function getMapping(
        int $accurateDatabaseId,
        string $moduleSlug,
        string $number
    ): ?self {
        return self::where('accurate_database_id', $accurateDatabaseId)
            ->where('module_slug', $moduleSlug)
            ->where('accurate_number', $number)
            ->first();
    }

    /**
     * Delete mapping (for rollback purposes)
     */
    public static function deleteMapping(
        int $accurateDatabaseId,
        string $moduleSlug,
        string $number
    ): bool {
        return self::where('accurate_database_id', $accurateDatabaseId)
            ->where('module_slug', $moduleSlug)
            ->where('accurate_number', $number)
            ->delete() > 0;
    }

    /**
     * Get all mappings for a module
     */
    public static function getMappingsForModule(
        int $accurateDatabaseId,
        string $moduleSlug
    ): \Illuminate\Database\Eloquent\Collection {
        return self::where('accurate_database_id', $accurateDatabaseId)
            ->where('module_slug', $moduleSlug)
            ->get();
    }
}
