<?php

namespace App\Console\Commands;

use App\Models\Inventory\Asset;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Scheduled Artisan command that checks expiring software licenses and dispatches reminders to administrators.
 */
class CheckSoftwareLicenseExpirations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'licenses:check-expirations {--dry-run : Simulate execution without sending notifications}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check software licenses approaching expiration and dispatch renewal reminders to administrators';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $today = Carbon::now()->startOfDay();

        $this->info("Checking software license expirations for {$today->format('Y-m-d')}..." . ($isDryRun ? ' [DRY RUN]' : ''));

        // 1. Transition overdue software licenses to 'Lisensi Kedaluwarsa'
        if (!$isDryRun) {
            $expiredCount = Asset::expireOverdueLicenses();
            if ($expiredCount > 0) {
                $this->info("Transitioned {$expiredCount} software asset(s) to 'Lisensi Kedaluwarsa'.");
            }
        }

        $softwareAssets = Asset::with(['lot.model.brand', 'lot.model.type.category', 'location.parent'])
            ->whereNotNull('license_exp_date')
            ->where(function ($query) {
                $query->whereNull('renewal_reminder')
                    ->orWhere('renewal_reminder', '>', 0);
            })
            ->whereNotIn('status', ['Dihapus', 'Lisensi Kedaluwarsa'])
            ->whereHas('lot.model.type.category', function ($query) {
                $query->where('code', 'SOFT')
                    ->orWhereRaw('LOWER(name) = ?', ['software']);
            })
            ->get();

        $notifiedCount = 0;
        $emailCount = 0;

        foreach ($softwareAssets as $asset) {
            $expDate = Carbon::parse($asset->license_exp_date)->startOfDay();
            $daysRemaining = (int) $today->diffInDays($expDate, false);

            // Determine if today is a scheduled notification milestone
            $shouldNotify = false;
            $sendEmail = false;

            if ($daysRemaining === 30 || $daysRemaining === 21 || $daysRemaining === 14) {
                $shouldNotify = true;
            } elseif ($daysRemaining === 7) {
                $shouldNotify = true;
                $sendEmail = true;
            } elseif ($daysRemaining >= 0 && $daysRemaining <= 6) {
                // Daily notifications until the day of expiration (0 days)
                $shouldNotify = true;
            }

            if (!$shouldNotify) {
                continue;
            }

            $this->line("Asset {$asset->number} expires in {$daysRemaining} day(s) on {$expDate->format('Y-m-d')}" . ($sendEmail ? ' [EMAIL + IN-APP]' : ' [IN-APP]'));

            if (!$isDryRun) {
                $notificationService->notifySoftwareLicenseExpiring($asset, $daysRemaining, $sendEmail);
            }

            $notifiedCount++;
            if ($sendEmail) {
                $emailCount++;
            }
        }

        $this->info("Completed license expiration check: {$notifiedCount} notification(s) dispatched, {$emailCount} email notice(s) sent.");

        return Command::SUCCESS;
    }
}
