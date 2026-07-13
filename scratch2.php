<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$transactions = \App\Models\Transaction::limit(1)->get();
foreach ($transactions as $t) {
    $t->data = '{"test": true}';
}
$groupedByModule = $transactions->groupBy('module_id');
foreach ($groupedByModule as $group) {
    foreach ($group as $transaction) {
        echo "Data: " . $transaction->data . "\n";
    }
}
