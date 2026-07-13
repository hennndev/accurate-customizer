<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$t1 = new \App\Models\Transaction();
$t1->id = 6162;
$t1->data = '{"original": "data"}';

$transactions = collect([$t1]);
$targetNumbers = [6162 => "SI.TEST.1"];

foreach ($transactions as $t) {
    if (!empty($targetNumbers[$t->id])) {
        $data = is_string($t->data) ? json_decode($t->data, true) : (array)$t->data;
        $data['number'] = $targetNumbers[$t->id];
        $data['_custom_number'] = true;
        $t->data = json_encode($data);
    }
}

$grouped = $transactions->groupBy('module.slug');
foreach ($grouped as $slug => $group) {
    foreach ($group as $transaction) {
        echo $transaction->data . "\n";
    }
}
