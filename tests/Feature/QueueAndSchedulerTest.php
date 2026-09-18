<?php

namespace Tests\Feature;

use App\Jobs\TestQueueJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use RuntimeException;
use Tests\TestCase;

/**
 * Failing job fixture for queue error-handling tests.
 */
class FailingQueueJobFixture implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        throw new RuntimeException('Intentional queue failure for testing');
    }
}

/**
 * Feature tests for Laravel Queue (database driver) and Task Scheduler.
 */
class QueueAndSchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('queue.default', 'database');
    }

    /**
     * Test that jobs are stored in the database 'jobs' table and successfully processed by the worker.
     */
    public function test_database_queue_dispatches_and_processes_job_successfully(): void
    {
        $testKey = 'test_queue_run_' . uniqid();
        $this->assertNull(Cache::get($testKey));

        // Dispatch job to the database queue
        dispatch(new TestQueueJob($testKey, 'job_completed_successfully'));

        // Assert job exists in the SQL Server database queue table
        $this->assertDatabaseHas('jobs', [
            'queue' => 'default',
        ]);

        // Run one job via artisan queue:work
        $exitCode = Artisan::call('queue:work', [
            '--once' => true,
            '--sleep' => 0,
        ]);

        $this->assertSame(0, $exitCode);

        // Verify the job was executed and updated the cache
        $this->assertSame('job_completed_successfully', Cache::get($testKey));

        // Assert the job was removed from the jobs table
        $this->assertDatabaseCount('jobs', 0);
    }

    /**
     * Test that failing jobs are correctly handled and logged into 'failed_jobs' table.
     */
    public function test_database_queue_logs_failed_jobs_to_failed_jobs_table(): void
    {
        // Clear any previous failed jobs
        DB::table('failed_jobs')->truncate();

        // Dispatch job that intentionally throws an exception
        dispatch(new FailingQueueJobFixture());

        $this->assertDatabaseHas('jobs', [
            'queue' => 'default',
        ]);

        // Process with 1 attempt so it fails immediately
        Artisan::call('queue:work', [
            '--once' => true,
            '--tries' => 1,
            '--sleep' => 0,
        ]);

        // Verify it was logged into failed_jobs table
        $this->assertDatabaseCount('failed_jobs', 1);

        // Verify it was cleared from active jobs
        $this->assertDatabaseCount('jobs', 0);
    }

    /**
     * Test that Laravel Scheduler executes scheduled tasks successfully.
     */
    public function test_scheduler_executes_scheduled_tasks(): void
    {
        $flagKey = 'test_scheduler_executed_' . uniqid();

        Schedule::call(function () use ($flagKey) {
            Cache::put($flagKey, true, 60);
        })->everyMinute();

        $exitCode = Artisan::call('schedule:run');

        $this->assertSame(0, $exitCode);
        $this->assertTrue(Cache::get($flagKey));
    }

    /**
     * Test that database queue configuration conforms to production standards.
     */
    public function test_queue_production_configuration_integrity(): void
    {
        $this->assertSame('database', Config::get('queue.connections.database.driver'));
        $this->assertSame('jobs', Config::get('queue.connections.database.table'));
        $this->assertGreaterThanOrEqual(90, (int) Config::get('queue.connections.database.retry_after'));
    }
}
