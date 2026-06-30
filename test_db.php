<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$log = \App\Models\SystemLog::where('event_type', 'capture_queue')
    ->where('status', 'failed')
    ->orderBy('id', 'desc')
    ->first();

if ($log) {
    echo "LATEST FAILED CAPTURE LOG:\n";
    echo "Module: " . $log->module . "\n";
    echo "Error: " . ($log->payload['error'] ?? 'N/A') . "\n";
} else {
    echo "No failed log found.\n";
}
