<?php

namespace Database\Seeders;

use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetLifecycle;
use App\Models\Inventory\InventoryLog;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestAdminConfirmation;
use App\Models\Request\RequestFulfillment;
use App\Models\Request\RequestHandover;
use App\Models\Request\RequestItem;
use App\Models\Request\RequestReturn;
use App\Models\Request\RequestStatusLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Phase 1 Showcase Seeder
 *
 * Seeds pure Phase 1 (Admin-driven) manual requests for showcase:
 * - Corporate User 265656 (IFS Department)
 * - Project User 184697 (Project 25-1323033)
 * Covering active LT assignments, active ST borrowings, returned borrowings,
 * and consumable stock distributions.
 */
class DummyRequestShowcaseSeeder extends Seeder
{
    public const SHOWCASE_REQ_NUMBERS = [
        '102026-0001',
        '102026-0002',
        '102026-0003',
        '102026-0004',
        '112026-0001',
        '112026-0002',
        '112026-0003',
        '112026-0004',
        // Legacy numbers for idempotent cleanup
        'REQ-26-CLT1',
        'REQ-26-CST1',
        'REQ-26-CRET',
        'REQ-26-CCSM',
        'REQ-26-PLT1',
        'REQ-26-PST1',
        'REQ-26-PRET',
        'REQ-26-PCSM',
    ];

    public function run(): void
    {
        // 0. Ensure prepped showcase proof images are stored in authorized media storage
        $dummyFormSource = database_path('seeders/requests/dummy_form.jpg');
        $dummyHandoverSource = database_path('seeders/requests/dummy_handover.jpg');
        $dummyReturnSource = database_path('seeders/requests/dummy_return.jpg');

        if (file_exists($dummyFormSource)) {
            Storage::disk('local')->put('request_forms/dummy_form.jpg', file_get_contents($dummyFormSource));
        }
        if (file_exists($dummyHandoverSource)) {
            Storage::disk('local')->put('handovers/dummy_handover.jpg', file_get_contents($dummyHandoverSource));
        }
        if (file_exists($dummyReturnSource)) {
            Storage::disk('local')->put('returns/dummy_return.jpg', file_get_contents($dummyReturnSource));
        }
        // 1. Identify Users
        $corpUser = User::where('employee_id', '265656')->first();
        $projUser = User::where('employee_id', '184697')->first();
        $adminUser = User::where('employee_id', '255578')->first() ?? User::where('employee_id', '265656')->first() ?? User::first();

        // Corporate Approver reference: Sonny Handini (033340 / User #5092, manager of IFS)
        $ifsDm = User::where('employee_id', '033340')->first() ?? User::find(5092) ?? $adminUser;

        // Project Approver reference: Gede Satoto Ir. (023061 / User #5056, PM of 25-1323033)
        $projPm = User::where('employee_id', '023061')->first() ?? User::find(5056) ?? $adminUser;

        if (!$corpUser || !$projUser) {
            $this->command?->warn('DummyRequestShowcaseSeeder: Required users (265656 or 184697) not found.');
            return;
        }

        // Project 25-1323033 (ID 11470)
        $projectId = 11470;
        $ifsOrgId = 93;

        // 2. Safe Idempotent Cleanup: Purge previous showcase records and restore assets
        $oldRequests = SmartRequest::whereIn('request_number', self::SHOWCASE_REQ_NUMBERS)->get();
        foreach ($oldRequests as $oldReq) {
            $fulfillments = RequestFulfillment::whereHas('requestItem', fn ($q) => $q->where('request_id', $oldReq->id))->get();
            $assetIds = $fulfillments->pluck('asset_id')->filter()->unique();

            if ($assetIds->isNotEmpty()) {
                Asset::whereIn('id', $assetIds)->update(['status' => 'Tersedia']);
                AssetLifecycle::whereIn('asset_id', $assetIds)->delete();
            }

            InventoryLog::where('request_id', $oldReq->id)->delete();
            RequestFulfillment::whereIn('id', $fulfillments->pluck('id'))->delete();
            RequestReturn::where('request_id', $oldReq->id)->delete();
            RequestHandover::where('request_id', $oldReq->id)->delete();
            RequestAdminConfirmation::where('request_id', $oldReq->id)->delete();
            RequestStatusLog::where('request_id', $oldReq->id)->delete();
            RequestItem::where('request_id', $oldReq->id)->delete();
            $oldReq->delete();
        }

        // 3. Locate available Non-Consumable Assets for Assignment & Borrowing
        $ltAssets = Asset::where('borrowing_type', 'LT')
            ->where('status', 'Tersedia')
            ->whereNull('approval_status')
            ->take(2)
            ->get();

        $stAssets = Asset::where('borrowing_type', 'ST')
            ->where('status', 'Tersedia')
            ->whereNull('approval_status')
            ->take(4)
            ->get();

        if ($stAssets->count() < 4) {
            $needed = 4 - $stAssets->count();
            $additional = Asset::where('borrowing_type', 'LT')
                ->where('status', 'Tersedia')
                ->whereNull('approval_status')
                ->take($needed)
                ->get();
            foreach ($additional as $add) {
                $add->update(['borrowing_type' => 'ST']);
            }
            $stAssets = Asset::where('borrowing_type', 'ST')
                ->where('status', 'Tersedia')
                ->whereNull('approval_status')
                ->take(4)
                ->get();
        }

        if ($ltAssets->count() < 2 || $stAssets->count() < 4) {
            $this->command?->warn('DummyRequestShowcaseSeeder: Not enough available non-consumable assets. Skipping asset requests.');
            return;
        }

        $corpLtAsset = $ltAssets[0];
        $projLtAsset = $ltAssets[1];
        $corpStAsset = $stAssets[0];
        $corpRetAsset = $stAssets[1];
        $projStAsset = $stAssets[2];
        $projRetAsset = $stAssets[3];

        $now = Carbon::create(2026, 11, 10, 10, 0, 0);

        // -------------------------------------------------------------
        // SCENARIO 1: Corporate Active LT Assignment (User 265656)
        // -------------------------------------------------------------
        $this->createPhase1AssetRequest(
            requestNumber: '102026-0001',
            requestType: 'lt',
            user: $corpUser,
            approverId: $ifsDm->id,
            utilization: 'corporate',
            orgId: $ifsOrgId,
            projectId: null,
            asset: $corpLtAsset,
            startDate: Carbon::create(2026, 10, 1, 9, 0, 0),
            endDate: null,
            reasoning: 'Penugasan inventaris kerja jangka panjang divisi IFS',
            adminUserId: $adminUser->id
        );

        // -------------------------------------------------------------
        // SCENARIO 2: Project Returned ST Borrowing (User 184697)
        // -------------------------------------------------------------
        $this->createPhase1ReturnedAssetRequest(
            requestNumber: '102026-0002',
            requestType: 'st',
            user: $projUser,
            approverId: $projPm->id,
            utilization: 'project',
            orgId: null,
            projectId: $projectId,
            asset: $projRetAsset,
            startDate: Carbon::create(2026, 10, 5, 8, 0, 0),
            endDate: Carbon::create(2026, 10, 15, 17, 0, 0),
            returnDate: Carbon::create(2026, 10, 15, 15, 30, 0),
            reasoning: 'Peminjaman instrumen ukur geodetik site survey',
            adminUserId: $adminUser->id
        );

        // -------------------------------------------------------------
        // SCENARIO 3: Corporate Returned ST Borrowing (User 265656)
        // -------------------------------------------------------------
        $this->createPhase1ReturnedAssetRequest(
            requestNumber: '102026-0003',
            requestType: 'st',
            user: $corpUser,
            approverId: $ifsDm->id,
            utilization: 'corporate',
            orgId: $ifsOrgId,
            projectId: null,
            asset: $corpRetAsset,
            startDate: Carbon::create(2026, 10, 10, 9, 0, 0),
            endDate: Carbon::create(2026, 10, 18, 17, 0, 0),
            returnDate: Carbon::create(2026, 10, 18, 16, 0, 0),
            reasoning: 'Peminjaman kamera dokumentasi fasilitas',
            adminUserId: $adminUser->id
        );

        // -------------------------------------------------------------
        // SCENARIO 4: Project Active LT Assignment (User 184697)
        // -------------------------------------------------------------
        $this->createPhase1AssetRequest(
            requestNumber: '102026-0004',
            requestType: 'lt',
            user: $projUser,
            approverId: $projPm->id,
            utilization: 'project',
            orgId: null,
            projectId: $projectId,
            asset: $projLtAsset,
            startDate: Carbon::create(2026, 10, 15, 8, 30, 0),
            endDate: null,
            reasoning: 'Penugasan workstation mobile untuk paket EPC Dieng Unit 2',
            adminUserId: $adminUser->id
        );

        // -------------------------------------------------------------
        // SCENARIO 5: Corporate Consumable Request (User 265656)
        // -------------------------------------------------------------
        $hvsModel = InventoryModel::where('number', 'ATK-HVS4-0001')->first();
        $penModel = InventoryModel::where('number', 'ATK-PLPH-0001')->first();

        if ($hvsModel && $penModel) {
            $hvsLot = Lot::where('model_id', $hvsModel->id)->first();
            $penLot = Lot::where('model_id', $penModel->id)->first();

            if ($hvsLot && $penLot) {
                // Corporate Consumable Request (Nov 3, 2026)
                $this->createPhase1ConsumableRequest(
                    requestNumber: '112026-0001',
                    user: $corpUser,
                    approverId: $ifsDm->id,
                    utilization: 'corporate',
                    orgId: $ifsOrgId,
                    projectId: null,
                    date: Carbon::create(2026, 11, 3, 14, 0, 0),
                    reasoning: 'Permintaan ATK bulanan operasional IFS',
                    items: [
                        ['model' => $hvsModel, 'lot' => $hvsLot, 'qty' => 25],
                        ['model' => $penModel, 'lot' => $penLot, 'qty' => 10],
                    ],
                    adminUserId: $adminUser->id
                );

                // Project Active ST Borrowing (Nov 5, 2026)
                $this->createPhase1AssetRequest(
                    requestNumber: '112026-0002',
                    requestType: 'st',
                    user: $projUser,
                    approverId: $projPm->id,
                    utilization: 'project',
                    orgId: null,
                    projectId: $projectId,
                    asset: $projStAsset,
                    startDate: Carbon::create(2026, 11, 5, 9, 0, 0),
                    endDate: Carbon::create(2026, 11, 25, 17, 0, 0),
                    reasoning: 'Peminjaman alat uji jaringan lapangan project Dieng',
                    adminUserId: $adminUser->id
                );

                // Project Consumable Request (Nov 6, 2026)
                $this->createPhase1ConsumableRequest(
                    requestNumber: '112026-0003',
                    user: $projUser,
                    approverId: $projPm->id,
                    utilization: 'project',
                    orgId: null,
                    projectId: $projectId,
                    date: Carbon::create(2026, 11, 6, 10, 30, 0),
                    reasoning: 'Kebutuhan ATK administrasi tim EPC Dieng',
                    items: [
                        ['model' => $hvsModel, 'lot' => $hvsLot, 'qty' => 15],
                        ['model' => $penModel, 'lot' => $penLot, 'qty' => 10],
                    ],
                    adminUserId: $adminUser->id
                );

                // Corporate Active ST Borrowing (Nov 8, 2026)
                $this->createPhase1AssetRequest(
                    requestNumber: '112026-0004',
                    requestType: 'st',
                    user: $corpUser,
                    approverId: $ifsDm->id,
                    utilization: 'corporate',
                    orgId: $ifsOrgId,
                    projectId: null,
                    asset: $corpStAsset,
                    startDate: Carbon::create(2026, 11, 8, 10, 0, 0),
                    endDate: Carbon::create(2026, 11, 20, 18, 0, 0),
                    reasoning: 'Peminjaman operasional untuk koordinasi rapat mingguan',
                    adminUserId: $adminUser->id
                );
            }
        }
    }

    private function createPhase1AssetRequest(
        string $requestNumber,
        string $requestType,
        User $user,
        int $approverId,
        string $utilization,
        ?int $orgId,
        ?int $projectId,
        Asset $asset,
        Carbon $startDate,
        ?Carbon $endDate,
        string $reasoning,
        int $adminUserId
    ): SmartRequest {
        $newAssetStatus = match ($requestType) {
            'rent' => 'Dipinjam (sewa)',
            'st' => 'Dipinjam (ST)',
            default => 'Di-assign (LT)',
        };
        $lifecycleAction = in_array($requestType, ['st', 'rent'], true) ? 'Peminjaman' : 'Assignment';
        $actionTypeLabel = match ($requestType) {
            'st' => 'Peminjaman (ST)',
            'rent' => 'Peminjaman (Sewa)',
            default => 'Assignment (LT)',
        };

        $req = SmartRequest::create([
            'request_number' => $requestNumber,
            'user_id' => $user->id,
            'approver_id' => $approverId,
            'utilization' => $utilization,
            'org_id' => $orgId,
            'project_id' => $projectId,
            'reasoning' => $reasoning,
            'form_url' => 'request_forms/dummy_form.jpg',
            'status' => 'borrow',
            'created_at' => $startDate,
            'updated_at' => $startDate,
        ]);

        $handover = RequestHandover::create([
            'request_id' => $req->id,
            'method' => 'pickup',
            'scheduled_date' => $startDate,
            'location' => 'Gedung Grha Rekayasa Lt. 1',
            'proof_url' => 'handovers/dummy_handover.jpg',
            'user_confirmed_at' => $startDate,
            'auto_confirmed' => true,
        ]);

        $model = $asset->lot?->model;
        $item = RequestItem::create([
            'request_id' => $req->id,
            'type_id' => $model?->type_id,
            'model_id' => $model?->id,
            'quantity_requested' => 1,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reasoning' => $reasoning,
            'status' => 'fulfilled',
        ]);

        $fulfillment = RequestFulfillment::create([
            'request_item_id' => $item->id,
            'asset_id' => $asset->id,
            'lot_id' => null,
            'handover_id' => $handover->id,
            'quantity_fulfilled' => 1,
            'assigned_at' => $startDate,
        ]);

        $catatanPart = !empty($reasoning) ? ' Catatan: ' . rtrim($reasoning, '.') . '.' : '';
        $lifecycleNote = "{$actionTypeLabel} {$requestNumber} untuk {$user->name} ({$user->employee_id}).{$catatanPart}";

        AssetLifecycle::create([
            'asset_id' => $asset->id,
            'request_id' => $req->id,
            'fulfillment_id' => $fulfillment->id,
            'action_type' => $lifecycleAction,
            'status' => $newAssetStatus,
            'condition' => $asset->condition ?? 'Bagus',
            'location_id' => $asset->location_id,
            'start_date' => $startDate,
            'end_date' => null,
            'actor_id' => $user->id,
            'note' => $lifecycleNote,
            'previous_state' => ['status' => 'Tersedia'],
            'new_state' => [
                'status' => $newAssetStatus,
                'borrower_id' => $user->id,
                'request_number' => $requestNumber,
            ],
        ]);

        $asset->update(['status' => $newAssetStatus]);

        RequestAdminConfirmation::create([
            'request_id' => $req->id,
            'admin_id' => $adminUserId,
            'action' => 'confirm',
            'note' => "Permintaan {$requestNumber} disetujui dan diserahkan secara manual oleh Admin",
            'decided_at' => $startDate,
        ]);

        RequestStatusLog::create([
            'request_id' => $req->id,
            'status_from' => 'draft',
            'status_to' => 'borrow',
            'changed_by' => $adminUserId,
            'note' => "Permintaan {$requestNumber} dikonfirmasi oleh Admin",
            'created_at' => $startDate,
        ]);

        return $req;
    }

    private function createPhase1ReturnedAssetRequest(
        string $requestNumber,
        string $requestType,
        User $user,
        int $approverId,
        string $utilization,
        ?int $orgId,
        ?int $projectId,
        Asset $asset,
        Carbon $startDate,
        Carbon $endDate,
        Carbon $returnDate,
        string $reasoning,
        int $adminUserId
    ): SmartRequest {
        $req = $this->createPhase1AssetRequest(
            requestNumber: $requestNumber,
            requestType: $requestType,
            user: $user,
            approverId: $approverId,
            utilization: $utilization,
            orgId: $orgId,
            projectId: $projectId,
            asset: $asset,
            startDate: $startDate,
            endDate: $endDate,
            reasoning: $reasoning,
            adminUserId: $adminUserId
        );

        $requestReturn = RequestReturn::create([
            'request_id' => $req->id,
            'method' => 'manual',
            'scheduled_date' => null,
            'location' => 'Gedung Grha Rekayasa Lt. 1',
            'proof_url' => 'returns/dummy_return.jpg',
            'is_auto_set' => false,
            'completed_at' => $returnDate,
        ]);

        $itemIds = $req->items()->pluck('id');
        RequestFulfillment::whereIn('request_item_id', $itemIds)->update(['return_id' => $requestReturn->id]);

        AssetLifecycle::where('asset_id', $asset->id)
            ->whereNull('end_date')
            ->update(['end_date' => $returnDate]);

        $actionLabel = $requestType === 'lt' ? 'assignment' : 'peminjaman';

        AssetLifecycle::create([
            'asset_id' => $asset->id,
            'request_id' => $req->id,
            'fulfillment_id' => RequestFulfillment::whereIn('request_item_id', $itemIds)->value('id'),
            'action_type' => 'Pengembalian',
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'location_id' => $asset->location_id,
            'start_date' => $returnDate,
            'end_date' => null,
            'actor_id' => $adminUserId,
            'note' => "Pengembalian {$actionLabel} {$requestNumber} dari {$user->name} ({$user->employee_id}).",
            'previous_state' => ['status' => 'Dipinjam (ST)'],
            'new_state' => ['status' => 'Tersedia'],
        ]);

        $asset->update(['status' => 'Tersedia']);
        $req->update(['status' => 'success']);

        RequestStatusLog::create([
            'request_id' => $req->id,
            'status_from' => 'borrow',
            'status_to' => 'success',
            'changed_by' => $adminUserId,
            'note' => "Seluruh peminjaman diselesaikan secara manual oleh Admin di Gedung Grha Rekayasa Lt. 1.",
            'created_at' => $returnDate,
        ]);

        return $req;
    }

    private function createPhase1ConsumableRequest(
        string $requestNumber,
        User $user,
        int $approverId,
        string $utilization,
        ?int $orgId,
        ?int $projectId,
        Carbon $date,
        string $reasoning,
        array $items,
        int $adminUserId
    ): SmartRequest {
        $req = SmartRequest::create([
            'request_number' => $requestNumber,
            'user_id' => $user->id,
            'approver_id' => $approverId,
            'utilization' => $utilization,
            'org_id' => $orgId,
            'project_id' => $projectId,
            'reasoning' => $reasoning,
            'form_url' => 'request_forms/dummy_form.jpg',
            'status' => 'success',
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        $handover = RequestHandover::create([
            'request_id' => $req->id,
            'method' => 'pickup',
            'scheduled_date' => $date,
            'location' => 'Ruang Logistik IFS',
            'proof_url' => 'handovers/dummy_handover.jpg',
            'user_confirmed_at' => $date,
            'auto_confirmed' => true,
        ]);

        $destinationName = null;
        if ($utilization === 'corporate' && $orgId !== null) {
            $destinationName = \App\Models\HrdOrgchart::where('id', $orgId)->value('org_name');
        } elseif ($utilization === 'project' && $projectId !== null) {
            $destinationName = \App\Models\TbProject::where('id_project', $projectId)->value('project_name');
        }

        $logService = app(\App\Services\InventoryLogService::class);

        foreach ($items as $it) {
            $model = $it['model'];
            $lot = $it['lot'];
            $qty = (int) $it['qty'];

            $requestItem = RequestItem::create([
                'request_id' => $req->id,
                'type_id' => $model->type_id,
                'model_id' => $model->id,
                'quantity_requested' => $qty,
                'start_date' => null,
                'end_date' => null,
                'reasoning' => $reasoning,
                'status' => 'fulfilled',
            ]);

            $fulfillment = RequestFulfillment::create([
                'request_item_id' => $requestItem->id,
                'asset_id' => null,
                'lot_id' => $lot->id,
                'handover_id' => $handover->id,
                'quantity_fulfilled' => $qty,
                'assigned_at' => $date,
            ]);

            // Deduct lot current_quantity
            $prevQty = (int) $lot->current_quantity;
            $newQty = max(0, $prevQty - $qty);
            $lot->update(['current_quantity' => $newQty]);

            // Log stock_out movement via production InventoryLogService for 100% parity
            $logService->logConsumableStockOut(
                model: $model,
                lot: $lot,
                deductQty: $qty,
                prevQty: $prevQty,
                newQty: $newQty,
                user: $user,
                utilization: $utilization,
                destinationName: $destinationName,
                reasonNote: $reasoning,
                createdAt: $date,
                requestId: $req->id,
                fulfillmentId: $fulfillment->id,
                requestNumber: $requestNumber
            );
        }

        RequestAdminConfirmation::create([
            'request_id' => $req->id,
            'admin_id' => $adminUserId,
            'action' => 'confirm',
            'note' => 'Permintaan consumable dikonfirmasi dan diserahkan oleh Admin',
            'decided_at' => $date,
        ]);

        RequestStatusLog::create([
            'request_id' => $req->id,
            'status_from' => 'draft',
            'status_to' => 'success',
            'changed_by' => $adminUserId,
            'note' => 'Permintaan consumable dikonfirmasi dan diserahkan oleh Admin',
            'created_at' => $date,
        ]);

        return $req;
    }
}
