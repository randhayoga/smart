<?php

namespace Database\Seeders;

use App\Models\Inventory\InventoryLog;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestAdminConfirmation;
use App\Models\Request\RequestFulfillment;
use App\Models\Request\RequestHandover;
use App\Models\Request\RequestItem;
use App\Models\Request\RequestStatusLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DummyConsumableMovementLogSeeder extends Seeder
{
    public const DUMMY_MOVEMENT_REQ_NUMBERS = [
        '122025-0001',
        '012026-0001',
        '012026-0002',
        '022026-0001',
        '022026-0002',
        '022026-0003',
        '032026-0001',
        '042026-0001',
        '042026-0002',
        '052026-0001',
        '052026-0002',
        '062026-0001',
        '062026-0002',
        '072026-0001',
        '072026-0002',
        '082026-0001',
        '082026-0002',
        '092026-0001',
        '092026-0002',
        '102026-0005',
        '102026-0006',
        '112026-0005',
        '112026-0006',
    ];

    /**
     * Run the consumable movement logs database seeds.
     */
    public function run(): void
    {
        // 0. Ensure prepped showcase proof images are stored in authorized media storage
        $dummyFormSource = database_path('seeders/requests/dummy_form.jpg');
        $dummyHandoverSource = database_path('seeders/requests/dummy_handover.jpg');

        if (file_exists($dummyFormSource)) {
            Storage::disk('local')->put('request_forms/dummy_form.jpg', file_get_contents($dummyFormSource));
        }
        if (file_exists($dummyHandoverSource)) {
            Storage::disk('local')->put('handovers/dummy_handover.jpg', file_get_contents($dummyHandoverSource));
        }

        // 1. Identify Users
        $corpUser = User::where('employee_id', '265656')->first();
        $projUser = User::where('employee_id', '184697')->first();
        $adminUser = User::where('employee_id', '255578')->first() ?? User::where('employee_id', '265656')->first() ?? User::first();

        $ifsDm = User::where('employee_id', '033340')->first() ?? User::find(5092) ?? $adminUser;
        $projPm = User::where('employee_id', '023061')->first() ?? User::find(5056) ?? $adminUser;

        $hvsModel = InventoryModel::where('number', 'ATK-HVS4-0001')->first();
        $penModel1 = InventoryModel::where('number', 'ATK-PLPH-0001')->first();
        $penModel2 = InventoryModel::where('number', 'ATK-PLPH-0002')->first();

        if (!$hvsModel || !$penModel1 || !$corpUser || !$projUser) {
            return;
        }

        $hvsLotCorp = Lot::where('number', 'LOT-0001-26-ATK-HVS4-0001')->first() ?? Lot::where('model_id', $hvsModel->id)->first();
        $hvsLotProj = Lot::where('number', 'LOT-0002-26-ATK-HVS4-0001')->first();
        $penLot1 = Lot::where('model_id', $penModel1->id)->first();

        $projectId = 11470;
        $ifsOrgId = 93;

        // 2. Safe Idempotent Cleanup of prior movement requests
        $oldRequests = SmartRequest::whereIn('request_number', self::DUMMY_MOVEMENT_REQ_NUMBERS)->get();
        foreach ($oldRequests as $oldReq) {
            $fulfillments = RequestFulfillment::whereHas('requestItem', fn ($q) => $q->where('request_id', $oldReq->id))->get();
            InventoryLog::where('request_id', $oldReq->id)->delete();
            RequestFulfillment::whereIn('id', $fulfillments->pluck('id'))->delete();
            RequestHandover::where('request_id', $oldReq->id)->delete();
            RequestAdminConfirmation::where('request_id', $oldReq->id)->delete();
            RequestStatusLog::where('request_id', $oldReq->id)->delete();
            RequestItem::where('request_id', $oldReq->id)->delete();
            $oldReq->delete();
        }

        // Safe idempotent cleanup: remove unlinked dummy movement logs for these models
        InventoryLog::whereNull('request_id')
            ->whereIn('model_id', array_filter([$hvsModel->id, $penModel1->id, $penModel2?->id]))
            ->whereIn('action_type', ['stock_in', 'stock_out'])
            ->delete();

        // 3. Seed Incoming Stock Records (Stock In)
        $inboundMovements = [
            // Late 2025 initial stock (Moved to December 2025)
            [
                'model_id' => $hvsModel->id,
                'lot_id' => $hvsLotCorp?->id,
                'user_id' => $adminUser->id,
                'action_type' => 'stock_in',
                'quantity_change' => 80,
                'note' => 'Penerimaan LOT awal kertas HVS A4',
                'created_at' => Carbon::create(2025, 12, 1, 9, 30, 0),
            ],
            // January 2026
            [
                'model_id' => $hvsModel->id,
                'lot_id' => $hvsLotCorp?->id,
                'user_id' => $adminUser->id,
                'action_type' => 'stock_in',
                'quantity_change' => 100,
                'note' => 'Penerimaan LOT baru PO-ATK-2026-01',
                'created_at' => Carbon::create(2026, 1, 8, 10, 0, 0),
            ],
            [
                'model_id' => $penModel1->id,
                'lot_id' => $penLot1?->id,
                'user_id' => $adminUser->id,
                'action_type' => 'stock_in',
                'quantity_change' => 60,
                'note' => 'Penerimaan LOT Snowman V5',
                'created_at' => Carbon::create(2026, 1, 12, 9, 0, 0),
            ],
            // March 2026
            [
                'model_id' => $hvsModel->id,
                'lot_id' => $hvsLotCorp?->id,
                'user_id' => $adminUser->id,
                'action_type' => 'stock_in',
                'quantity_change' => 120,
                'note' => 'Restock kertas HVS triwulan I',
                'created_at' => Carbon::create(2026, 3, 5, 8, 30, 0),
            ],
            [
                'model_id' => $penModel1->id,
                'lot_id' => $penLot1?->id,
                'user_id' => $adminUser->id,
                'action_type' => 'stock_in',
                'quantity_change' => 50,
                'note' => 'Penerimaan restock pulpen',
                'created_at' => Carbon::create(2026, 3, 10, 11, 0, 0),
            ],
            // April 2026
            [
                'model_id' => $hvsModel->id,
                'lot_id' => $hvsLotCorp?->id,
                'user_id' => $adminUser->id,
                'action_type' => 'stock_in',
                'quantity_change' => 90,
                'note' => 'Penerimaan LOT restock kertas HVS April',
                'created_at' => Carbon::create(2026, 4, 2, 8, 30, 0),
            ],
            [
                'model_id' => $penModel1->id,
                'lot_id' => $penLot1?->id,
                'user_id' => $adminUser->id,
                'action_type' => 'stock_in',
                'quantity_change' => 45,
                'note' => 'Penerimaan LOT restock pulpen April',
                'created_at' => Carbon::create(2026, 4, 3, 10, 0, 0),
            ],
            // May 2026
            [
                'model_id' => $hvsModel->id,
                'lot_id' => $hvsLotCorp?->id,
                'user_id' => $adminUser->id,
                'action_type' => 'stock_in',
                'quantity_change' => 100,
                'note' => 'Penerimaan LOT restock kertas HVS Mei',
                'created_at' => Carbon::create(2026, 5, 4, 9, 0, 0),
            ],
            [
                'model_id' => $penModel1->id,
                'lot_id' => $penLot1?->id,
                'user_id' => $adminUser->id,
                'action_type' => 'stock_in',
                'quantity_change' => 50,
                'note' => 'Penerimaan LOT restock pulpen Mei',
                'created_at' => Carbon::create(2026, 5, 5, 11, 0, 0),
            ],
            // June 2026
            [
                'model_id' => $hvsModel->id,
                'lot_id' => $hvsLotProj?->id ?? $hvsLotCorp?->id,
                'user_id' => $adminUser->id,
                'action_type' => 'stock_in',
                'quantity_change' => 80,
                'note' => 'Penerimaan LOT Proyek Kertas HVS Juni',
                'created_at' => Carbon::create(2026, 6, 2, 8, 30, 0),
            ],
            [
                'model_id' => $penModel1->id,
                'lot_id' => $penLot1?->id,
                'user_id' => $adminUser->id,
                'action_type' => 'stock_in',
                'quantity_change' => 40,
                'note' => 'Penerimaan LOT Snowman Juni',
                'created_at' => Carbon::create(2026, 6, 4, 10, 30, 0),
            ],
            // July 2026
            [
                'model_id' => $hvsModel->id,
                'lot_id' => $hvsLotCorp?->id,
                'user_id' => $adminUser->id,
                'action_type' => 'stock_in',
                'quantity_change' => 110,
                'note' => 'Penerimaan LOT Kertas HVS Triwulan III',
                'created_at' => Carbon::create(2026, 7, 6, 9, 0, 0),
            ],
            [
                'model_id' => $penModel1->id,
                'lot_id' => $penLot1?->id,
                'user_id' => $adminUser->id,
                'action_type' => 'stock_in',
                'quantity_change' => 60,
                'note' => 'Penerimaan LOT Pulpen Triwulan III',
                'created_at' => Carbon::create(2026, 7, 8, 14, 0, 0),
            ],
            // August 2026
            [
                'model_id' => $hvsModel->id,
                'lot_id' => $hvsLotCorp?->id,
                'user_id' => $adminUser->id,
                'action_type' => 'stock_in',
                'quantity_change' => 95,
                'note' => 'Penerimaan LOT restock kertas HVS Agustus',
                'created_at' => Carbon::create(2026, 8, 3, 8, 45, 0),
            ],
            [
                'model_id' => $penModel1->id,
                'lot_id' => $penLot1?->id,
                'user_id' => $adminUser->id,
                'action_type' => 'stock_in',
                'quantity_change' => 50,
                'note' => 'Penerimaan LOT restock pulpen Agustus',
                'created_at' => Carbon::create(2026, 8, 5, 11, 0, 0),
            ],
            // September 2026
            [
                'model_id' => $hvsModel->id,
                'lot_id' => $hvsLotProj?->id ?? $hvsLotCorp?->id,
                'user_id' => $adminUser->id,
                'action_type' => 'stock_in',
                'quantity_change' => 70,
                'note' => 'Penerimaan LOT Proyek Kertas HVS September',
                'created_at' => Carbon::create(2026, 9, 2, 9, 15, 0),
            ],
            [
                'model_id' => $penModel1->id,
                'lot_id' => $penLot1?->id,
                'user_id' => $adminUser->id,
                'action_type' => 'stock_in',
                'quantity_change' => 45,
                'note' => 'Penerimaan LOT restock pulpen September',
                'created_at' => Carbon::create(2026, 9, 4, 13, 30, 0),
            ],
            // October 2026
            [
                'model_id' => $hvsModel->id,
                'lot_id' => $hvsLotCorp?->id,
                'user_id' => $adminUser->id,
                'action_type' => 'stock_in',
                'quantity_change' => 80,
                'note' => 'Penerimaan LOT restock kertas HVS Oktober',
                'created_at' => Carbon::create(2026, 10, 5, 9, 0, 0),
            ],
            [
                'model_id' => $penModel1->id,
                'lot_id' => $penLot1?->id,
                'user_id' => $adminUser->id,
                'action_type' => 'stock_in',
                'quantity_change' => 40,
                'note' => 'Penerimaan LOT Snowman V5 PO-ATK-2026-10',
                'created_at' => Carbon::create(2026, 10, 12, 10, 0, 0),
            ],
            // November 2026
            [
                'model_id' => $hvsModel->id,
                'lot_id' => $hvsLotCorp?->id,
                'user_id' => $adminUser->id,
                'action_type' => 'stock_in',
                'quantity_change' => 100,
                'note' => 'Penerimaan LOT PO-ATK-2026-11 Kertas HVS A4 (Corporate)',
                'created_at' => Carbon::create(2026, 11, 1, 8, 30, 0),
            ],
            [
                'model_id' => $penModel1->id,
                'lot_id' => $penLot1?->id,
                'user_id' => $adminUser->id,
                'action_type' => 'stock_in',
                'quantity_change' => 50,
                'note' => 'Penerimaan LOT PO-ATK-2026-11 Pulpen Snowman',
                'created_at' => Carbon::create(2026, 11, 2, 11, 0, 0),
            ],
            [
                'model_id' => $hvsModel->id,
                'lot_id' => $hvsLotProj?->id ?? $hvsLotCorp?->id,
                'user_id' => $adminUser->id,
                'action_type' => 'stock_in',
                'quantity_change' => 50,
                'note' => 'Penerimaan LOT Proyek 25-1323033 Kertas HVS A4',
                'created_at' => Carbon::create(2026, 11, 3, 10, 0, 0),
            ],
        ];

        if ($penModel2) {
            $penLot2 = Lot::where('model_id', $penModel2->id)->first();
            $inboundMovements[] = [
                'model_id' => $penModel2->id,
                'lot_id' => $penLot2?->id,
                'user_id' => $adminUser->id,
                'action_type' => 'stock_in',
                'quantity_change' => 40,
                'note' => 'Penerimaan LOT Standard AE7',
                'created_at' => Carbon::create(2026, 2, 1, 9, 0, 0),
            ];
        }

        foreach ($inboundMovements as $inMov) {
            InventoryLog::create($inMov);
        }

        // 4. Seed Outgoing Stock as Proper Admin-Driven Manual Requests (Stock Out)
        // 4.1 December 2025: Corporate HVS Request (User 265656)
        $this->createConsumableRequest(
            requestNumber: '122025-0001',
            user: $corpUser,
            approverId: $ifsDm->id,
            utilization: 'corporate',
            orgId: $ifsOrgId,
            projectId: null,
            date: Carbon::create(2025, 12, 10, 14, 0, 0),
            reasoning: 'Permintaan untuk Corporate Divisi Keuangan',
            model: $hvsModel,
            lot: $hvsLotCorp,
            qty: 25,
            adminUserId: $adminUser->id
        );

        // 4.2 January 2026: Corporate HVS Request (User 265656)
        $this->createConsumableRequest(
            requestNumber: '012026-0001',
            user: $corpUser,
            approverId: $ifsDm->id,
            utilization: 'corporate',
            orgId: $ifsOrgId,
            projectId: null,
            date: Carbon::create(2026, 1, 20, 11, 15, 0),
            reasoning: 'Permintaan untuk Corporate HRD',
            model: $hvsModel,
            lot: $hvsLotCorp,
            qty: 40,
            adminUserId: $adminUser->id
        );

        // 4.3 January 2026: Corporate Pen Request (User 265656)
        $this->createConsumableRequest(
            requestNumber: '012026-0002',
            user: $corpUser,
            approverId: $ifsDm->id,
            utilization: 'corporate',
            orgId: $ifsOrgId,
            projectId: null,
            date: Carbon::create(2026, 1, 25, 15, 30, 0),
            reasoning: 'Permintaan pulpen operasional umum',
            model: $penModel1,
            lot: $penLot1,
            qty: 15,
            adminUserId: $adminUser->id
        );

        // 4.4 February 2026: Project HVS Request (User 184697)
        $this->createConsumableRequest(
            requestNumber: '022026-0001',
            user: $projUser,
            approverId: $projPm->id,
            utilization: 'project',
            orgId: null,
            projectId: $projectId,
            date: Carbon::create(2026, 2, 14, 13, 0, 0),
            reasoning: 'Permintaan untuk Project Expansion Dieng',
            model: $hvsModel,
            lot: $hvsLotProj ?? $hvsLotCorp,
            qty: 35,
            adminUserId: $adminUser->id
        );

        // 4.5 February 2026: Corporate Pen Request (User 265656)
        $this->createConsumableRequest(
            requestNumber: '022026-0002',
            user: $corpUser,
            approverId: $ifsDm->id,
            utilization: 'corporate',
            orgId: $ifsOrgId,
            projectId: null,
            date: Carbon::create(2026, 2, 18, 10, 45, 0),
            reasoning: 'Permintaan ATK Front Office',
            model: $penModel1,
            lot: $penLot1,
            qty: 20,
            adminUserId: $adminUser->id
        );

        // 4.6 February 2026: Pen 2 Request if model exists
        if ($penModel2) {
            $penLot2 = Lot::where('model_id', $penModel2->id)->first();
            if ($penLot2) {
                $this->createConsumableRequest(
                    requestNumber: '022026-0003',
                    user: $corpUser,
                    approverId: $ifsDm->id,
                    utilization: 'corporate',
                    orgId: $ifsOrgId,
                    projectId: null,
                    date: Carbon::create(2026, 2, 22, 16, 0, 0),
                    reasoning: 'Permintaan ATK Admin IFS',
                    model: $penModel2,
                    lot: $penLot2,
                    qty: 10,
                    adminUserId: $adminUser->id
                );
            }
        }

        // 4.7 March 2026: Corporate HVS Request (User 265656)
        $this->createConsumableRequest(
            requestNumber: '032026-0001',
            user: $corpUser,
            approverId: $ifsDm->id,
            utilization: 'corporate',
            orgId: $ifsOrgId,
            projectId: null,
            date: Carbon::create(2026, 3, 15, 14, 20, 0),
            reasoning: 'Permintaan divisi Audit & Legal',
            model: $hvsModel,
            lot: $hvsLotCorp,
            qty: 50,
            adminUserId: $adminUser->id
        );

        // 4.8 April 2026: Corporate HVS Request (User 265656, Finance Dept)
        $this->createConsumableRequest(
            requestNumber: '042026-0001',
            user: $corpUser,
            approverId: $ifsDm->id,
            utilization: 'corporate',
            orgId: 91,
            projectId: null,
            date: Carbon::create(2026, 4, 15, 10, 30, 0),
            reasoning: 'Permintaan kertas HVS penutupan audit keuangan kuartal I',
            model: $hvsModel,
            lot: $hvsLotCorp,
            qty: 35,
            adminUserId: $adminUser->id
        );

        // 4.9 April 2026: Corporate Pen Request (User 265656, IFS Dept)
        $this->createConsumableRequest(
            requestNumber: '042026-0002',
            user: $corpUser,
            approverId: $ifsDm->id,
            utilization: 'corporate',
            orgId: $ifsOrgId,
            projectId: null,
            date: Carbon::create(2026, 4, 20, 14, 0, 0),
            reasoning: 'Permintaan pulpen operasional IFS',
            model: $penModel1,
            lot: $penLot1,
            qty: 15,
            adminUserId: $adminUser->id
        );

        // 4.10 May 2026: Corporate HVS Request (User 265656, HRD Dept)
        $this->createConsumableRequest(
            requestNumber: '052026-0001',
            user: $corpUser,
            approverId: $ifsDm->id,
            utilization: 'corporate',
            orgId: 92,
            projectId: null,
            date: Carbon::create(2026, 5, 12, 11, 0, 0),
            reasoning: 'Permintaan kertas HVS untuk berkas rekrutmen tahunan',
            model: $hvsModel,
            lot: $hvsLotCorp,
            qty: 30,
            adminUserId: $adminUser->id
        );

        // 4.11 May 2026: Project Pen Request (User 184697, Dieng Project)
        $this->createConsumableRequest(
            requestNumber: '052026-0002',
            user: $projUser,
            approverId: $projPm->id,
            utilization: 'project',
            orgId: null,
            projectId: $projectId,
            date: Carbon::create(2026, 5, 18, 15, 30, 0),
            reasoning: 'Permintaan pulpen tim engineering lapangan Dieng',
            model: $penModel1,
            lot: $penLot1,
            qty: 20,
            adminUserId: $adminUser->id
        );

        // 4.12 June 2026: Project HVS Request (User 184697, Dieng Project)
        $this->createConsumableRequest(
            requestNumber: '062026-0001',
            user: $projUser,
            approverId: $projPm->id,
            utilization: 'project',
            orgId: null,
            projectId: $projectId,
            date: Carbon::create(2026, 6, 10, 13, 0, 0),
            reasoning: 'Permintaan kertas HVS cetak blueprint EPC Dieng',
            model: $hvsModel,
            lot: $hvsLotProj ?? $hvsLotCorp,
            qty: 45,
            adminUserId: $adminUser->id
        );

        // 4.13 June 2026: Corporate Pen Request (User 265656, Finance Dept)
        $this->createConsumableRequest(
            requestNumber: '062026-0002',
            user: $corpUser,
            approverId: $ifsDm->id,
            utilization: 'corporate',
            orgId: 91,
            projectId: null,
            date: Carbon::create(2026, 6, 22, 16, 0, 0),
            reasoning: 'Permintaan pulpen verifikasi invoice semester I',
            model: $penModel1,
            lot: $penLot1,
            qty: 25,
            adminUserId: $adminUser->id
        );

        // 4.14 July 2026: Corporate HVS Request (User 265656, IFS Dept)
        $this->createConsumableRequest(
            requestNumber: '072026-0001',
            user: $corpUser,
            approverId: $ifsDm->id,
            utilization: 'corporate',
            orgId: $ifsOrgId,
            projectId: null,
            date: Carbon::create(2026, 7, 14, 10, 0, 0),
            reasoning: 'Permintaan kertas HVS operasional IFS',
            model: $hvsModel,
            lot: $hvsLotCorp,
            qty: 40,
            adminUserId: $adminUser->id
        );

        // 4.15 July 2026: Project Pen Request (User 184697, Dieng Project)
        $this->createConsumableRequest(
            requestNumber: '072026-0002',
            user: $projUser,
            approverId: $projPm->id,
            utilization: 'project',
            orgId: null,
            projectId: $projectId,
            date: Carbon::create(2026, 7, 25, 14, 45, 0),
            reasoning: 'Permintaan pulpen pengawasan lapangan Dieng',
            model: $penModel1,
            lot: $penLot1,
            qty: 15,
            adminUserId: $adminUser->id
        );

        // 4.16 August 2026: Corporate HVS Request (User 265656, HRD Dept)
        $this->createConsumableRequest(
            requestNumber: '082026-0001',
            user: $corpUser,
            approverId: $ifsDm->id,
            utilization: 'corporate',
            orgId: 92,
            projectId: null,
            date: Carbon::create(2026, 8, 11, 11, 30, 0),
            reasoning: 'Permintaan kertas evaluasi kinerja HRD',
            model: $hvsModel,
            lot: $hvsLotCorp,
            qty: 25,
            adminUserId: $adminUser->id
        );

        // 4.17 August 2026: Corporate Pen Request (User 265656, IFS Dept)
        $this->createConsumableRequest(
            requestNumber: '082026-0002',
            user: $corpUser,
            approverId: $ifsDm->id,
            utilization: 'corporate',
            orgId: $ifsOrgId,
            projectId: null,
            date: Carbon::create(2026, 8, 20, 15, 0, 0),
            reasoning: 'Permintaan pulpen operasional rutin',
            model: $penModel1,
            lot: $penLot1,
            qty: 20,
            adminUserId: $adminUser->id
        );

        // 4.18 September 2026: Project HVS Request (User 184697, Dieng Project)
        $this->createConsumableRequest(
            requestNumber: '092026-0001',
            user: $projUser,
            approverId: $projPm->id,
            utilization: 'project',
            orgId: null,
            projectId: $projectId,
            date: Carbon::create(2026, 9, 15, 10, 15, 0),
            reasoning: 'Permintaan kertas HVS laporan progress proyek Dieng',
            model: $hvsModel,
            lot: $hvsLotProj ?? $hvsLotCorp,
            qty: 35,
            adminUserId: $adminUser->id
        );

        // 4.19 September 2026: Corporate Pen Request (User 265656, Finance Dept)
        $this->createConsumableRequest(
            requestNumber: '092026-0002',
            user: $corpUser,
            approverId: $ifsDm->id,
            utilization: 'corporate',
            orgId: 91,
            projectId: null,
            date: Carbon::create(2026, 9, 24, 14, 0, 0),
            reasoning: 'Permintaan pulpen administrasi perpajakan',
            model: $penModel1,
            lot: $penLot1,
            qty: 15,
            adminUserId: $adminUser->id
        );

        // 4.20 October 2026: Corporate HVS Request (User 265656)
        $this->createConsumableRequest(
            requestNumber: '102026-0005',
            user: $corpUser,
            approverId: $ifsDm->id,
            utilization: 'corporate',
            orgId: $ifsOrgId,
            projectId: null,
            date: Carbon::create(2026, 10, 18, 14, 0, 0),
            reasoning: 'Permintaan kebutuhan kertas evaluasi kuartal III',
            model: $hvsModel,
            lot: $hvsLotCorp,
            qty: 30,
            adminUserId: $adminUser->id
        );

        // 4.21 October 2026: Corporate Pen Request (User 265656)
        $this->createConsumableRequest(
            requestNumber: '102026-0006',
            user: $corpUser,
            approverId: $ifsDm->id,
            utilization: 'corporate',
            orgId: $ifsOrgId,
            projectId: null,
            date: Carbon::create(2026, 10, 22, 11, 30, 0),
            reasoning: 'Permintaan pulpen operasional divisi IFS',
            model: $penModel1,
            lot: $penLot1,
            qty: 15,
            adminUserId: $adminUser->id
        );

        // 4.22 November 2026: Corporate HVS Request (User 265656)
        $this->createConsumableRequest(
            requestNumber: '112026-0005',
            user: $corpUser,
            approverId: $ifsDm->id,
            utilization: 'corporate',
            orgId: $ifsOrgId,
            projectId: null,
            date: Carbon::create(2026, 11, 4, 15, 0, 0),
            reasoning: 'Permintaan ATK operasional administrasi',
            model: $hvsModel,
            lot: $hvsLotCorp,
            qty: 20,
            adminUserId: $adminUser->id
        );

        // 4.23 November 2026: Project Pen Request (User 184697)
        $this->createConsumableRequest(
            requestNumber: '112026-0006',
            user: $projUser,
            approverId: $projPm->id,
            utilization: 'project',
            orgId: null,
            projectId: $projectId,
            date: Carbon::create(2026, 11, 8, 14, 30, 0),
            reasoning: 'Permintaan pulpen untuk rapat koordinasi',
            model: $penModel1,
            lot: $penLot1,
            qty: 15,
            adminUserId: $adminUser->id
        );
    }

    private function createConsumableRequest(
        string $requestNumber,
        User $user,
        int $approverId,
        string $utilization,
        ?int $orgId,
        ?int $projectId,
        Carbon $date,
        string $reasoning,
        InventoryModel $model,
        Lot $lot,
        int $qty,
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
        app(\App\Services\InventoryLogService::class)->logConsumableStockOut(
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
