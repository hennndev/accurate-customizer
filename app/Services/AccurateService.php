<?php

namespace App\Services;

use App\Services\Accurate\DataFetcher;
use App\Services\Accurate\DatabaseClientManager;
use App\Services\Accurate\TransactionSaver;

class AccurateService
{
    protected DatabaseClientManager $databaseClientManager;
    protected DataFetcher $dataFetcher;
    protected TransactionSaver $transactionSaver;

    public function __construct(
        DatabaseClientManager $databaseClientManager,
        DataFetcher $dataFetcher,
        TransactionSaver $transactionSaver
    ) {
        $this->databaseClientManager = $databaseClientManager;
        $this->dataFetcher = $dataFetcher;
        $this->transactionSaver = $transactionSaver;
    }

    public function getDatabaseList(): array
    {
        return $this->databaseClientManager->getDatabaseList();
    }

    public function getDatabaseHost()
    {
        return $this->databaseClientManager->getDatabaseHost();
    }

    public function bulkSaveToAccurate(string $endpoint, array $data, ?array $targetDbInfo = null, ?string $accessToken = null)
    {
        return $this->transactionSaver->bulkSaveToAccurate($endpoint, $data, $targetDbInfo, $accessToken);
    }

    public function openDatabaseById(int $dbId): ?array
    {
        return $this->databaseClientManager->openDatabaseById($dbId);
    }

    public function fetchModuleDataPage(
        string $endpoint,
        array $params = [],
        int $pageNumber = 1,
        int $pageSize = 50,
        ?array $targetDbInfo = null,
        ?string $accessToken = null
    ): array
    {
        return $this->dataFetcher->fetchModuleDataPage($endpoint, $params, $pageNumber, $pageSize, $targetDbInfo, $accessToken);
    }

    public function fetchModuleData(
        string $endpoint,
        array $params = [],
        ?array $targetDbInfo = null,
        ?string $accessToken = null
    ): array
    {
        return $this->dataFetcher->fetchModuleData($endpoint, $params, $targetDbInfo, $accessToken);
    }
}
