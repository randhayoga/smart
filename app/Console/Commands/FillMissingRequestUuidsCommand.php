<?php

namespace App\Console\Commands;

use App\Models\Request\Request;
use Illuminate\Console\Command;

class FillMissingRequestUuidsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'requests:fill-missing-uuids';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill missing UUIDv7 for requests table (useful after legacy data migration)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting UUID backfill for requests table...');

        $updatedCount = Request::fillMissingUuids();

        $this->info("Successfully updated {$updatedCount} request record(s) with new UUIDv7.");

        return Command::SUCCESS;
    }
}
