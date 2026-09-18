<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Job used to verify that the queue worker is actively processing queued jobs.
 */
class TestQueueJob implements ShouldQueue
{
    use Queueable;

    public string $key;
    public string $value;

    /**
     * Create a new job instance.
     */
    public function __construct(string $key, string $value = 'processed')
    {
        $this->key = $key;
        $this->value = $value;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Cache::put($this->key, $this->value, 300);
    }
}
