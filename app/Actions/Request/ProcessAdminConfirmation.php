<?php

namespace App\Actions\Request;

use App\Models\AdmUser;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestAdminConfirmation;
use App\Models\Request\RequestStatusLog;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;

/**
 * Process Admin Confirmation Action handling admin confirmation and rejection decisions on requests.
 */
class ProcessAdminConfirmation
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Execute admin confirmation or rejection for a given request.
     *
     * @param  \App\Models\Request\Request  $req
     * @param  string  $action  'confirm' or 'reject'
     * @param  string|null  $note
     * @param  \App\Models\AdmUser|int  $admin
     * @return void
     */
    public function execute(
        SmartRequest $req,
        string $action,
        ?string $note,
        AdmUser|int $admin
    ): void {
        $adminUser = $admin instanceof AdmUser ? $admin : AdmUser::find($admin);
        $adminId = $adminUser?->id ?? (int) $admin;
        $adminName = $adminUser?->name ?? 'Admin';

        $executed = DB::transaction(function () use ($req, $action, $note, $adminId, $adminName) {
            $lockedRequest = SmartRequest::where('id', $req->id)->lockForUpdate()->first();
            if (!$lockedRequest || $lockedRequest->status !== 'approve') {
                return false;
            }

            $oldStatus = $lockedRequest->status;
            $actionWord = $action === 'confirm' ? 'Dikonfirmasi' : 'Ditolak';
            $actionWordLower = $action === 'confirm' ? 'dikonfirmasi' : 'ditolak';

            $defaultConfirmationNote = "{$actionWord} oleh Admin: {$adminName}";
            $defaultLogNote = "Permintaan {$actionWordLower} oleh Admin: {$adminName}.";

            RequestAdminConfirmation::create([
                'request_id' => $lockedRequest->id,
                'admin_id' => $adminId,
                'action' => $action,
                'note' => !empty($note) ? $note : $defaultConfirmationNote,
                'decided_at' => now(),
            ]);

            $newStatus = $action === 'confirm' ? 'confirm' : 'reject';
            $lockedRequest->update(['status' => $newStatus]);
            $req->status = $newStatus;

            if ($action === 'reject') {
                $lockedRequest->items()->update(['status' => 'rejected']);
            }

            RequestStatusLog::create([
                'request_id' => $lockedRequest->id,
                'status_from' => $oldStatus,
                'status_to' => $newStatus,
                'changed_by' => $adminId,
                'note' => !empty($note) ? $note : $defaultLogNote,
            ]);

            return true;
        });

        if ($executed && $adminUser) {
            $req->loadMissing([
                'user',
                'department',
                'project',
                'items.barang.brand',
                'items.barang.subcategory.category',
                'items.barang.uom',
                'items.subcategory.category',
                'items.subcategory.barangs.uom',
            ]);

            if ($action === 'reject') {
                $this->notificationService->notifyRequesterRequestRejected($req, $adminUser, $note);
            }
        }
    }
}
