<?php

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$rows = DB::table('failed_jobs')->orderBy('failed_at', 'desc')->limit(3)->get();
foreach ($rows as $r) {
    echo "--- FAILED JOB {$r->uuid} ---\n";
    echo "Queue: {$r->queue}\n";
    echo "Connection: {$r->connection}\n";
    echo "Failed at: {$r->failed_at}\n";
    // Extract just the error message from the exception trace
    $lines = explode("\n", $r->exception);
    $firstLine = $lines[0] ?? 'unknown';
    echo 'Error: '.substr($firstLine, 0, 300)."\n\n";
}
