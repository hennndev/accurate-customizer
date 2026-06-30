<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$accurate = app(\App\Services\AccurateService::class);
$dbInfo = \App\Models\AccurateDatabase::first();

try {
    echo "Fetching branch list for other-deposit handler...\n";
    $branches = $accurate->fetchModuleData('/api/branch/list.do', [], $dbInfo->toArray());
    echo "Branch list OK: " . count($branches) . " branches.\n";
} catch (\Exception $e) {
    echo "Branch fetch failed: " . $e->getMessage() . "\n";
}

try {
    echo "\nFetching other-deposit list...\n";
    $result = $accurate->fetchModuleDataPage('/api/other-deposit/list.do', [], 1, 10, $dbInfo->toArray());
    echo "Success! " . count($result['data'] ?? []) . " items found.\n";
} catch (\Exception $e) {
    echo "Fetch Failed: " . $e->getMessage() . "\n";
    // If it threw ACCURATE_TOKEN_INVALID, we need to bypass DataFetcher to see the real response!
}

// Bypass DataFetcher to see exactly what Accurate returns
echo "\n--- DIRECT HTTP CALL ---\n";
try {
    $client = app(\App\Services\Accurate\DatabaseClientManager::class)->getDataClientForDatabase($dbInfo->toArray(), null, 60);
    $response = $client->get('/api/other-deposit/list.do', [
        'sp.page' => 1,
        'sp.pageSize' => 10
    ]);
    
    echo "Status: " . $response->status() . "\n";
    echo "Body: " . $response->body() . "\n";
} catch (\Exception $e) {
    echo "Direct HTTP Failed: " . $e->getMessage() . "\n";
}
