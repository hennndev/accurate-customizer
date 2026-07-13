<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$mappings = \App\Models\TransactionNumberMapping::where('module_slug', 'sales-receipt')->get();
$updatedCount = 0;
$deletedCount = 0;

foreach ($mappings as $m) {
    // Find the transaction where JSON data->number matches $m->new_number
    $t = \App\Models\Transaction::whereHas('module', function($q) { $q->where('slug', 'sales-receipt'); })
        ->get()
        ->first(function($tr) use ($m) {
            $data = json_decode($tr->data, true);
            return ($data['number'] ?? null) == $m->new_number || ($data['no'] ?? null) == $m->new_number;
        });

    if ($t) {
        // Check if there is already a mapping with the same unique key
        $exists = \App\Models\TransactionNumberMapping::where('accurate_database_id', $m->accurate_database_id)
            ->where('module_slug', $m->module_slug)
            ->where('old_number', $t->transaction_no)
            ->where('id', '!=', $m->id)
            ->first();

        if ($exists) {
            echo "Mapping ID {$m->id} is a duplicate of Mapping ID {$exists->id}. Deleting duplicate mapping ID {$m->id}.\n";
            $m->delete();
            $deletedCount++;
        } else {
            echo "Mapping ID {$m->id}: Old old_number '{$m->old_number}' -> New old_number '{$t->transaction_no}'\n";
            $m->old_number = $t->transaction_no;
            $m->save();
            $updatedCount++;
        }
    } else {
        echo "Mapping ID {$m->id} (new_number '{$m->new_number}'): Matching transaction not found.\n";
    }
}

echo "Total updated mappings: $updatedCount\n";
echo "Total deleted duplicate mappings: $deletedCount\n";
