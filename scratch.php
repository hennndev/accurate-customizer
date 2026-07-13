<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$transactions = \App\Models\Transaction::limit(2)->get();
foreach ($transactions as $t) {
    $data = is_string($t->data) ? json_decode($t->data, true) : (array)$t->data;
    $data['_custom_number'] = true;
    $t->data = json_encode($data);
}
$grouped = $transactions->groupBy('module_id');
foreach ($grouped as $group) {
    foreach ($group as $transaction) {
        $data = json_decode($transaction->data, true);
        echo "Has custom number: " . (isset($data['_custom_number']) ? 'Yes' : 'No') . "\n";
    }
}
