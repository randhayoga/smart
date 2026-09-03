<?php

namespace App\Actions\Request;

use App\Models\AdmUser;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestApproval;
use App\Models\Request\RequestStatusLog;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;

/**
 * Process Request Approval Action handling manager decisions on borrow and requisition requests.
 */
class ProcessRequestApproval
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}
    /**
     * Execute the approval or rejection process for a given request.
     *
     * @param  \App\Models\Request\Request  $req
     * @param  string  $decision  'approve' or 'reject'
     * @param  string|null  $note
     * @param  \App\Models\AdmUser|int  $approver
     * @param  string  $source  'in_app' or 'email'
     * @return void
     */
    public function execute(
        SmartRequest $req,
        string $decision,
        ?string $note,
        AdmUser|int $approver,
        string $source = 'in_app'
    ): void {
        if ($req->status !== 'wait') {
            return;
        }

        $approverUser = $approver instanceof AdmUser ? $approver : AdmUser::find($approver);
        $approverId = $approverUser?->id ?? (int) $approver;
        $approverName = $approverUser?->name ?? ($req->approver?->name ?? 'Manager');

        DB::transaction(function () use ($req, $decision, $note, $approverId, $approverName, $source) {
            $oldStatus = $req->status;
            $actionWord = $decision === 'approve' ? 'Disetujui' : 'Ditolak';
            $actionWordLower = $decision === 'approve' ? 'disetujui' : 'ditolak';
            $suffix = $source === 'email' ? ' via Email' : '';

            $defaultApprovalNote = "{$actionWord} oleh Manager: {$approverName}{$suffix}";
            $defaultLogNote = "Permintaan {$actionWordLower} oleh Manager: {$approverName}{$suffix}.";

            RequestApproval::create([
                'request_id' => $req->id,
                'approver_id' => $approverId,
                'decision' => $decision,
                'note' => !empty($note) ? $note : $defaultApprovalNote,
                'decided_at' => now(),
            ]);

            $newStatus = $decision === 'approve' ? 'approve' : 'reject';
            $req->update(['status' => $newStatus]);

            RequestStatusLog::create([
                'request_id' => $req->id,
                'status_from' => $oldStatus,
                'status_to' => $newStatus,
                'changed_by' => $approverId,
                'note' => !empty($note) ? $note : $defaultLogNote,
            ]);
        });

        if ($approverUser) {
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

            if ($decision === 'approve') {
                $this->notificationService->notifyRequesterRequestApproved($req, $approverUser);
            } else {
                $this->notificationService->notifyRequesterRequestRejected($req, $approverUser, $note);
            }
        }
    }
}
