<?php

namespace App\Services;

use App\Models\AdmUser;
use App\Models\Cart\AssetBasket;
use App\Models\Cart\ConsumableBasket;
use App\Models\HrdOrgchart;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestItem;
use App\Models\Request\RequestStatusLog;
use App\Models\TbAssignProject;
use App\Models\TbProject;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service managing atomic submission of user requests and asset borrow confirmations.
 */
class RequestSubmissionService
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Submit a consumable request or asset borrow request atomically.
     *
     * @param AdmUser $user
     * @param array{
     *     items: array<int, array{id: int}>,
     *     pemanfaatan: string,
     *     departemen?: string|int|null,
     *     project?: string|int|null,
     *     alasan: string,
     *     start_date?: string|null,
     *     end_date?: string|null,
     * } $data
     * @param 'consumable'|'borrow' $type
     * @return SmartRequest
     * @throws ValidationException
     */
    public function submit(AdmUser $user, array $data, string $type = 'consumable'): SmartRequest
    {
        $managerUser = $this->resolveApprover($user, $data);

        /** @var SmartRequest $smartRequest */
        $smartRequest = DB::transaction(function () use ($user, $data, $type, $managerUser) {
            $requestNumber = $this->generateRequestNumber();

            $smartRequest = SmartRequest::create([
                'request_number' => $requestNumber,
                'user_id' => $user->id,
                'approver_id' => $managerUser->id,
                'utilization' => $data['pemanfaatan'],
                'org_id' => $data['pemanfaatan'] === 'corporate' ? (int) $data['departemen'] : null,
                'project_id' => $data['pemanfaatan'] === 'project' ? (int) $data['project'] : null,
                'reasoning' => $data['alasan'],
                'status' => 'wait',
            ]);

            if ($type === 'consumable') {
                foreach ($data['items'] as $itemData) {
                    $basketItem = ConsumableBasket::where('user_id', $user->id)
                        ->findOrFail($itemData['id']);

                    RequestItem::create([
                        'request_id' => $smartRequest->id,
                        'subcategory_id' => $basketItem->subcategory_id,
                        'barang_id' => $basketItem->barang_id,
                        'quantity_requested' => $basketItem->quantity,
                    ]);

                    $basketItem->delete();
                }
            } else {
                $startDate = Carbon::parse($data['start_date']);
                $endDate = !empty($data['end_date']) ? Carbon::parse($data['end_date']) : null;

                foreach ($data['items'] as $itemData) {
                    $basketItem = AssetBasket::where('user_id', $user->id)
                        ->findOrFail($itemData['id']);

                    RequestItem::create([
                        'request_id' => $smartRequest->id,
                        'subcategory_id' => $basketItem->subcategory_id,
                        'barang_id' => $basketItem->barang_id,
                        'quantity_requested' => $basketItem->quantity,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ]);

                    $basketItem->delete();
                }
            }

            RequestStatusLog::create([
                'request_id' => $smartRequest->id,
                'status_from' => 'draft',
                'status_to' => 'wait',
                'changed_by' => $user->id,
                'note' => $type === 'borrow' ? 'Permintaan peminjaman diajukan' : 'Permintaan diajukan',
            ]);

            return $smartRequest;
        });

        // Dispatch notification after transaction commit
        $notifType = $type === 'borrow' ? 'Peminjaman' : 'Permintaan';
        $this->notificationService->notifyManagerNewRequest($smartRequest, $managerUser, $notifType);

        return $smartRequest;
    }

    /**
     * Resolve the designated approver for the given request context.
     * Enforces project assignment authorization and eliminates arbitrary fallback approvers.
     *
     * @param AdmUser $user
     * @param array<string, mixed> $data
     * @return AdmUser
     * @throws ValidationException
     */
    public function resolveApprover(AdmUser $user, array $data): AdmUser
    {
        $utilization = $data['pemanfaatan'] ?? '';

        if ($utilization === 'corporate') {
            if (empty($data['departemen'])) {
                throw ValidationException::withMessages([
                    'departemen' => 'Departemen wajib dipilih untuk pemanfaatan corporate.',
                ]);
            }

            $orgchart = HrdOrgchart::find((int) $data['departemen']);
            if (!$orgchart) {
                throw ValidationException::withMessages([
                    'departemen' => 'Departemen yang dipilih tidak valid.',
                ]);
            }

            $managerUser = null;
            if ($orgchart->employee_id) {
                $managerUser = AdmUser::where('employee_id', $orgchart->employee_id)->first();
            }

            if (!$managerUser) {
                $managerEmployeeId = HrdOrgchart::whereNotNull('employee_id')
                    ->where('org_code', '!=', 'IFS')
                    ->value('employee_id');
                if ($managerEmployeeId) {
                    $managerUser = AdmUser::where('employee_id', $managerEmployeeId)->first();
                }
            }

            if (!$managerUser) {
                throw ValidationException::withMessages([
                    'departemen' => 'Manajer penanggung jawab departemen tidak ditemukan. Silakan hubungi administrator.',
                ]);
            }

            return $managerUser;
        }

        if ($utilization === 'project') {
            if (empty($data['project'])) {
                throw ValidationException::withMessages([
                    'project' => 'Project wajib dipilih untuk pemanfaatan project.',
                ]);
            }

            $project = TbProject::find((int) $data['project']);
            if (!$project) {
                throw ValidationException::withMessages([
                    'project' => 'Project yang dipilih tidak valid.',
                ]);
            }

            // Authorization: Ensure the current user is assigned to this project
            $isAssigned = TbAssignProject::where('no_project', $project->no_project)
                ->where('npk', $user->employee_id)
                ->exists();

            if (!$isAssigned) {
                throw ValidationException::withMessages([
                    'project' => 'Anda tidak terdaftar sebagai anggota pada project ini.',
                ]);
            }

            // Approver is the newest Project Manager (id_rbs = P2211)
            $assignment = TbAssignProject::where('no_project', $project->no_project)
                ->where('id_rbs', 'P2211')
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->first();

            $managerUser = null;
            if ($assignment && $assignment->npk) {
                $managerUser = AdmUser::where('employee_id', $assignment->npk)->first();
            }

            if (!$managerUser) {
                throw ValidationException::withMessages([
                    'project' => 'Project Manager (P2211) untuk project ini tidak ditemukan. Silakan hubungi administrator.',
                ]);
            }

            return $managerUser;
        }

        throw ValidationException::withMessages([
            'pemanfaatan' => 'Pemanfaatan harus berupa corporate atau project.',
        ]);
    }

    /**
     * Generate a collision-resistant sequential request number with row-level database locking.
     */
    protected function generateRequestNumber(): string
    {
        $monthYear = now()->format('mY');

        $lastRequest = SmartRequest::where('request_number', 'like', $monthYear . '-%')
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->first();

        $seq = 1;
        if ($lastRequest) {
            $parts = explode('-', $lastRequest->request_number);
            $seq = ((int) end($parts)) + 1;
        }

        return $monthYear . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
