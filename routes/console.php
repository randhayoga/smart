<?php

/**
 * Console and Artisan Command Routes
 *
 * Defines closure-based console commands and custom CLI utilities.
 */

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
