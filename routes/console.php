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

// ==========================================
// [PHASE 2 - TASK SCHEDULER]
// Uncomment and register scheduled recurring commands below when transitioning to Phase 2:
// use Illuminate\Support\Facades\Schedule;
//
// Schedule::command('model:prune')->daily();
// ==========================================
