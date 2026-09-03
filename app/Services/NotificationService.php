<?php

namespace App\Services;

use App\Mail\DMUnitStatusRequest;
use App\Mail\ManagerRequestApprovalMail;
use App\Models\AdmUser;
use App\Models\Inventory\Barang;
use App\Models\Inventory\Unit;
use App\Models\Inventory\UnitStatusApproval;
use App\Models\Request\Request as SmartRequest;
use App\Notifications\AppNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Notification Service handling application alerts, role-targeted broadcasts, low-stock alerts, and approval notices.
 */
class NotificationService
{
    /**
     * Send a notification to a specific user.
     *
     * @param AdmUser $user
     * @param string $title
     * @param string $message
     * @param string $type
     * @param string|null $url
     * @param array<string, mixed> $extra
     */
    public function sendToUser(
        AdmUser $user,
        string $title,
        string $message,
        string $type = 'info',
        ?string $url = null,
        array $extra = []
    ): void {
        $user->notify(new AppNotification($title, $message, $type, $url, $extra));
    }

    /**
     * Send a notification to a collection or array of users.
     *
     * @param iterable<AdmUser> $users
     */
    public function sendToUsers(
        iterable $users,
        string $title,
        string $message,
        string $type = 'info',
        ?string $url = null,
        array $extra = []
    ): void {
        foreach ($users as $user) {
            $this->sendToUser($user, $title, $message, $type, $url, $extra);
        }
    }

    /**
     * Send a notification to all users holding a specific dynamic role.
     * Roles: 'admin' | 'ifs_manager' | 'manager' | 'user'
     *
     * @param string $role
     * @param string $title
     * @param string $message
     * @param string $type
     * @param string|null $url
     * @param array<string, mixed> $extra
     */
    public function sendToRole(
        string $role,
        string $title,
        string $message,
        string $type = 'info',
        ?string $url = null,
        array $extra = []
    ): void {
        $targetUsers = AdmUser::getUsersByRole($role);

        foreach ($targetUsers as $user) {
            $this->sendToUser($user, $title, $message, $type, $url, $extra);
        }
    }

    /**
     * Send a notification to all users holding any of the specified dynamic roles.
     *
     * @param array<string> $roles
     */
    public function sendToMultipleRoles(
        array $roles,
        string $title,
        string $message,
        string $type = 'info',
        ?string $url = null,
        array $extra = []
    ): void {
        $targetUsers = AdmUser::getUsersByRole($roles);

        foreach ($targetUsers as $user) {
            $this->sendToUser($user, $title, $message, $type, $url, $extra);
        }
    }

    /**
     * Check if a consumable barang's stock is equal to or below its min_stock_threshold,
     * and notify all Admins if true.
     *
     * @param Barang $barang
     * @return bool Whether notifications were sent
     */
    public function checkAndNotifyLowStock(Barang $barang): bool
    {
        $barang->loadMissing(['subcategory.category', 'uom', 'brand']);

        $isConsumable = (bool) ($barang->subcategory?->category?->is_consumable ?? false);
        if (!$isConsumable) {
            return false;
        }

        if (is_null($barang->min_stock_threshold)) {
            return false;
        }

        $currentStock = (int) $barang->lots()->sum('current_quantity');

        if ($currentStock <= $barang->min_stock_threshold) {
            $targetAdmins = AdmUser::getUsersByRole('admin');
            if ($targetAdmins->isEmpty()) {
                return false;
            }

            // Deduplication: Avoid sending if an unread notification for this barang was sent within the last 2 hours
            $firstAdmin = $targetAdmins->first();
            $recentUnreadExists = $firstAdmin->unreadNotifications()
                ->where('data', 'like', '%"barang_id":' . $barang->id . '%')
                ->where('created_at', '>=', now()->subHours(2))
                ->exists();

            if ($recentUnreadExists) {
                return false;
            }

            $uomName = $barang->uom->name ?? 'unit';
            $brandName = $barang->brand->name ?? '';
            $fullName = trim(($brandName ? $brandName . ' ' : '') . $barang->name);
            $title = "Peringatan Stok Minimum: {$fullName}";
            $message = "Stok barang {$barang->number} saat ini {$currentStock} {$uomName}, telah mencapai atau di bawah batas minimum ({$barang->min_stock_threshold} {$uomName}).";

            $barangRouteKey = preg_replace('/[^a-zA-Z0-9]/', '', (string)$barang->number);
            $this->sendToRole(
                'admin',
                $title,
                $message,
                'warning',
                "/smart/inventory/stok-habis-pakai/{$barangRouteKey}",
                [
                    'barang_id' => $barang->id,
                    'current_stock' => $currentStock,
                    'min_stock_threshold' => $barang->min_stock_threshold,
                ]
            );

            return true;
        }

        return false;
    }

    /**
     * Check all consumable barangs and send notifications for any that are at or below minimum stock threshold.
     *
     * @return int Number of barangs that triggered low stock notifications
     */
    public function checkAllConsumableLowStock(): int
    {
        $consumableBarangs = Barang::whereHas('subcategory.category', function ($query) {
            $query->where('is_consumable', true);
        })->whereNotNull('min_stock_threshold')->get();

        $notifiedCount = 0;
        foreach ($consumableBarangs as $barang) {
            if ($this->checkAndNotifyLowStock($barang)) {
                $notifiedCount++;
            }
        }

        return $notifiedCount;
    }

    /**
     * Send a notification to IFS Manager when an asset status is switched to Pending:DM.
     *
     * @param Unit $unit
     */
    public function notifyIfsManagerPendingDm(Unit $unit): void
    {
        $unit->loadMissing('lot.barang.brand');
        $brand = $unit->lot?->barang?->brand?->name ?? '';
        $assetName = $unit->lot?->barang?->name ?? '';
        $brandAndName = trim("{$brand} {$assetName}") ?: $unit->number;

        $title = "Penghapusan Aset {$brandAndName}: Perlu Perhatian Anda";
        $message = "Penghapusan aset {$unit->number} telah disetujui oleh BoD/BoC dan sekarang memerlukan approval Anda";

        // 1. In-app database + Mercure notification
        $this->sendToRole(
            'ifs_manager',
            $title,
            $message,
            'warning',
            "/smart/approve-status?search=" . urlencode($unit->number),
            [
                'unit_id' => $unit->id,
                'unit_number' => $unit->number,
            ]
        );

        // 2. Email notification to IFS Manager(s)
        try {
            $ifsUsers = AdmUser::getUsersByRole('ifs_manager');
            foreach ($ifsUsers as $ifsUser) {
                $email = $ifsUser->email;
                if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Mail::to($email)->send(new DMUnitStatusRequest($unit, null, $ifsUser->name));
                }
            }
        } catch (\Throwable $e) {
            Log::error("Gagal mengirim email DMUnitStatusRequest untuk unit {$unit->number}: " . $e->getMessage(), [
                'exception' => $e,
                'unit_id' => $unit->id,
            ]);
        }
    }

    /**
     * Send a notification to all Admins when an asset status approval is decided by DM IFS.
     *
     * @param UnitStatusApproval $approval
     * @param string $decision 'approved' | 'rejected'
     */
    public function notifyAdminAssetStatusDecision(UnitStatusApproval $approval, string $decision): void
    {
        $approval->loadMissing(['unit.lot.barang.brand']);
        $unit = $approval->unit;
        if (!$unit) {
            return;
        }

        $brand = $unit->lot?->barang?->brand?->name ?? '';
        $name = $unit->lot?->barang?->name ?? '';
        $brandAndName = trim("{$brand} {$name}") ?: $unit->number;

        if ($decision === 'approved') {
            $currentCondition = $approval->proposed_condition;
            $title = "Penghapusan Aset {$brandAndName} Disetujui DM IFS";
            $message = "Status aset {$unit->number} telah berubah menjadi Tidak Aktif dan kondisi berubah menjadi {$currentCondition}.";
            $type = 'success';
        } else {
            $currentStatus = $approval->previous_status ?? 'Tersedia';
            $currentCondition = $approval->previous_condition ?? $unit->condition;
            $title = "Penghapusan Aset {$brandAndName} Ditolak DM IFS";
            $message = "Status aset {$unit->number} telah dikembalikan menjadi {$currentStatus} dan kondisi dikembalikan menjadi {$currentCondition}.";
            $type = 'error';
        }

        $this->sendToRole(
            'admin',
            $title,
            $message,
            $type,
            "/smart/inventory/assets?search=" . urlencode($unit->number),
            [
                'unit_id' => $unit->id,
                'unit_number' => $unit->number,
                'approval_id' => $approval->id,
                'decision' => $decision,
            ]
        );
    }

    /**
     * Send a real-time notification to the relevant manager when a user submits a new request or borrowing.
     *
     * @param SmartRequest $request
     * @param AdmUser $manager
     * @param string $type 'Permintaan' | 'Peminjaman'
     */
    public function notifyManagerNewRequest(SmartRequest $request, AdmUser $manager, string $type = 'Permintaan'): void
    {
        $request->loadMissing(['user', 'department', 'project']);

        $requesterName = $request->user?->name ?? 'Pengguna';
        $targetName = $request->utilization === 'corporate'
            ? ($request->department?->org_name ? "Corporate ({$request->department->org_name})" : 'Corporate')
            : ($request->project ? ($request->project->no_project ? "Project {$request->project->no_project} ({$request->project->project_name})" : "Project ({$request->project->project_name})") : 'Project');

        $title = "{$type} Baru: {$request->request_number}";
        $message = "{$requesterName} telah mengajukan " . strtolower($type) . " baru untuk {$targetName}.";

        $this->sendToUser(
            $manager,
            $title,
            $message,
            'info',
            '/smart/approve',
            [
                'request_id' => $request->id,
                'request_number' => $request->request_number,
                'type' => $type,
                'utilization' => $request->utilization,
            ]
        );

        // Dispatch email notification with signed 1-click approval URL
        if (!empty($manager->email)) {
            try {
                Mail::to($manager->email)->send(new ManagerRequestApprovalMail($request, $manager, $type));
            } catch (\Throwable $e) {
                Log::error("Failed to send approval email for request {$request->id} to {$manager->email}: " . $e->getMessage());
            }
        }
    }
}
