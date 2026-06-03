<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$r = \App\Models\Request\Request::where('request_number', '052026-0004')->first();
if ($r) {
    echo "ID: " . $r->id . "\n";
    echo "Number: " . $r->request_number . "\n";
    echo "Status: " . $r->status . "\n";

    $daysPassed = '-';
    // Use user_confirmed_at if available, else updated_at
    $confirmedAt = $r->handover?->user_confirmed_at ?? $r->updated_at;

    if ($confirmedAt) {
        $diffPassed = $confirmedAt->diffInDays(now(), false);
        $daysPassedVal = (int) $diffPassed;
        $daysPassed = (string)$daysPassedVal . ' hari';
    }
    echo "Days Passed: " . $daysPassed . "\n";
} else {
    echo "Request not found\n";
}
