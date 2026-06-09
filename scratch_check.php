<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Request\Request as SmartRequest;

$requests = SmartRequest::all();
echo "Total requests: " . $requests->count() . "\n";
foreach ($requests as $req) {
    echo "ID: " . $req->id 
       . " | Number: " . $req->request_number 
       . " | User ID: " . $req->user_id 
       . " | Approver ID: " . $req->approver_id 
       . " | Status: " . $req->status . "\n";
}
