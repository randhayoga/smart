<?php

require __DIR__ . '/public/index.php';

$app = app();
$requests = \App\Models\Request\Request::all();
foreach ($requests as $r) {
    echo $r->request_number . ': ' . $r->status . PHP_EOL;
}
