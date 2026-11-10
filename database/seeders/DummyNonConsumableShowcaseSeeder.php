<?php

namespace Database\Seeders;

use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetActivationApproval;
use App\Models\Inventory\AssetDeletionApproval;
use App\Models\Inventory\AssetLifecycle;
use App\Models\Inventory\InventoryLog;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Master\Brand;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Type;
use App\Models\Master\Uom;
use App\Models\User;
use App\Services\InventoryLogService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DummyNonConsumableShowcaseSeeder extends Seeder
{
    public const SHOWCASE_DGX_MODEL_NUMBER = 'COMP-SVR-0007';
    public const SHOWCASE_DGX_LOT_NUMBER = 'LOT-0001-26-COMP-SVR-0007';
    public const SHOWCASE_CHAIR_LOT_NUMBER = 'LOT-0003-26-FUR-KK-0004';

    public const SHOWCASE_ASSET_NUMBERS = [
        '00001-COMP-SVR-ICT-PTRE-26',
        '00002-COMP-SVR-ICT-PTRE-26',
        '00101-FUR-KK-CFS-PTRE-26',
        '00102-FUR-KK-CFS-PTRE-26',
        '00103-FUR-KK-CFS-PTRE-26',
        '00104-FUR-KK-CFS-PTRE-26',
        '00105-FUR-KK-CFS-PTRE-26',
    ];

    /**
     * Run the database seeds for Non-Consumable Input, Activation, and Deactivation Showcase.
     */
    public function run(): void
    {
        // 0. Deploy showcase media and dummy approval documents
        $this->deployDummyFiles();

        // 1. Identify Actor Users
        $adminUser = User::where('employee_id', '255578')->first()
            ?? User::where('employee_id', '265656')->first()
            ?? User::first();
        $ifsDm = User::where('employee_id', '033340')->first()
            ?? User::find(5092)
            ?? $adminUser;

        $locationId = Location::value('id') ?? Location::firstOrCreate(['name' => 'Lantai Mezzanine'])->id;
        $inventoryLogService = app(InventoryLogService::class);

        // 2. Safe Idempotent Cleanup of prior showcase records
        $this->cleanupPriorRecords();

        // 3. Showcase Input 1: COMP+SRV Nvidia DGX Spark (ICT Organizer, Procurement LOT)
        $this->seedNvidiaDgxSpark($adminUser, $ifsDm, $locationId, $inventoryLogService);

        // 4. Showcase Input 2 & Deactivation: FUR+KK-0004 Kursi Kerja (CFS Organizer, Hibah LOT)
        $this->seedChairHibahAndDeactivations($adminUser, $ifsDm, $locationId, $inventoryLogService);
    }

    /**
     * Copy dummy images and deactivation PDF files to active storage disks.
     */
    private function deployDummyFiles(): void
    {
        // Image files
        $dgxImageSource = database_path('seeders/assets/dummy_dgx.jpg');
        $chairImageSource = database_path('seeders/assets/dummy_chair.webp');

        if (file_exists($dgxImageSource)) {
            $content = file_get_contents($dgxImageSource);
            Storage::disk('public')->put('inventory/lots/dummy_dgx.jpg', $content);
            Storage::disk('public')->put('inventory/models/dummy_dgx.jpg', $content);
        }

        if (file_exists($chairImageSource)) {
            $content = file_get_contents($chairImageSource);
            Storage::disk('public')->put('inventory/lots/dummy_chair.webp', $content);
            Storage::disk('public')->put('inventory/assets/dummy_chair.webp', $content);
        }

        // Deactivation PDF documents
        $memoSource = database_path('seeders/deactivation_files/dummy_memo.pdf');
        $lostSource = database_path('seeders/deactivation_files/dummy_lost.pdf');
        $approveSource = database_path('seeders/deactivation_files/dummy_approve.pdf');
        $rejectSource = database_path('seeders/deactivation_files/dummy_reject.pdf');

        if (file_exists($memoSource)) {
            Storage::disk('local')->put('memos/dummy_memo.pdf', file_get_contents($memoSource));
        }
        if (file_exists($lostSource)) {
            Storage::disk('local')->put('lost_docs/dummy_lost.pdf', file_get_contents($lostSource));
        }
        if (file_exists($approveSource)) {
            Storage::disk('local')->put('bod_boc_approvals/dummy_approve.pdf', file_get_contents($approveSource));
        }
        if (file_exists($rejectSource)) {
            Storage::disk('local')->put('bod_boc_disapprovals/dummy_reject.pdf', file_get_contents($rejectSource));
        }
    }

    /**
     * Idempotent cleanup of previously seeded showcase entities.
     */
    private function cleanupPriorRecords(): void
    {
        $existingAssets = Asset::whereIn('number', self::SHOWCASE_ASSET_NUMBERS)->get();
        foreach ($existingAssets as $asset) {
            AssetLifecycle::where('asset_id', $asset->id)->delete();
            AssetActivationApproval::where('asset_id', $asset->id)->delete();
            AssetDeletionApproval::where('asset_id', $asset->id)->delete();
            $asset->delete();
        }

        $existingLots = Lot::whereIn('number', [
            self::SHOWCASE_DGX_LOT_NUMBER,
            self::SHOWCASE_CHAIR_LOT_NUMBER,
        ])->get();

        foreach ($existingLots as $lot) {
            InventoryLog::where('lot_id', $lot->id)->delete();
            $lot->delete();
        }

        $existingModel = InventoryModel::where('number', self::SHOWCASE_DGX_MODEL_NUMBER)->first();
        if ($existingModel) {
            InventoryLog::where('model_id', $existingModel->id)->delete();
            $existingModel->delete();
        }
    }

    /**
     * Seed Input 1: Nvidia DGX Spark Model, LOT, and Units (with Activation Showcase).
     */
    private function seedNvidiaDgxSpark(User $adminUser, User $ifsDm, int $locationId, InventoryLogService $inventoryLogService): void
    {
        $compCategory = \App\Models\Master\Category::firstOrCreate(['code' => 'COMP'], ['name' => 'Computer']);
        $serverType = Type::firstOrCreate(['code' => 'COMP-SVR'], [
            'name' => 'Server',
            'category_id' => $compCategory->id,
            'is_consumable' => false,
        ]);

        $nvidiaBrand = Brand::firstOrCreate(['name' => 'Nvidia']);
        $unitUom = Uom::firstOrCreate(['name' => 'Unit']);
        $ictOrganizer = Organizer::firstOrCreate(['name' => 'ICT']);

        // 1. Create Model (Computer Category forces specification null on model)
        $dgxModel = InventoryModel::create([
            'number' => self::SHOWCASE_DGX_MODEL_NUMBER,
            'type_id' => $serverType->id,
            'brand_id' => $nvidiaBrand->id,
            'uom_id' => $unitUom->id,
            'name' => 'Nvidia DGX Spark',
            'specification' => null,
        ]);

        $inventoryLogService->logModelCreated($dgxModel, $adminUser, 'Menambahkan model baru: Nvidia DGX Spark');

        // 2. Create Procurement LOT (Non-consumable lots store location on individual assets)
        $purchaseDate = Carbon::create(2026, 2, 15, 10, 0, 0);
        $dgxLot = Lot::create([
            'number' => self::SHOWCASE_DGX_LOT_NUMBER,
            'model_id' => $dgxModel->id,
            'organizer_id' => $ictOrganizer?->id ?? 2,
            'vendor_id' => null,
            'initial_quantity' => 2,
            'current_quantity' => 2,
            'entry_type' => 'Procurement',
            'po_number' => 'PO-ICT-2026-DGX01',
            'purchase_grant_date' => $purchaseDate,
            'location_id' => null,
            'image_url' => 'inventory/lots/dummy_dgx.jpg',
            'burden' => 'Corporate',
            'created_at' => $purchaseDate,
            'updated_at' => $purchaseDate,
        ]);

        $inboundLog = $inventoryLogService->logLotCreated($dgxLot, $adminUser, null, 2);
        $inboundLog->update(['created_at' => $purchaseDate]);

        // 3. Asset 1: Active Unit (Approved Activation)
        $asset1Date = Carbon::create(2026, 2, 16, 9, 0, 0);
        $asset1 = Asset::withoutEvents(fn () => Asset::create([
            'number' => self::SHOWCASE_ASSET_NUMBERS[0],
            'lot_id' => $dgxLot->id,
            'location_id' => $locationId,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'price' => 350000000,
            'comp_specification' => 'AI Supercomputer Server, 8x NVIDIA H100 GPU',
            'image_url' => 'inventory/models/dummy_dgx.jpg',
            'burden' => 'Corporate',
            'created_at' => $asset1Date,
            'updated_at' => $asset1Date,
        ]));

        AssetActivationApproval::create([
            'asset_id' => $asset1->id,
            'requester_id' => $adminUser->id,
            'approver_id' => $ifsDm->id,
            'decision' => 'approved',
            'note' => 'Disetujui untuk operasional AI Research',
            'requested_at' => $asset1Date,
            'decided_at' => $asset1Date->copy()->addHours(4),
            'created_at' => $asset1Date,
            'updated_at' => $asset1Date->copy()->addHours(4),
        ]);

        AssetLifecycle::create([
            'asset_id' => $asset1->id,
            'action_type' => 'Registrasi',
            'status' => 'Belum Diverifikasi',
            'condition' => 'Bagus',
            'location_id' => $locationId,
            'start_date' => $asset1Date,
            'end_date' => $asset1Date->copy()->addHours(4),
            'actor_id' => $adminUser->id,
            'note' => 'Aset berhasil diregistrasi ke dalam sistem.',
        ]);

        AssetLifecycle::create([
            'asset_id' => $asset1->id,
            'action_type' => 'Approval',
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'location_id' => $locationId,
            'start_date' => $asset1Date->copy()->addHours(4),
            'end_date' => null,
            'actor_id' => $ifsDm->id,
            'note' => 'Aktivasi aset disetujui oleh DM IFS. Catatan: Disetujui untuk operasional AI Research',
        ]);

        // 4. Asset 2: Pending Activation Unit (Belum Diverifikasi)
        $asset2Date = Carbon::create(2026, 2, 16, 11, 0, 0);
        $asset2 = Asset::withoutEvents(fn () => Asset::create([
            'number' => self::SHOWCASE_ASSET_NUMBERS[1],
            'lot_id' => $dgxLot->id,
            'location_id' => $locationId,
            'status' => 'Belum Diverifikasi',
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'price' => 350000000,
            'comp_specification' => 'AI Supercomputer Server, 8x NVIDIA H100 GPU',
            'image_url' => 'inventory/models/dummy_dgx.jpg',
            'burden' => 'Corporate',
            'created_at' => $asset2Date,
            'updated_at' => $asset2Date,
        ]));

        AssetActivationApproval::create([
            'asset_id' => $asset2->id,
            'requester_id' => $adminUser->id,
            'approver_id' => null,
            'decision' => 'pending',
            'note' => null,
            'requested_at' => $asset2Date,
            'decided_at' => null,
            'created_at' => $asset2Date,
            'updated_at' => $asset2Date,
        ]);

        AssetLifecycle::create([
            'asset_id' => $asset2->id,
            'action_type' => 'Registrasi',
            'status' => 'Belum Diverifikasi',
            'condition' => 'Bagus',
            'location_id' => $locationId,
            'start_date' => $asset2Date,
            'end_date' => null,
            'actor_id' => $adminUser->id,
            'note' => 'Aset berhasil diregistrasi ke dalam sistem.',
        ]);
    }

    /**
     * Seed Input 2 & Deactivation: Kursi Kerja FUR-KK-0004 Hibah LOT and Deactivation Lifecycle Showcase.
     */
    private function seedChairHibahAndDeactivations(User $adminUser, User $ifsDm, int $locationId, InventoryLogService $inventoryLogService): void
    {
        $furCategory = \App\Models\Master\Category::firstOrCreate(['code' => 'FUR'], ['name' => 'Furniture']);
        $furType = Type::firstOrCreate(['code' => 'FUR-KK'], [
            'name' => 'Kursi Kerja',
            'category_id' => $furCategory->id,
            'is_consumable' => false,
        ]);
        $chairModel = InventoryModel::firstOrCreate(
            ['number' => 'FUR-KK-0004'],
            [
                'type_id' => $furType->id,
                'brand_id' => Brand::firstOrCreate(['name' => 'Indachi'])->id,
                'uom_id' => Uom::firstOrCreate(['name' => 'Unit'])->id,
                'name' => 'Kursi Kerja',
                'specification' => 'Warna Hitam',
            ]
        );

        $cfsOrganizer = Organizer::firstOrCreate(['name' => 'CFS']);

        // 1. Create Hibah LOT
        $hibahDate = Carbon::create(2026, 3, 1, 9, 30, 0);
        $chairLot = Lot::create([
            'number' => self::SHOWCASE_CHAIR_LOT_NUMBER,
            'model_id' => $chairModel->id,
            'organizer_id' => $cfsOrganizer?->id ?? 1,
            'vendor_id' => null,
            'initial_quantity' => 5,
            'current_quantity' => 5,
            'entry_type' => 'Hibah',
            'memo_number' => 'MEMO-CFS-2026-HB01',
            'purchase_grant_date' => $hibahDate,
            'location_id' => null,
            'image_url' => 'inventory/lots/dummy_chair.webp',
            'burden' => 'Corporate',
            'created_at' => $hibahDate,
            'updated_at' => $hibahDate,
        ]);

        $inboundLog = $inventoryLogService->logLotCreated($chairLot, $adminUser, null, 5);
        $inboundLog->update(['created_at' => $hibahDate]);

        // 2. Unit 1: Active Chair (Tersedia)
        $chair1Date = Carbon::create(2026, 3, 2, 8, 30, 0);
        $chair1 = Asset::withoutEvents(fn () => Asset::create([
            'number' => self::SHOWCASE_ASSET_NUMBERS[2],
            'lot_id' => $chairLot->id,
            'location_id' => $locationId,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'price' => 1250000,
            'image_url' => 'inventory/assets/dummy_chair.webp',
            'burden' => 'Corporate',
            'created_at' => $chair1Date,
            'updated_at' => $chair1Date,
        ]));

        AssetLifecycle::create([
            'asset_id' => $chair1->id,
            'action_type' => 'Registrasi',
            'status' => 'Belum Diverifikasi',
            'condition' => 'Bagus',
            'location_id' => $locationId,
            'start_date' => $chair1Date,
            'end_date' => $chair1Date->copy()->addHours(2),
            'actor_id' => $adminUser->id,
            'note' => 'Aset berhasil diregistrasi ke dalam sistem.',
        ]);

        AssetLifecycle::create([
            'asset_id' => $chair1->id,
            'action_type' => 'Approval',
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'location_id' => $locationId,
            'start_date' => $chair1Date->copy()->addHours(2),
            'end_date' => null,
            'actor_id' => $ifsDm->id,
            'note' => 'Aktivasi aset disetujui oleh DM IFS. Catatan: Disetujui untuk operasional kantor',
        ]);

        // 3. Unit 2: Pending DM Deactivation (Tersedia, approval_status: Pending:DM, condition: Rusak Total)
        $chair2Date = Carbon::create(2026, 3, 10, 10, 0, 0);
        $chair2 = Asset::withoutEvents(fn () => Asset::create([
            'number' => self::SHOWCASE_ASSET_NUMBERS[3],
            'lot_id' => $chairLot->id,
            'location_id' => $locationId,
            'status' => 'Tersedia',
            'condition' => 'Rusak Total',
            'approval_status' => 'Pending:DM',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'price' => 1250000,
            'image_url' => 'inventory/assets/dummy_chair.webp',
            'burden' => 'Corporate',
            'created_at' => $chair2Date,
            'updated_at' => $chair2Date,
        ]));

        AssetDeletionApproval::withoutEvents(fn () => AssetDeletionApproval::create([
            'asset_id' => $chair2->id,
            'requester_id' => $adminUser->id,
            'approver_id' => null,
            'proposed_condition' => 'Rusak Total',
            'previous_condition' => 'Bagus',
            'previous_status' => 'Tersedia',
            'decision' => 'pending',
            'admin_note' => 'Pengajuan deaktivasi kursi kerja rusak hidrolik dan patah kaki',
            'dm_note' => null,
            'requested_at' => $chair2Date,
            'decided_at' => null,
            'memo_url' => 'memos/dummy_memo.pdf',
            'lost_doc_url' => null,
            'created_at' => $chair2Date,
            'updated_at' => $chair2Date,
        ]));

        AssetLifecycle::create([
            'asset_id' => $chair2->id,
            'action_type' => 'Perubahan kondisi',
            'status' => 'Tersedia',
            'condition' => 'Rusak Total',
            'location_id' => $locationId,
            'start_date' => $chair2Date,
            'end_date' => null,
            'actor_id' => $adminUser->id,
            'note' => 'Pengajuan deaktivasi kursi kerja rusak hidrolik dan patah kaki',
        ]);

        // 4. Unit 3: Pending BoD/BoC Deactivation (Tidak Aktif, approval_status: Pending:BoD/BoC)
        $chair3ReqDate = Carbon::create(2026, 3, 12, 11, 0, 0);
        $chair3AppDate = Carbon::create(2026, 3, 13, 14, 0, 0);
        $chair3 = Asset::withoutEvents(fn () => Asset::create([
            'number' => self::SHOWCASE_ASSET_NUMBERS[4],
            'lot_id' => $chairLot->id,
            'location_id' => $locationId,
            'status' => 'Tidak Aktif',
            'condition' => 'Hilang',
            'approval_status' => 'Pending:BoD/BoC',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'price' => 1250000,
            'image_url' => 'inventory/assets/dummy_chair.webp',
            'burden' => 'Corporate',
            'created_at' => $chair3ReqDate,
            'updated_at' => $chair3AppDate,
        ]));

        AssetDeletionApproval::withoutEvents(fn () => AssetDeletionApproval::create([
            'asset_id' => $chair3->id,
            'requester_id' => $adminUser->id,
            'approver_id' => $ifsDm->id,
            'proposed_condition' => 'Hilang',
            'previous_condition' => 'Bagus',
            'previous_status' => 'Tersedia',
            'decision' => 'approved',
            'admin_note' => 'Pengajuan penghapusan karena unit kursi hilang saat renovasi ruangan',
            'dm_note' => 'Disetujui untuk diteruskan ke BoD/BoC',
            'requested_at' => $chair3ReqDate,
            'decided_at' => $chair3AppDate,
            'memo_url' => 'memos/dummy_memo.pdf',
            'lost_doc_url' => 'lost_docs/dummy_lost.pdf',
            'created_at' => $chair3ReqDate,
            'updated_at' => $chair3AppDate,
        ]));

        AssetLifecycle::create([
            'asset_id' => $chair3->id,
            'action_type' => 'Perubahan kondisi',
            'status' => 'Tersedia',
            'condition' => 'Hilang',
            'location_id' => $locationId,
            'start_date' => $chair3ReqDate,
            'end_date' => $chair3AppDate,
            'actor_id' => $adminUser->id,
            'note' => 'Pengajuan penghapusan karena unit kursi hilang saat renovasi ruangan',
        ]);

        AssetLifecycle::create([
            'asset_id' => $chair3->id,
            'action_type' => 'Persetujuan',
            'status' => 'Tidak Aktif',
            'condition' => 'Hilang',
            'location_id' => $locationId,
            'start_date' => $chair3AppDate,
            'end_date' => null,
            'actor_id' => $ifsDm->id,
            'note' => 'Disetujui untuk diteruskan ke BoD/BoC',
        ]);

        // 5. Unit 4: Approved BoD/BoC Deactivation (Dihapus, condition: Rusak Total)
        $chair4ReqDate = Carbon::create(2026, 3, 15, 9, 0, 0);
        $chair4BodDate = Carbon::create(2026, 3, 16, 10, 30, 0);
        $chair4 = Asset::withoutEvents(fn () => Asset::create([
            'number' => self::SHOWCASE_ASSET_NUMBERS[5],
            'lot_id' => $chairLot->id,
            'location_id' => $locationId,
            'status' => 'Dihapus',
            'condition' => 'Rusak Total',
            'approval_status' => null,
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'price' => 1250000,
            'image_url' => 'inventory/assets/dummy_chair.webp',
            'burden' => 'Corporate',
            'created_at' => $chair4ReqDate,
            'updated_at' => $chair4BodDate,
        ]));

        AssetDeletionApproval::withoutEvents(fn () => AssetDeletionApproval::create([
            'asset_id' => $chair4->id,
            'requester_id' => $adminUser->id,
            'approver_id' => $ifsDm->id,
            'proposed_condition' => 'Rusak Total',
            'previous_condition' => 'Bagus',
            'previous_status' => 'Tersedia',
            'decision' => 'approved',
            'admin_note' => 'Kursi rusak total tidak dapat direparasi',
            'dm_note' => 'Disetujui untuk pemusnahan aset',
            'requested_at' => $chair4ReqDate,
            'decided_at' => $chair4BodDate,
            'memo_url' => 'memos/dummy_memo.pdf',
            'bod_boc_approval_url' => 'bod_boc_approvals/dummy_approve.pdf',
            'created_at' => $chair4ReqDate,
            'updated_at' => $chair4BodDate,
        ]));

        AssetLifecycle::create([
            'asset_id' => $chair4->id,
            'action_type' => 'Persetujuan',
            'status' => 'Dihapus',
            'condition' => 'Rusak Total',
            'location_id' => $locationId,
            'start_date' => $chair4BodDate,
            'end_date' => null,
            'actor_id' => $adminUser->id,
            'note' => 'BoD/BoC menyetujui penghapusan asset dengan kondisi Rusak Total',
        ]);

        // 6. Unit 5: Rejected BoD/BoC Deactivation (Tersedia, proposed: Rusak Total)
        $chair5ReqDate = Carbon::create(2026, 3, 18, 10, 0, 0);
        $chair5BodDate = Carbon::create(2026, 3, 20, 15, 0, 0);
        $chair5 = Asset::withoutEvents(fn () => Asset::create([
            'number' => self::SHOWCASE_ASSET_NUMBERS[6],
            'lot_id' => $chairLot->id,
            'location_id' => $locationId,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'approval_status' => null,
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'price' => 1250000,
            'image_url' => 'inventory/assets/dummy_chair.webp',
            'burden' => 'Corporate',
            'created_at' => $chair5ReqDate,
            'updated_at' => $chair5BodDate,
        ]));

        AssetDeletionApproval::withoutEvents(fn () => AssetDeletionApproval::create([
            'asset_id' => $chair5->id,
            'requester_id' => $adminUser->id,
            'approver_id' => $ifsDm->id,
            'proposed_condition' => 'Rusak Total',
            'previous_condition' => 'Bagus',
            'previous_status' => 'Tersedia',
            'decision' => 'rejected',
            'admin_note' => 'Pengajuan penghapusan kursi',
            'dm_note' => 'Disetujui DM',
            'requested_at' => $chair5ReqDate,
            'decided_at' => $chair5BodDate,
            'memo_url' => 'memos/dummy_memo.pdf',
            'bod_boc_disapproval_url' => 'bod_boc_disapprovals/dummy_reject.pdf',
            'created_at' => $chair5ReqDate,
            'updated_at' => $chair5BodDate,
        ]));

        AssetLifecycle::create([
            'asset_id' => $chair5->id,
            'action_type' => 'Penolakan',
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'location_id' => $locationId,
            'start_date' => $chair5BodDate,
            'end_date' => null,
            'actor_id' => $adminUser->id,
            'note' => 'BoD/BoC menolak penghapusan asset dengan kondisi Rusak Total',
        ]);
    }
}
