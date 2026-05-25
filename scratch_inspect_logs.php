<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$r = \App\Models\Request\Request::where('request_number', '052026-0004')->first();
if ($r) {
    echo "Request: " . $r->request_number . " (Status: " . $r->status . ")\n";
    foreach ($r->statusLogs as $log) {
        $user = \App\Models\User::find($log->changed_by);
        echo "  - " . $log->status_from . " -> " . $log->status_to . " by " . ($user ? $user->name : 'Unknown') . " (" . $log->note . ")\n";
    }
} else {
    echo "Request not found\n";
}
