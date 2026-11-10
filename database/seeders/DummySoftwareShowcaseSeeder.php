<?php

namespace Database\Seeders;

use App\Mail\SoftwareLicenseExpiringMail;
use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetLifecycle;
use App\Models\Inventory\InventoryLog;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Organizer;
use App\Models\Master\Type;
use App\Models\Master\Uom;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\InventoryLogService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Software Assets Showcase Seeder
 *
 * Mocks realistic software assets, license expiration stages (7-day alert, 14-day alert,
 * 30-day alert, healthy subscription, perpetual, and expired), in-app notifications, and
 * email dispatching strictly limited to Admin Muhamad Radifa Azizi (255578).
 */
class DummySoftwareShowcaseSeeder extends Seeder
{
    public const TARGET_ADMIN_EMPLOYEE_ID = '255578';

    public const SHOWCASE_MODEL_NUMBERS = [
        'SOFT-GEN-0091',
        'SOFT-GEN-0092',
        'SOFT-GEN-0093',
        'SOFT-ENG-0091',
        'SOFT-ENG-0092',
        'SOFT-ENG-0093',
    ];

    public const SHOWCASE_LOT_NUMBERS = [
        'LOT-0001-26-SOFT-GEN-0091',
        'LOT-0001-26-SOFT-GEN-0092',
        'LOT-0001-26-SOFT-GEN-0093',
        'LOT-0001-26-SOFT-ENG-0091',
        'LOT-0001-26-SOFT-ENG-0092',
        'LOT-0001-26-SOFT-ENG-0093',
    ];

    public const SHOWCASE_ASSET_NUMBERS = [
        '00091-SOFT-GEN-ICT-PTRE-26', // JetBrains All Products Pack (Expiring in 7 days, Email + In-App)
        '00092-SOFT-GEN-ICT-PTRE-26', // Microsoft 365 E5 Enterprise (Expiring in 180 days, Healthy)
        '00093-SOFT-GEN-ICT-PTRE-26', // Figma Enterprise Organization (Expired 5 days ago, Lisensi Kedaluwarsa)
        '00094-SOFT-ENG-ICT-PTRE-26', // Autodesk AEC Collection 2026 (Expiring in 14 days, In-App Warning)
        '00095-SOFT-ENG-ICT-PTRE-26', // AutoCAD 2026 Civil 3D (Perpetual, null expiry)
        '00096-SOFT-ENG-ICT-PTRE-26', // SketchUp Pro 2026 (Dipinjam / LT, Expiring in 30 days)
    ];

    /**
     * Run the software showcase database seeds.
     */
    public function run(): void
    {
        // 1. Identify Target Admin User strictly limited to Muhamad Radifa Azizi (255578)
        $adminUser = User::where('employee_id', self::TARGET_ADMIN_EMPLOYEE_ID)->first()
            ?? User::where('name', 'Muhamad Radifa Azizi')->first()
            ?? User::where('email', 'muhamad.radifa@re.rekayasa.co.id')->first();

        if (!$adminUser) {
            $this->command?->warn('Target Admin user (255578) not found. Seeder will create assets without user-specific dispatch.');
        }

        // 2. Safe Idempotent Cleanup of prior showcase records
        $this->cleanupPriorRecords();

        // 3. Resolve Master Entities (Category, Types, Brands, Organizer, UOM, Location)
        $category = Category::where('code', 'SOFT')->first()
            ?? Category::whereRaw('LOWER(name) = ?', ['software'])->first()
            ?? Category::create(['code' => 'SOFT', 'name' => 'Software']);

        $typeGen = Type::firstOrCreate(
            ['category_id' => $category->id, 'code' => 'SOFT-GEN'],
            ['name' => 'General Software', 'is_consumable' => false]
        );

        $typeEng = Type::firstOrCreate(
            ['category_id' => $category->id, 'code' => 'SOFT-ENG'],
            ['name' => 'Engineering Software', 'is_consumable' => false]
        );

        $brandJetBrains = Brand::firstOrCreate(['name' => 'JetBrains']);
        $brandMicrosoft = Brand::where('name', 'Microsoft')->first() ?? Brand::create(['name' => 'Microsoft']);
        $brandFigma = Brand::firstOrCreate(['name' => 'Figma']);
        $brandAutodesk = Brand::where('name', 'Autodesk')->first() ?? Brand::create(['name' => 'Autodesk']);
        $brandTrimble = Brand::firstOrCreate(['name' => 'Trimble']);

        $organizer = Organizer::firstOrCreate(['name' => 'ICT']);

        $uom = Uom::firstOrCreate(['name' => 'Unit']);
        $inventoryLogService = app(InventoryLogService::class);
        $today = Carbon::now()->startOfDay();

        // 4. Seed Showcase Asset 1: JetBrains All Products Pack (Expiring in 7 Days - Critical Email & In-App Notice)
        $assetJetBrains = $this->seedSoftwareAsset(
            modelNumber: 'SOFT-GEN-0091',
            modelName: 'All Products Pack',
            brand: $brandJetBrains,
            type: $typeGen,
            lotNumber: 'LOT-0001-26-SOFT-GEN-0091',
            assetNumber: '00091-SOFT-GEN-ICT-PTRE-26',
            price: 14500000,
            status: 'Tersedia',
            borrowingType: 'LT',
            specification: 'License Key: JB-ENT-2026-X9921-FULL | 5 Floating Seats',
            licenseExpDate: $today->copy()->addDays(7),
            renewalReminder: 30,
            organizer: $organizer,
            uom: $uom,
            adminUser: $adminUser,
            inventoryLogService: $inventoryLogService
        );

        // 5. Seed Showcase Asset 2: Microsoft 365 E5 Enterprise (Healthy Subscription - 180 Days)
        $this->seedSoftwareAsset(
            modelNumber: 'SOFT-GEN-0092',
            modelName: 'Microsoft 365 E5 Enterprise',
            brand: $brandMicrosoft,
            type: $typeGen,
            lotNumber: 'LOT-0001-26-SOFT-GEN-0092',
            assetNumber: '00092-SOFT-GEN-ICT-PTRE-26',
            price: 72000000,
            status: 'Tersedia',
            borrowingType: 'LT',
            specification: 'Tenant: resiapberkarya.com | 100 Enterprise Seats',
            licenseExpDate: $today->copy()->addDays(180),
            renewalReminder: 30,
            organizer: $organizer,
            uom: $uom,
            adminUser: $adminUser,
            inventoryLogService: $inventoryLogService
        );

        // 6. Seed Showcase Asset 3: Figma Enterprise Organization (Expired 5 Days Ago - Lisensi Kedaluwarsa)
        $assetFigma = $this->seedSoftwareAsset(
            modelNumber: 'SOFT-GEN-0093',
            modelName: 'Figma Enterprise Organization',
            brand: $brandFigma,
            type: $typeGen,
            lotNumber: 'LOT-0001-26-SOFT-GEN-0093',
            assetNumber: '00093-SOFT-GEN-ICT-PTRE-26',
            price: 28000000,
            status: 'Lisensi Kedaluwarsa',
            borrowingType: 'LT',
            specification: 'Org: PT REKAYASA ENGINEERING | 25 Full Editors',
            licenseExpDate: $today->copy()->subDays(5),
            renewalReminder: 30,
            organizer: $organizer,
            uom: $uom,
            adminUser: $adminUser,
            inventoryLogService: $inventoryLogService
        );

        // 7. Seed Showcase Asset 4: Autodesk AEC Collection 2026 (Expiring in 14 Days - In-App Warning)
        $assetAutodesk = $this->seedSoftwareAsset(
            modelNumber: 'SOFT-ENG-0091',
            modelName: 'AEC Collection 2026',
            brand: $brandAutodesk,
            type: $typeEng,
            lotNumber: 'LOT-0001-26-SOFT-ENG-0091',
            assetNumber: '00094-SOFT-ENG-ICT-PTRE-26',
            price: 52000000,
            status: 'Tersedia',
            borrowingType: 'LT',
            specification: 'Serial: 569-88291044 | Product Key: 02J1 | Multi-user',
            licenseExpDate: $today->copy()->addDays(14),
            renewalReminder: 30,
            organizer: $organizer,
            uom: $uom,
            adminUser: $adminUser,
            inventoryLogService: $inventoryLogService
        );

        // 8. Seed Showcase Asset 5: AutoCAD 2026 Civil 3D (Perpetual License - null Expiry)
        $this->seedSoftwareAsset(
            modelNumber: 'SOFT-ENG-0092',
            modelName: 'AutoCAD 2026 Civil 3D',
            brand: $brandAutodesk,
            type: $typeEng,
            lotNumber: 'LOT-0001-26-SOFT-ENG-0092',
            assetNumber: '00095-SOFT-ENG-ICT-PTRE-26',
            price: 38000000,
            status: 'Tersedia',
            borrowingType: 'LT',
            specification: 'Perpetual Standalone License | Key: ABCDEFGHIJKL',
            licenseExpDate: null,
            renewalReminder: null,
            organizer: $organizer,
            uom: $uom,
            adminUser: $adminUser,
            inventoryLogService: $inventoryLogService
        );

        // 9. Seed Showcase Asset 6: SketchUp Pro 2026 (Dipinjam / LT - Expiring in 30 Days)
        $assetSketchUp = $this->seedSoftwareAsset(
            modelNumber: 'SOFT-ENG-0093',
            modelName: 'SketchUp Pro 2026',
            brand: $brandTrimble,
            type: $typeEng,
            lotNumber: 'LOT-0001-26-SOFT-ENG-0093',
            assetNumber: '00096-SOFT-ENG-ICT-PTRE-26',
            price: 12500000,
            status: 'Dipinjam',
            borrowingType: 'LT',
            specification: 'Assigned to Senior Civil Engineer | Serial: SU-2026-PRO-IND',
            licenseExpDate: $today->copy()->addDays(30),
            renewalReminder: 30,
            organizer: $organizer,
            uom: $uom,
            adminUser: $adminUser,
            inventoryLogService: $inventoryLogService
        );

        // 10. Dispatch Showcase In-App Notifications and Email strictly to Admin Muhamad Radifa Azizi (255578)
        if ($adminUser) {
            $this->dispatchShowcaseAlerts($adminUser, $assetJetBrains, $assetAutodesk, $assetSketchUp, $assetFigma);
        }
    }

    /**
     * Seed a single software model, lot, and asset with lifecycle records.
     */
    protected function seedSoftwareAsset(
        string $modelNumber,
        string $modelName,
        Brand $brand,
        Type $type,
        string $lotNumber,
        string $assetNumber,
        float $price,
        string $status,
        string $borrowingType,
        string $specification,
        ?Carbon $licenseExpDate,
        ?int $renewalReminder,
        Organizer $organizer,
        Uom $uom,
        ?User $adminUser,
        InventoryLogService $inventoryLogService
    ): Asset {
        $model = InventoryModel::create([
            'number' => $modelNumber,
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'name' => $modelName,
            'specification' => $specification,
        ]);

        if ($adminUser) {
            $inventoryLogService->logModelCreated($model, $adminUser);
        }

        $lot = Lot::create([
            'number' => $lotNumber,
            'model_id' => $model->id,
            'organizer_id' => $organizer->id,
            'vendor_id' => null,
            'initial_quantity' => 1,
            'current_quantity' => 1,
            'entry_type' => 'Procurement',
            'po_number' => 'PO-ICT-2026-' . substr(md5($modelNumber), 0, 6),
            'purchase_grant_date' => Carbon::now()->subMonths(6),
            'location_id' => null,
            'unit_price' => $price,
            'burden' => 'Corporate',
        ]);

        if ($adminUser) {
            $inventoryLogService->logLotCreated($lot, $adminUser);
        }

        $asset = Asset::withoutEvents(fn () => Asset::create([
            'lot_id' => $lot->id,
            'location_id' => null,
            'number' => $assetNumber,
            'legacy_number' => $assetNumber,
            'status' => $status,
            'approval_status' => null,
            'condition' => null,
            'borrowing_type' => $borrowingType,
            'classification' => 'Aset',
            'price' => $price,
            'burden' => 'Corporate',
            'comp_specification' => null,
            'license_exp_date' => $licenseExpDate?->toDateString(),
            'renewal_reminder' => $renewalReminder,
        ]));

        $createdAt = Carbon::now()->subMonths(6);
        if ($status === 'Lisensi Kedaluwarsa' && $licenseExpDate) {
            // Initial deployment lifecycle
            AssetLifecycle::create([
                'asset_id' => $asset->id,
                'actor_id' => $adminUser?->id,
                'action_type' => 'Aktivasi',
                'status' => 'Tersedia',
                'condition' => null,
                'location_id' => null,
                'start_date' => $createdAt,
                'end_date' => $licenseExpDate,
                'note' => "Showcase Software Deployment: {$brand->name} {$modelName}",
            ]);

            // Expired lifecycle
            AssetLifecycle::create([
                'asset_id' => $asset->id,
                'actor_id' => null,
                'action_type' => 'Perubahan status',
                'status' => 'Lisensi Kedaluwarsa',
                'condition' => null,
                'location_id' => null,
                'start_date' => $licenseExpDate,
                'end_date' => null,
                'note' => "Lisensi software telah kedaluwarsa pada {$licenseExpDate->format('d-m-Y')}.",
            ]);
        } elseif ($status === 'Dipinjam') {
            $assignedAt = Carbon::now()->subMonths(3);
            // Initial deployment lifecycle
            AssetLifecycle::create([
                'asset_id' => $asset->id,
                'actor_id' => $adminUser?->id,
                'action_type' => 'Aktivasi',
                'status' => 'Tersedia',
                'condition' => null,
                'location_id' => null,
                'start_date' => $createdAt,
                'end_date' => $assignedAt,
                'note' => "Showcase Software Deployment: {$brand->name} {$modelName}",
            ]);

            // Borrowing lifecycle
            AssetLifecycle::create([
                'asset_id' => $asset->id,
                'actor_id' => $adminUser?->id,
                'action_type' => 'Peminjaman',
                'status' => 'Dipinjam',
                'condition' => null,
                'location_id' => null,
                'start_date' => $assignedAt,
                'end_date' => null,
                'note' => "Peminjaman jangka panjang (LT) lisensi software untuk Senior Civil Engineer.",
            ]);
        } else {
            AssetLifecycle::create([
                'asset_id' => $asset->id,
                'actor_id' => $adminUser?->id,
                'action_type' => 'Aktivasi',
                'status' => $status,
                'condition' => null,
                'location_id' => null,
                'start_date' => $createdAt,
                'end_date' => null,
                'note' => "Showcase Software Deployment: {$brand->name} {$modelName}",
            ]);
        }

        return $asset;
    }

    /**
     * Dispatch in-app notifications and email notice strictly targeted to Muhamad Radifa Azizi (255578).
     */
    protected function dispatchShowcaseAlerts(
        User $adminUser,
        Asset $assetJetBrains,
        Asset $assetAutodesk,
        Asset $assetSketchUp,
        Asset $assetFigma
    ): void {
        $notificationService = app(NotificationService::class);

        // 1. Critical Notification (7 days) for JetBrains All Products Pack
        $brandJetBrains = $assetJetBrains->lot?->model?->brand?->name ?? '';
        $nameJetBrains = $assetJetBrains->lot?->model?->name ?? '';
        $swJetBrains = trim("{$brandJetBrains} {$nameJetBrains}");

        $title7 = __("inventory.notification_license_expiring_title", ['days' => 7, 'asset_number' => $assetJetBrains->number]);
        $msg7 = __("inventory.notification_license_expiring_message", ['days' => 7, 'asset_number' => $assetJetBrains->number, 'software' => $swJetBrains]);
        $url7 = '/smart/stock-management/non-consumables?search=' . urlencode($assetJetBrains->number);

        $notificationService->sendToUser(
            $adminUser,
            $title7,
            $msg7,
            'warning',
            $url7,
            [
                'asset_id' => $assetJetBrains->id,
                'asset_number' => $assetJetBrains->number,
                'days_remaining' => 7,
                'title_key' => 'inventory.notification_license_expiring_title',
                'title_params' => ['days' => 7, 'asset_number' => $assetJetBrains->number],
                'message_key' => 'inventory.notification_license_expiring_message',
                'message_params' => ['days' => 7, 'asset_number' => $assetJetBrains->number, 'software' => $swJetBrains],
            ]
        );

        // 2. Email Notification (7 days) strictly sent to Muhamad Radifa Azizi (255578)
        if (!empty($adminUser->email)) {
            try {
                Mail::to($adminUser->email)->send(
                    new SoftwareLicenseExpiringMail($assetJetBrains, 7, $adminUser->name)
                );
                $this->command?->info("Dispatched SoftwareLicenseExpiringMail to {$adminUser->name} ({$adminUser->email}).");
            } catch (\Throwable $e) {
                $this->command?->warn("Email delivery notice: Could not send email to {$adminUser->email} ({$e->getMessage()}).");
                Log::warning("SoftwareLicenseExpiringMail sending skipped or failed: " . $e->getMessage());
            }
        }

        // 3. Warning Notification (14 days) for Autodesk AEC Collection 2026
        $brandAutodesk = $assetAutodesk->lot?->model?->brand?->name ?? '';
        $nameAutodesk = $assetAutodesk->lot?->model?->name ?? '';
        $swAutodesk = trim("{$brandAutodesk} {$nameAutodesk}");

        $title14 = __("inventory.notification_license_expiring_title", ['days' => 14, 'asset_number' => $assetAutodesk->number]);
        $msg14 = __("inventory.notification_license_expiring_message", ['days' => 14, 'asset_number' => $assetAutodesk->number, 'software' => $swAutodesk]);

        $notificationService->sendToUser(
            $adminUser,
            $title14,
            $msg14,
            'info',
            '/smart/stock-management/non-consumables?search=' . urlencode($assetAutodesk->number),
            [
                'asset_id' => $assetAutodesk->id,
                'asset_number' => $assetAutodesk->number,
                'days_remaining' => 14,
                'title_key' => 'inventory.notification_license_expiring_title',
                'title_params' => ['days' => 14, 'asset_number' => $assetAutodesk->number],
                'message_key' => 'inventory.notification_license_expiring_message',
                'message_params' => ['days' => 14, 'asset_number' => $assetAutodesk->number, 'software' => $swAutodesk],
            ]
        );

        // 4. Info Notification (30 days) for SketchUp Pro 2026
        $brandSketchUp = $assetSketchUp->lot?->model?->brand?->name ?? '';
        $nameSketchUp = $assetSketchUp->lot?->model?->name ?? '';
        $swSketchUp = trim("{$brandSketchUp} {$nameSketchUp}");

        $title30 = __("inventory.notification_license_expiring_title", ['days' => 30, 'asset_number' => $assetSketchUp->number]);
        $msg30 = __("inventory.notification_license_expiring_message", ['days' => 30, 'asset_number' => $assetSketchUp->number, 'software' => $swSketchUp]);

        $notificationService->sendToUser(
            $adminUser,
            $title30,
            $msg30,
            'info',
            '/smart/stock-management/non-consumables?search=' . urlencode($assetSketchUp->number),
            [
                'asset_id' => $assetSketchUp->id,
                'asset_number' => $assetSketchUp->number,
                'days_remaining' => 30,
                'title_key' => 'inventory.notification_license_expiring_title',
                'title_params' => ['days' => 30, 'asset_number' => $assetSketchUp->number],
                'message_key' => 'inventory.notification_license_expiring_message',
                'message_params' => ['days' => 30, 'asset_number' => $assetSketchUp->number, 'software' => $swSketchUp],
            ]
        );

        // 5. Expired Notification for Figma Enterprise Organization
        $brandFigma = $assetFigma->lot?->model?->brand?->name ?? '';
        $nameFigma = $assetFigma->lot?->model?->name ?? '';
        $swFigma = trim("{$brandFigma} {$nameFigma}");

        $titleExp = __("inventory.notification_license_expired_title", ['asset_number' => $assetFigma->number]);
        $msgExp = __("inventory.notification_license_expired_message", ['asset_number' => $assetFigma->number, 'software' => $swFigma]);

        $notificationService->sendToUser(
            $adminUser,
            $titleExp,
            $msgExp,
            'error',
            '/smart/stock-management/non-consumables?search=' . urlencode($assetFigma->number),
            [
                'asset_id' => $assetFigma->id,
                'asset_number' => $assetFigma->number,
                'title_key' => 'inventory.notification_license_expired_title',
                'title_params' => ['asset_number' => $assetFigma->number],
                'message_key' => 'inventory.notification_license_expired_message',
                'message_params' => ['asset_number' => $assetFigma->number, 'software' => $swFigma],
            ]
        );
    }

    /**
     * Clean up prior showcase models, lots, assets, and notifications.
     */
    protected function cleanupPriorRecords(): void
    {
        $assets = Asset::whereIn('number', self::SHOWCASE_ASSET_NUMBERS)->get();
        $assetIds = $assets->pluck('id')->toArray();

        if (!empty($assetIds)) {
            AssetLifecycle::whereIn('asset_id', $assetIds)->delete();

            // Clean up showcase notifications
            \Illuminate\Support\Facades\DB::table('notifications')
                ->where(function ($q) use ($assetIds) {
                    foreach ($assetIds as $id) {
                        $q->orWhere('data', 'LIKE', '%"asset_id":' . $id . '%');
                    }
                })
                ->delete();

            Asset::whereIn('id', $assetIds)->delete();
        }

        $existingLots = Lot::whereIn('number', self::SHOWCASE_LOT_NUMBERS)->get();
        foreach ($existingLots as $lot) {
            InventoryLog::where('lot_id', $lot->id)->delete();
            $lot->delete();
        }

        $existingModels = InventoryModel::whereIn('number', self::SHOWCASE_MODEL_NUMBERS)->get();
        foreach ($existingModels as $model) {
            InventoryLog::where('model_id', $model->id)->delete();
            $model->delete();
        }
    }
}
