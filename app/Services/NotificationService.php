<?php

namespace App\Services;

use App\Mail\DMAssetActivationRequest;
use App\Mail\DMBulkAssetActivationRequest;
use App\Mail\DMAssetDeletionRequest;
use App\Mail\DMBulkAssetDeletionRequest;
use App\Mail\ManagerRequestApprovalMail;
use App\Mail\RequesterRequestRejectedMail;
use App\Models\Inventory\Model;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetDeletionApproval;
use App\Models\Inventory\AssetActivationApproval;
use App\Models\Request\Request as SmartRequest;
use App\Models\User;
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
     * @param User $user
     * @param string $title
     * @param string $message
     * @param string $type
     * @param string|null $url
     * @param array<string, mixed> $extra
     */
    public function sendToUser(
        User $user,
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
     * @param iterable<User> $users
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
        $targetUsers = User::getUsersByRole($role);

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
        $targetUsers = User::getUsersByRole($roles);

        foreach ($targetUsers as $user) {
            $this->sendToUser($user, $title, $message, $type, $url, $extra);
        }
    }

    /**
     * Check if a consumable model's stock is equal to or below its min_stock_threshold,
     * and notify all Admins if true.
     *
     * @param Model $model
     * @return bool Whether notifications were sent
     */
    public function checkAndNotifyLowStock(Model $model): bool
    {
        $model->loadMissing(['type', 'uom', 'brand']);

        $isConsumable = (bool) $model->is_consumable;
        if (!$isConsumable) {
            return false;
        }

        if (is_null($model->min_stock_threshold)) {
            return false;
        }

        $currentStock = (int) $model->lots()->sum('current_quantity');

        if ($currentStock <= $model->min_stock_threshold) {
            $targetAdmins = User::getUsersByRole('admin');
            if ($targetAdmins->isEmpty()) {
                return false;
            }

            // Deduplication: Avoid sending if an unread notification for this model was sent within the last 2 hours
            $firstAdmin = $targetAdmins->first();
            $recentUnreadExists = $firstAdmin->unreadNotifications()
                ->where('data', 'like', '%"model_id":' . $model->id . '%')
                ->where('created_at', '>=', now()->subHours(2))
                ->exists();

            if ($recentUnreadExists) {
                return false;
            }

            $uomName = $model->uom->name ?? 'unit';
            $brandName = $model->brand->name ?? '';
            $fullName = trim(($brandName ? $brandName . ' ' : '') . $model->name);
            $title = "Peringatan Stok Minimum: {$fullName}";
            $message = "Stok model {$model->number} saat ini {$currentStock} {$uomName}, telah mencapai atau di bawah batas minimum ({$model->min_stock_threshold} {$uomName}).";

            $modelRouteKey = preg_replace('/[^a-zA-Z0-9]/', '', (string)$model->number);
            $this->sendToRole(
                'admin',
                $title,
                $message,
                'warning',
                "/smart/stock-management/consumables/{$modelRouteKey}",
                [
                    'model_id' => $model->id,
                    'barang_id' => $model->id,
                    'current_stock' => $currentStock,
                    'min_stock_threshold' => $model->min_stock_threshold,
                ]
            );

            return true;
        }

        return false;
    }

    /**
     * Check all consumable models and send notifications for any that are at or below minimum stock threshold.
     *
     * @return int Number of models that triggered low stock notifications
     */
    public function checkAllConsumableLowStock(): int
    {
        $consumableModels = Model::whereHas('type', function ($query) {
            $query->where('is_consumable', true);
        })->whereNotNull('min_stock_threshold')->get();

        $notifiedCount = 0;
        foreach ($consumableModels as $model) {
            if ($this->checkAndNotifyLowStock($model)) {
                $notifiedCount++;
            }
        }

        return $notifiedCount;
    }

    /**
     * Format a notification payload array into the active application locale.
     * Supports both new key-based notifications and legacy static string notifications.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function formatNotificationPayload(array $data): array
    {
        $titleKey = $data['title_key'] ?? ($data['extra']['title_key'] ?? null);
        $messageKey = $data['message_key'] ?? ($data['extra']['message_key'] ?? null);
        $titleParams = $data['title_params'] ?? ($data['extra']['title_params'] ?? []);
        $messageParams = $data['message_params'] ?? ($data['extra']['message_params'] ?? []);

        $title = $titleKey
            ? __($titleKey, $titleParams)
            : ($data['title'] ?? '');

        $message = $messageKey
            ? __($messageKey, $messageParams)
            : ($data['message'] ?? '');

        // Fallback for legacy database notifications stored as raw strings
        if (!$titleKey && isset($data['title'])) {
            if (preg_match('/(?:Penghapusan|Deaktivasi|Deletion|Deactivation)\s+Aset\s+(.+?)(?:\s+Disetujui\s+DM\s+IFS|\s+Approved\s+by\s+DM\s+IFS)/iu', $data['title'], $m)) {
                $title = __('inventory.notification_status_approved_title', ['name' => $m[1]]);
            } elseif (preg_match('/(?:Penghapusan|Deaktivasi|Deletion|Deactivation)\s+Aset\s+(.+?)(?:\s+Ditolak\s+DM\s+IFS|\s+oleh\s+DM\s+IFS|\s+Rejected\s+by\s+DM\s+IFS)/iu', $data['title'], $m)) {
                $title = __('inventory.notification_status_rejected_title', ['name' => $m[1]]);
            }
        }
        if (!$messageKey && isset($data['message'])) {
            if (preg_match('/(?:Status\s+aset|Asset)\s+(.+?)\s+(?:telah\s+berubah\s+menjadi|status\s+has\s+changed\s+to)\s+Tidak\s+Aktif,\s*Pending:BoD\/BoC/iu', $data['message'], $m)) {
                $message = __('inventory.notification_status_approved_message', ['number' => $m[1]]);
            } elseif (preg_match('/(?:Status\s+aset|Asset)\s+(.+?)\s+(?:telah\s+dikembalikan\s+menjadi|status\s+was\s+reverted\s+to)\s+(.+?)\s+(?:dan\s+kondisi\s+dikembalikan\s+menjadi|and\s+condition\s+reverted\s+to)\s+(.+?)\.?$/iu', $data['message'], $m)) {
                $message = __('inventory.notification_status_rejected_message', [
                    'number' => $m[1],
                    'status' => $m[2],
                    'condition' => $m[3],
                ]);
            }
        }

        return [
            ...$data,
            'title' => $title,
            'message' => $message,
        ];
    }

    /**
     * Send a notification to IFS Manager when an asset status is switched to Pending:DM.
     *
     * @param Asset $asset
     * @param AssetDeletionApproval|null $approval
     */
    public function notifyManagerAssetDeletionChange(Asset $asset, ?AssetDeletionApproval $approval = null): void
    {
        $asset->loadMissing('lot.model.brand');
        $brand = $asset->lot?->model?->brand?->name ?? '';
        $assetName = $asset->lot?->model?->name ?? '';
        $brandAndName = trim("{$brand} {$assetName}") ?: $asset->number;

        $titleKey = 'inventory.notification_deactivation_title';
        $titleParams = ['name' => $brandAndName];
        $messageKey = 'inventory.notification_deactivation_message';
        $messageParams = ['number' => $asset->number];

        $title = __($titleKey, $titleParams);
        $message = __($messageKey, $messageParams);

        // 1. In-app database + Mercure notification
        $this->sendToRole(
            'ifs_manager',
            $title,
            $message,
            'warning',
            "/smart/approve-deletion?search=" . urlencode($asset->number),
            [
                'asset_id' => $asset->id,
                'asset_number' => $asset->number,
                'title_key' => $titleKey,
                'title_params' => $titleParams,
                'message_key' => $messageKey,
                'message_params' => $messageParams,
            ]
        );

        // 2. Email notification to IFS Manager(s)
        try {
            $ifsUsers = User::getUsersByRole('ifs_manager');
            foreach ($ifsUsers as $ifsUser) {
                if (!$this->isAllowedIfsRecipient($ifsUser)) {
                    continue;
                }

                $email = $ifsUser->email;
                if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Mail::to($email)->send(new DMAssetDeletionRequest($asset, $approval, $ifsUser->name));
                }
            }
        } catch (\Throwable $e) {
            Log::error("Gagal mengirim email DMAssetDeletionRequest untuk aset {$asset->number}: " . $e->getMessage(), [
                'exception' => $e,
                'asset_id' => $asset->id,
            ]);
        }
    }

    /**
     * Send a consolidated batch notification and email to IFS Manager(s) for multiple assets submitted for deletion.
     *
     * @param iterable<Asset> $assets
     * @param string $proposedCondition
     * @param User|null $requester
     */
    public function notifyManagerBulkAssetDeletionChange(iterable $assets, string $proposedCondition, ?User $requester = null): void
    {
        $assetsCollection = collect($assets);
        $count = $assetsCollection->count();
        if ($count === 0) {
            return;
        }

        $titleKey = 'inventory.notification_bulk_deactivation_title';
        $titleParams = [
            'count' => $count,
            'condition' => $proposedCondition,
        ];
        $messageKey = 'inventory.notification_bulk_deactivation_message';
        $messageParams = [
            'count' => $count,
            'condition' => $proposedCondition,
        ];

        $title = __($titleKey, $titleParams);
        $message = __($messageKey, $messageParams);

        $firstAsset = $assetsCollection->first();
        $firstNumber = $firstAsset instanceof Asset ? $firstAsset->number : '';

        // 1. In-app database + Mercure notification
        $this->sendToRole(
            'ifs_manager',
            $title,
            $message,
            'warning',
            "/smart/approve-deletion",
            [
                'count' => $count,
                'proposed_condition' => $proposedCondition,
                'first_number' => $firstNumber,
                'type' => 'deletion_approval_bulk',
                'title_key' => $titleKey,
                'title_params' => $titleParams,
                'message_key' => $messageKey,
                'message_params' => $messageParams,
            ]
        );

        // 2. Email notification to IFS Manager(s)
        try {
            $sampleAssets = $assetsCollection->take(10)->map(function ($asset) {
                if (!$asset instanceof Asset) {
                    return null;
                }
                $asset->loadMissing(['lot.model.brand', 'location']);
                $brand = $asset->lot?->model?->brand?->name ?? '';
                $name = $asset->lot?->model?->name ?? '';
                $brandAndName = trim("{$brand} {$name}") ?: $asset->number;

                return [
                    'number' => $asset->number,
                    'name' => $brandAndName,
                    'location' => $asset->location?->full_name ?? $asset->location?->name ?? '-',
                ];
            })->filter()->values()->all();

            $ifsUsers = User::getUsersByRole('ifs_manager');
            foreach ($ifsUsers as $ifsUser) {
                if (!$this->isAllowedIfsRecipient($ifsUser)) {
                    continue;
                }

                $email = $ifsUser->email;
                if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Mail::to($email)->send(new DMBulkAssetDeletionRequest(
                        $count,
                        $proposedCondition,
                        $sampleAssets,
                        $requester,
                        $ifsUser->name
                    ));
                }
            }
        } catch (\Throwable $e) {
            Log::error("Gagal mengirim email DMBulkAssetDeletionRequest: " . $e->getMessage(), [
                'exception' => $e,
                'count' => $count,
            ]);
        }
    }

    /**
     * Send a notification to all Admins when an asset deletion approval is decided by DM IFS.
     *
     * @param AssetDeletionApproval $approval
     * @param string $decision 'approved' | 'rejected'
     */
    public function notifyAdminAssetDeletionDecision(AssetDeletionApproval $approval, string $decision): void
    {
        $approval->loadMissing(['asset.lot.model.brand']);
        $asset = $approval->asset;
        if (!$asset) {
            return;
        }

        $brand = $asset->lot?->model?->brand?->name ?? '';
        $name = $asset->lot?->model?->name ?? '';
        $brandAndName = trim("{$brand} {$name}") ?: $asset->number;

        if ($decision === 'approved') {
            $titleKey = 'inventory.notification_status_approved_title';
            $titleParams = ['name' => $brandAndName];
            $messageKey = 'inventory.notification_status_approved_message';
            $messageParams = ['number' => $asset->number];
            $type = 'success';
            $url = "/smart/stock-management/pending-deletion?tab=Pending:BoD/BoC&search=" . urlencode($asset->number);
        } else {
            $currentStatus = $approval->previous_status ?? 'Tersedia';
            $currentCondition = $approval->previous_condition ?? $asset->condition;
            $titleKey = 'inventory.notification_status_rejected_title';
            $titleParams = ['name' => $brandAndName];
            $messageKey = 'inventory.notification_status_rejected_message';
            $messageParams = [
                'number' => $asset->number,
                'status' => $currentStatus,
                'condition' => $currentCondition,
            ];
            $type = 'error';
            $url = "/smart/stock-management/non-consumables?search=" . urlencode($asset->number);
        }

        $title = __($titleKey, $titleParams);
        $message = __($messageKey, $messageParams);

        $this->sendToRole(
            'admin',
            $title,
            $message,
            $type,
            $url,
            [
                'asset_id' => $asset->id,
                'asset_number' => $asset->number,
                'approval_id' => $approval->id,
                'decision' => $decision,
                'title_key' => $titleKey,
                'title_params' => $titleParams,
                'message_key' => $messageKey,
                'message_params' => $messageParams,
            ]
        );
    }

    /**
     * Send an in-app notification and email to all IFS Managers when a new asset is registered and requires activation approval.
     *
     * @param Asset $asset
     * @param AssetActivationApproval|null $approval
     */
    public function notifyManagerNewAssetActivation(Asset $asset, ?AssetActivationApproval $approval = null): void
    {
        $asset->loadMissing(['lot.model.brand']);
        $brand = $asset->lot?->model?->brand?->name ?? '';
        $assetName = $asset->lot?->model?->name ?? '';
        $brandAndName = trim("{$brand} {$assetName}") ?: $asset->number;

        $title = "Approval Aset Baru {$brandAndName}: Perlu Perhatian Anda";
        $message = "Aset baru dengan nomor {$asset->number} memerlukan approval Anda.";

        // 1. In-app database + Mercure notification
        $this->sendToRole(
            'ifs_manager',
            $title,
            $message,
            'warning',
            "/smart/approve-activation?search=" . urlencode($asset->number),
            [
                'asset_id' => $asset->id,
                'asset_number' => $asset->number,
                'type' => 'activation_approval',
            ]
        );

        // 2. Email notification to IFS Manager(s)
        try {
            $ifsUsers = User::getUsersByRole('ifs_manager');
            foreach ($ifsUsers as $ifsUser) {
                if (!$this->isAllowedIfsRecipient($ifsUser)) {
                    continue;
                }

                $email = $ifsUser->email;
                if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Mail::to($email)->send(new DMAssetActivationRequest($asset, $approval, $ifsUser->name));
                }
            }
        } catch (\Throwable $e) {
            Log::error("Gagal mengirim email DMAssetActivationRequest untuk aset {$asset->number}: " . $e->getMessage(), [
                'exception' => $e,
                'asset_id' => $asset->id,
            ]);
        }
    }

    /**
     * Send a consolidated batch notification and email to IFS Manager(s) for multiple newly created assets.
     *
     * @param array|\Illuminate\Support\Collection $assets
     * @param Lot $lot
     * @param User|null $requester
     */
    public function notifyManagerBulkAssetActivation($assets, Lot $lot, ?User $requester = null): void
    {
        $assetsCollection = collect($assets);
        $count = $assetsCollection->count();
        if ($count === 0) {
            return;
        }

        $lot->loadMissing(['model.brand', 'model.type.category']);
        $brand = $lot->model?->brand?->name ?? '';
        $assetName = $lot->model?->name ?? '';
        $brandAndName = trim("{$brand} {$assetName}") ?: ($lot->number ?? 'Aset Baru');

        $firstAsset = $assetsCollection->first();
        $lastAsset = $assetsCollection->last();
        $firstNumber = $firstAsset instanceof Asset ? $firstAsset->number : ($firstAsset['number'] ?? '');
        $lastNumber = $lastAsset instanceof Asset ? $lastAsset->number : ($lastAsset['number'] ?? '');

        $locationText = '-';
        if ($firstAsset instanceof Asset && $firstAsset->location) {
            $locationText = $firstAsset->location->full_name ?? $firstAsset->location->name ?? '-';
        }

        $title = "Approval Aset Baru: {$count} Unit {$brandAndName}";
        $message = "Sebanyak {$count} unit aset baru untuk {$brandAndName} ({$firstNumber} - {$lastNumber}) memerlukan approval Anda.";

        // 1. In-app database + Mercure notification
        $this->sendToRole(
            'ifs_manager',
            $title,
            $message,
            'warning',
            "/smart/approve-activation?search=" . urlencode($firstNumber),
            [
                'lot_id' => $lot->id,
                'lot_number' => $lot->number,
                'count' => $count,
                'first_number' => $firstNumber,
                'last_number' => $lastNumber,
                'type' => 'activation_approval_bulk',
            ]
        );

        // 2. Email notification to IFS Manager(s)
        try {
            $ifsUsers = User::getUsersByRole('ifs_manager');
            foreach ($ifsUsers as $ifsUser) {
                if (!$this->isAllowedIfsRecipient($ifsUser)) {
                    continue;
                }

                $email = $ifsUser->email;
                if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Mail::to($email)->send(new DMBulkAssetActivationRequest(
                        $lot,
                        $count,
                        $firstNumber,
                        $lastNumber,
                        $locationText,
                        $requester,
                        $ifsUser->name
                    ));
                }
            }
        } catch (\Throwable $e) {
            Log::error("Gagal mengirim email DMBulkAssetActivationRequest untuk batch aset {$firstNumber} - {$lastNumber}: " . $e->getMessage(), [
                'exception' => $e,
                'lot_id' => $lot->id,
            ]);
        }
    }

    /**
     * Send a notification to all Admins when an asset activation approval is decided by DM IFS.
     *
     * @param AssetActivationApproval $approval
     * @param string $decision 'approved' | 'rejected'
     */
    public function notifyAdminAssetActivationDecision(AssetActivationApproval $approval, string $decision): void
    {
        $approval->loadMissing(['asset.lot.model.brand']);
        $asset = $approval->asset;
        if (!$asset) {
            return;
        }

        $brand = $asset->lot?->model?->brand?->name ?? '';
        $name = $asset->lot?->model?->name ?? '';
        $brandAndName = trim("{$brand} {$name}") ?: $asset->number;

        if ($decision === 'approved') {
            $title = "Aktivasi Aset {$brandAndName} Disetujui DM IFS";
            $message = "Status aset {$asset->number} telah berubah menjadi Tersedia.";
            $type = 'success';
            $url = "/smart/stock-management/non-consumables?search=" . urlencode($asset->number);
        } else {
            $title = "Aktivasi Aset {$brandAndName} Ditolak DM IFS";
            $message = "Aktivasi aset {$asset->number} ditolak oleh DM IFS. Status: Verifikasi Ditolak.";
            $type = 'error';
            $url = "/smart/stock-management/pending-activation?search=" . urlencode($asset->number);
        }

        $this->sendToRole(
            'admin',
            $title,
            $message,
            $type,
            $url,
            [
                'asset_id' => $asset->id,
                'asset_number' => $asset->number,
                'approval_id' => $approval->id,
                'decision' => $decision,
            ]
        );
    }

    /**
     * Send a real-time notification to the relevant manager when a user submits a new request or borrowing.
     *
     * @param SmartRequest $request
     * @param User $manager
     * @param string $type 'Permintaan' | 'Peminjaman'
     */
    public function notifyManagerNewRequest(SmartRequest $request, User $manager, string $type = 'Permintaan'): void
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
        if (!empty($manager->email) && $this->isAllowedRecipient($manager, $manager->email)) {
            try {
                // ==========================================
                // [PHASE 2 - QUEUED EMAIL DISPATCH]
                // In Phase 1: ->send() runs synchronously.
                // In Phase 2: switch to ->queue() when smart-queue is running.
                // ==========================================
                Mail::to($manager->email)->send(new ManagerRequestApprovalMail($request, $manager, $type));
            } catch (\Throwable $e) {
                Log::error("Failed to send approval email for request {$request->id} to {$manager->email}: " . $e->getMessage());
            }
        }
    }

    /**
     * Delete pending request notification(s) sent to a manager once the request is approved or rejected.
     *
     * @param SmartRequest $request
     * @param User|int $manager
     */
    public function deleteManagerRequestNotification(SmartRequest $request, User|int $manager): void
    {
        $managerUser = $manager instanceof User ? $manager : User::find($manager);
        if (!$managerUser) {
            return;
        }

        $managerUser->notifications()
            ->where(function ($query) use ($request) {
                $query->where('data->extra->request_id', $request->id)
                    ->orWhere('data', 'like', '%"request_id":' . $request->id . '%');
            })
            ->delete();
    }

    /**
     * Send in-app notification to all Admins when a request is approved by the manager.
     *
     * @param SmartRequest $request
     * @param User $manager
     */
    public function notifyAdminRequestApproved(SmartRequest $request, User $manager): void
    {
        $request->loadMissing(['user', 'items']);
        $type = $request->type_name;
        $title = "{$type}: {$request->request_number} Di-approve";
        $message = "Review jenis dan jumlah barang yang diminta pada halaman Inbox";

        $this->sendToRole(
            'admin',
            $title,
            $message,
            'info',
            '/smart/inbox',
            [
                'request_id' => $request->id,
                'request_uuid' => $request->uuid,
                'request_number' => $request->request_number,
                'type' => $type,
                'manager_id' => $manager->id,
                'manager_name' => $manager->name,
            ]
        );
    }

    /**
     * Send in-app notification to the requester when their request is approved by the manager.
     *
     * @param SmartRequest $request
     * @param User $manager
     */
    public function notifyRequesterRequestApproved(SmartRequest $request, User $manager): void
    {
        $request->loadMissing(['user', 'items']);
        $requester = $request->user;
        if (!$requester) {
            return;
        }

        $type = $request->type_name;
        $title = "{$type} {$request->request_number} Disetujui";
        $message = "{$manager->name} menyetujui {$type} Anda dan sekarang sedang diproses oleh Tim Aset.";

        $targetRouteKey = $request->uuid ?? $request->id;

        $this->sendToUser(
            $requester,
            $title,
            $message,
            'success',
            "/smart/history/{$targetRouteKey}",
            [
                'request_id' => $request->id,
                'request_uuid' => $request->uuid,
                'request_number' => $request->request_number,
                'type' => $type,
                'decision' => 'approve',
            ]
        );
    }

    /**
     * Send in-app notification and email to the requester when their request is rejected by the manager.
     *
     * @param SmartRequest $request
     * @param User $manager
     * @param string|null $reason
     */
    public function notifyRequesterRequestRejected(SmartRequest $request, User $manager, ?string $reason = null): void
    {
        $request->loadMissing(['user', 'department', 'project', 'items']);
        $requester = $request->user;
        if (!$requester) {
            return;
        }

        $type = $request->type_name;
        $title = "{$type} {$request->request_number} Ditolak";
        $message = "{$manager->name} menolak {$type} Anda, alasan penolakan dapat dilihat pada detail {$type}.";

        $targetRouteKey = $request->uuid ?? $request->id;

        $this->sendToUser(
            $requester,
            $title,
            $message,
            'error',
            "/smart/history/{$targetRouteKey}",
            [
                'request_id' => $request->id,
                'request_uuid' => $request->uuid,
                'request_number' => $request->request_number,
                'type' => $type,
                'decision' => 'reject',
                'reason' => $reason,
            ]
        );

        // Dispatch email notification to requester
        if (!empty($requester->email) && filter_var($requester->email, FILTER_VALIDATE_EMAIL) && $this->isAllowedRecipient($requester, $requester->email)) {
            try {
                // ==========================================
                // [PHASE 2 - QUEUED EMAIL DISPATCH]
                // In Phase 1: ->send() runs synchronously.
                // In Phase 2: switch to ->queue() when smart-queue is running.
                // ==========================================
                Mail::to($requester->email)->send(new RequesterRequestRejectedMail($request, $manager, $reason));
            } catch (\Throwable $e) {
                Log::error("Failed to send rejection email for request {$request->id} to {$requester->email}: " . $e->getMessage());
            }
        }
    }

    /**
     * Check if a recipient is safe to email in the current environment.
     * In non-production, strictly prevent sending emails to real IFS personnel (e.g. Sonny Handini or IFS department).
     */
    protected function isAllowedRecipient(?User $user, ?string $email): bool
    {
        if (app()->isProduction()) {
            return true;
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $emailLower = strtolower($email);

        // Explicit blacklists for non-production: never send to real IFS manager
        if (str_contains($emailLower, 'sonny.handini') || ($user && (string) ($user->employee_id ?? '') === '033340')) {
            Log::warning("Blocked email to actual IFS Manager in non-production environment: {$email}");
            return false;
        }

        if ($user) {
            $orgCode = $user->department?->org_code ?? $user->orgchart?->org_code;
            if ($orgCode === 'IFS') {
                Log::warning("Blocked email to user with IFS org code in non-production environment: {$email}");
                return false;
            }
        }

        return true;
    }

    /**
     * Check if an IFS manager recipient is allowed to receive emails.
     * In non-production, only Departemen Fasilitas Uji Coba (TEST-DEPT) is permitted.
     */
    protected function isAllowedIfsRecipient(User $ifsUser): bool
    {
        if (app()->isProduction()) {
            return true;
        }

        if (!$this->isAllowedRecipient($ifsUser, $ifsUser->email)) {
            return false;
        }

        $orgCode = $ifsUser->department?->org_code ?? $ifsUser->orgchart?->org_code;
        $isTestDept = ($orgCode === 'TEST-DEPT')
            || ((string) ($ifsUser->employee_id ?? '') === '999996')
            || (app()->runningUnitTests() && str_contains(strtolower($ifsUser->email ?? ''), 'example.com'));

        if (!$isTestDept) {
            Log::warning("Blocked sending IFS manager email in non-production to non-test user {$ifsUser->employee_id} ({$ifsUser->email}). Only Departemen Uji Coba (TEST-DEPT) is permitted.");
            return false;
        }

        return true;
    }
}
