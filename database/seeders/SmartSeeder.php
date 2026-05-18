<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Department;
use App\Models\Project;
use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use App\Models\Master\Brand;
use App\Models\Master\Uom;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Vendor;
use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Request\Request;
use App\Models\Request\RequestItem;
use App\Models\Request\RequestUnitAssignment;
use App\Models\Request\RequestHandover;
use App\Models\Request\RequestReturn;
use App\Models\Request\RequestStatusLog;
use Carbon\Carbon;

class SmartSeeder extends Seeder
{
    public function run(): void
    {
        // Prevent constraints issues by truncating or checking first
        // Since we are running in development, we can check if data already exists
        if (Category::count() > 0) {
            return;
        }

        // 1. Create Projects
        $projectA = Project::create([
            'name' => 'Mega Mendung Infrastructure',
            'code' => 'PRJ-MGM-001',
            'manager_id' => 3, // manager user id
        ]);

        $projectB = Project::create([
            'name' => 'Tiga Negeri Renovation',
            'code' => 'PRJ-TGN-002',
            'manager_id' => 3, // manager user id
        ]);

        // 2. Create Master Data
        // UOMs
        $uomPcs = Uom::create(['name' => 'Pcs']);
        $uomPack = Uom::create(['name' => 'Pack']);
        $uomBox = Uom::create(['name' => 'Box']);

        // Brands
        $brandApple = Brand::create(['name' => 'Apple']);
        $brandAsus = Brand::create(['name' => 'Asus']);
        $brandEpson = Brand::create(['name' => 'Epson']);
        $brandCanon = Brand::create(['name' => 'Canon']);
        $brandStandard = Brand::create(['name' => 'Standard']);
        $brandGeneric = Brand::create(['name' => 'Generic']);

        // Organizers (Organisasi Pemberi/Unit)
        $orgIFS = Organizer::create(['name' => 'IFS Department']);
        $orgHRD = Organizer::create(['name' => 'HRD']);
        $orgICT = Organizer::create(['name' => 'ICT Division']);

        // Vendors
        $vendorBhinneka = Vendor::create(['name' => 'PT Bhinneka Mentari Dimensi']);
        $vendorGramedia = Vendor::create(['name' => 'Gramedia Asri Media']);
        $vendorGeneric = Vendor::create(['name' => 'Vendor Umum']);

        // Locations
        $locIFS = Location::create(['name' => 'Ruang IFS']);
        $locGudang = Location::create(['name' => 'Gudang Utama']);
        $locMegaMendung = Location::create(['name' => 'Mega Mendung']);
        $locTigaNegeri = Location::create(['name' => 'Tiga Negeri']);

        // Categories & Subcategories
        // Category 1: Assets (Loanable / Returnable)
        $catAsset = Category::create([
            'code' => 'AST',
            'name' => 'Aset Kantor',
            'is_consumable' => false,
        ]);

        $subLaptop = Subcategory::create([
            'code' => 'AST-LPT',
            'name' => 'Laptop & Komputer',
            'category_id' => $catAsset->id,
        ]);

        $subProjector = Subcategory::create([
            'code' => 'AST-PRJ',
            'name' => 'Proyektor & Layar',
            'category_id' => $catAsset->id,
        ]);

        $subCamera = Subcategory::create([
            'code' => 'AST-CAM',
            'name' => 'Kamera & Video',
            'category_id' => $catAsset->id,
        ]);

        // Category 2: Consumables (One-time use)
        $catConsumable = Category::create([
            'code' => 'CSM',
            'name' => 'Barang Habis Pakai',
            'is_consumable' => true,
        ]);

        $subAtk = Subcategory::create([
            'code' => 'CSM-ATK',
            'name' => 'Alat Tulis Kantor',
            'category_id' => $catConsumable->id,
        ]);

        $subPaper = Subcategory::create([
            'code' => 'CSM-PPR',
            'name' => 'Kertas & Dokumentasi',
            'category_id' => $catConsumable->id,
        ]);

        // 3. Create Items (Barangs)
        // Consumables
        $atkStandard = Barang::create([
            'number' => 'CSM-ATK-0001',
            'subcategory_id' => $subAtk->id,
            'brand_id' => $brandStandard->id,
            'uom_id' => $uomPcs->id,
            'type' => 'consumable',
            'specification' => 'Pena Standard Hitam 0.5mm',
            'image_url' => '/images/pena_hitam.png',
        ]);

        $paperA4 = Barang::create([
            'number' => 'CSM-PPR-0002',
            'subcategory_id' => $subPaper->id,
            'brand_id' => $brandGeneric->id,
            'uom_id' => $uomBox->id,
            'type' => 'consumable',
            'specification' => 'Kertas Sinar Dunia A4 80gsm',
            'image_url' => '/images/kertas_a4.png',
        ]);

        // Assets
        $laptopAsus = Barang::create([
            'number' => 'AST-LPT-0001',
            'subcategory_id' => $subLaptop->id,
            'brand_id' => $brandAsus->id,
            'uom_id' => $uomPcs->id,
            'type' => 'asset',
            'specification' => 'ASUS ROG Strix G15, Ryzen 7, 16GB RAM, 512GB SSD',
            'image_url' => '/images/asus_rog.png',
        ]);

        $projectorEpson = Barang::create([
            'number' => 'AST-PRJ-0002',
            'subcategory_id' => $subProjector->id,
            'brand_id' => $brandEpson->id,
            'uom_id' => $uomPcs->id,
            'type' => 'asset',
            'specification' => 'Epson EB-X400 Projector 3300 Lumens',
            'image_url' => '/images/epson_projector.png',
        ]);

        $cameraCanon = Barang::create([
            'number' => 'AST-CAM-0003',
            'subcategory_id' => $subCamera->id,
            'brand_id' => $brandCanon->id,
            'uom_id' => $uomPcs->id,
            'type' => 'asset',
            'specification' => 'Canon EOS M50 Mark II Mirrorless Camera',
            'image_url' => '/images/canon_eos.png',
        ]);

        // 4. Create Lots & Units (specifically for Assets to allow allocation)
        // Lot for Asus Laptop
        $lotLaptop = Lot::create([
            'number' => 'LOT-AST-LPT-0001-2026',
            'barang_id' => $laptopAsus->id,
            'organizer_id' => $orgICT->id,
            'vendor_id' => $vendorBhinneka->id,
            'location_id' => $locGudang->id,
            'po_number' => 'PO-ICT-2026-009',
            'date_of_receipt' => Carbon::now()->subMonths(2),
            'unit_price' => 15000000.00,
            'image_url' => '/images/laptop_receipt.png',
        ]);

        // Create 3 Units of Laptop
        $unitLaptop1 = Unit::create([
            'number' => 'ASUS-ROG-MGM-001',
            'lot_id' => $lotLaptop->id,
            'location_id' => $locMegaMendung->id,
            'status' => 'tersedia',
            'condition' => 'Sangat Baik',
            'price' => 15000000.00,
            'image_url' => '/images/asus_rog.png',
        ]);

        $unitLaptop2 = Unit::create([
            'number' => 'ASUS-ROG-TGN-002',
            'lot_id' => $lotLaptop->id,
            'location_id' => $locTigaNegeri->id,
            'status' => 'tersedia',
            'condition' => 'Baik',
            'price' => 15000000.00,
            'image_url' => '/images/asus_rog.png',
        ]);

        $unitLaptop3 = Unit::create([
            'number' => 'ASUS-ROG-IFS-003',
            'lot_id' => $lotLaptop->id,
            'location_id' => $locIFS->id,
            'status' => 'dipinjam', // to be linked to a borrowed request
            'condition' => 'Baik',
            'price' => 15000000.00,
            'image_url' => '/images/asus_rog.png',
        ]);

        // Lot for Projector
        $lotProjector = Lot::create([
            'number' => 'LOT-AST-PRJ-0001-2026',
            'barang_id' => $projectorEpson->id,
            'organizer_id' => $orgIFS->id,
            'vendor_id' => $vendorBhinneka->id,
            'location_id' => $locIFS->id,
            'po_number' => 'PO-IFS-2026-015',
            'date_of_receipt' => Carbon::now()->subMonth(),
            'unit_price' => 6500000.00,
            'image_url' => '/images/projector_receipt.png',
        ]);

        // Units of Projector
        $unitProj1 = Unit::create([
            'number' => 'EPSON-PRJ-IFS-001',
            'lot_id' => $lotProjector->id,
            'location_id' => $locIFS->id,
            'status' => 'tersedia',
            'condition' => 'Sangat Baik',
            'price' => 6500000.00,
            'image_url' => '/images/epson_projector.png',
        ]);

        $unitProj2 = Unit::create([
            'number' => 'EPSON-PRJ-MGM-002',
            'lot_id' => $lotProjector->id,
            'location_id' => $locMegaMendung->id,
            'status' => 'dipinjam', // to be linked to a return-pending request
            'condition' => 'Baik',
            'price' => 6500000.00,
            'image_url' => '/images/epson_projector.png',
        ]);

        // Lot for Pena Hitam (Consumable lot - no units needed for consumables)
        $lotPena = Lot::create([
            'number' => 'LOT-CSM-ATK-0001-2026',
            'barang_id' => $atkStandard->id,
            'organizer_id' => $orgHRD->id,
            'vendor_id' => $vendorGramedia->id,
            'location_id' => $locIFS->id,
            'po_number' => 'PO-HRD-2026-055',
            'date_of_receipt' => Carbon::now()->subWeek(),
            'unit_price' => 3500.00,
            'image_url' => '/images/pena_receipt.png',
        ]);

        // 5. Create Requests across different status flows!
        $adminId = 1;   // Radifa
        $userId = 2;    // Arya Gepa
        $managerId = 3; // Sonny Handini

        // --- REQUEST 1: Status = wait (Shows in Admin Inbox) ---
        $reqInbox = Request::create([
            'request_number' => '052026-0001',
            'user_id' => $userId,
            'approver_id' => $managerId,
            'utilization' => 'project',
            'department_id' => 1, // IFS
            'project_id' => $projectA->id,
            'reasoning' => 'Butuh laptop spek tinggi untuk pengerjaan modul GIS di lapangan Mega Mendung.',
            'start_date' => Carbon::now()->addDays(2),
            'end_date' => Carbon::now()->addDays(9),
            'status' => 'wait',
            'created_at' => Carbon::now()->subHours(5),
        ]);

        RequestItem::create([
            'request_id' => $reqInbox->id,
            'barang_id' => $laptopAsus->id,
            'quantity_requested' => 1,
        ]);

        RequestStatusLog::create([
            'request_id' => $reqInbox->id,
            'status_from' => 'wait',
            'status_to' => 'wait',
            'changed_by' => $userId,
            'note' => 'Permintaan diajukan oleh karyawan',
            'created_at' => Carbon::now()->subHours(5),
        ]);

        // --- REQUEST 2: Status = handover (Shows in Serah Terima) ---
        $reqHandover = Request::create([
            'request_number' => '052026-0002',
            'user_id' => $userId,
            'approver_id' => $managerId,
            'utilization' => 'corporate',
            'department_id' => 1, // IFS
            'reasoning' => 'Butuh pena untuk rapat koordinasi bulanan divisi.',
            'start_date' => Carbon::now()->addDay(),
            'status' => 'handover',
            'created_at' => Carbon::now()->subDays(1),
        ]);

        $itemConsumable = RequestItem::create([
            'request_id' => $reqHandover->id,
            'barang_id' => $atkStandard->id,
            'quantity_requested' => 12, // 12 pcs of standard pen
        ]);

        RequestHandover::create([
            'request_id' => $reqHandover->id,
            'method' => 'pickup',
            'scheduled_date' => Carbon::now()->addHours(4),
            'location' => 'Gudang Utama',
            'is_auto_set' => false,
            'note' => 'Akan diambil setelah makan siang',
        ]);

        RequestStatusLog::create([
            'request_id' => $reqHandover->id,
            'status_from' => 'wait',
            'status_to' => 'handover',
            'changed_by' => $adminId,
            'note' => 'Permintaan dikonfirmasi oleh Admin dan siap diambil',
            'created_at' => Carbon::now()->subHours(10),
        ]);

        // --- REQUEST 3: Status = borrow (Shows in Lacak Peminjaman) ---
        $reqBorrow = Request::create([
            'request_number' => '052026-0003',
            'user_id' => $userId,
            'approver_id' => $managerId,
            'utilization' => 'project',
            'department_id' => 1, // IFS
            'project_id' => $projectB->id,
            'reasoning' => 'Peminjaman laptop untuk testing aplikasi di Tiga Negeri.',
            'start_date' => Carbon::now()->subDays(3),
            'end_date' => Carbon::now()->addDays(4),
            'status' => 'borrow',
            'created_at' => Carbon::now()->subDays(4),
        ]);

        $itemBorrow = RequestItem::create([
            'request_id' => $reqBorrow->id,
            'barang_id' => $laptopAsus->id,
            'quantity_requested' => 1,
        ]);

        // Assign laptop unit 3 to this request
        RequestUnitAssignment::create([
            'request_item_id' => $itemBorrow->id,
            'unit_id' => $unitLaptop3->id,
            'assigned_at' => Carbon::now()->subDays(3),
        ]);

        RequestHandover::create([
            'request_id' => $reqBorrow->id,
            'method' => 'pickup',
            'scheduled_date' => Carbon::now()->subDays(3),
            'location' => 'Ruang IFS',
            'is_auto_set' => false,
            'user_confirmed_at' => Carbon::now()->subDays(3),
            'auto_confirmed' => false,
            'note' => 'Serah terima selesai dilakukan fisik',
        ]);

        RequestStatusLog::create([
            'request_id' => $reqBorrow->id,
            'status_from' => 'handover',
            'status_to' => 'borrow',
            'changed_by' => $adminId,
            'note' => 'Barang diserahterimakan dan status berganti dipinjam',
            'created_at' => Carbon::now()->subDays(3),
        ]);

        // --- REQUEST 4: Status = return (Shows in Pengembalian) ---
        $reqReturn = Request::create([
            'request_number' => '052026-0004',
            'user_id' => $userId,
            'approver_id' => $managerId,
            'utilization' => 'project',
            'department_id' => 1, // IFS
            'project_id' => $projectA->id,
            'reasoning' => 'Peminjaman proyektor untuk presentasi kickoff klien.',
            'start_date' => Carbon::now()->subDays(7),
            'end_date' => Carbon::now()->subDays(1),
            'status' => 'return',
            'created_at' => Carbon::now()->subDays(8),
        ]);

        $itemReturn = RequestItem::create([
            'request_id' => $reqReturn->id,
            'barang_id' => $projectorEpson->id,
            'quantity_requested' => 1,
        ]);

        // Assign projector unit 2 to this request
        RequestUnitAssignment::create([
            'request_item_id' => $itemReturn->id,
            'unit_id' => $unitProj2->id,
            'assigned_at' => Carbon::now()->subDays(7),
        ]);

        RequestReturn::create([
            'request_id' => $reqReturn->id,
            'method' => 'self',
            'scheduled_date' => Carbon::now()->addHours(2),
            'location' => 'Ruang IFS',
            'is_auto_set' => true,
        ]);

        RequestStatusLog::create([
            'request_id' => $reqReturn->id,
            'status_from' => 'borrow',
            'status_to' => 'return',
            'changed_by' => $userId,
            'note' => 'Karyawan mengajukan proses pengembalian barang',
            'created_at' => Carbon::now()->subHours(2),
        ]);

        // --- REQUEST 5: Status = success (Shows in Arsip) ---
        $reqSuccess = Request::create([
            'request_number' => '052026-0005',
            'user_id' => $userId,
            'approver_id' => $managerId,
            'utilization' => 'corporate',
            'department_id' => 1, // IFS
            'reasoning' => 'Butuh kertas A4 untuk keperluan printing laporan keuangan.',
            'start_date' => Carbon::now()->subDays(15),
            'status' => 'success',
            'created_at' => Carbon::now()->subDays(16),
        ]);

        RequestItem::create([
            'request_id' => $reqSuccess->id,
            'barang_id' => $paperA4->id,
            'quantity_requested' => 2,
        ]);

        RequestStatusLog::create([
            'request_id' => $reqSuccess->id,
            'status_from' => 'handover',
            'status_to' => 'success',
            'changed_by' => $adminId,
            'note' => 'Kertas telah diambil dan transaksi selesai',
            'created_at' => Carbon::now()->subDays(15),
        ]);

        // --- REQUEST 6: Status = reject (Shows in Arsip) ---
        $reqReject = Request::create([
            'request_number' => '052026-0006',
            'user_id' => $userId,
            'approver_id' => $managerId,
            'utilization' => 'corporate',
            'department_id' => 1, // IFS
            'reasoning' => 'Pengajuan peminjaman kamera untuk acara liburan pribadi.',
            'start_date' => Carbon::now()->subDays(10),
            'end_date' => Carbon::now()->subDays(8),
            'status' => 'reject',
            'created_at' => Carbon::now()->subDays(11),
        ]);

        RequestItem::create([
            'request_id' => $reqReject->id,
            'barang_id' => $cameraCanon->id,
            'quantity_requested' => 1,
        ]);

        RequestStatusLog::create([
            'request_id' => $reqReject->id,
            'status_from' => 'wait',
            'status_to' => 'reject',
            'changed_by' => $managerId,
            'note' => 'Peminjaman aset hanya diperbolehkan untuk keperluan pekerjaan resmi kantor.',
            'created_at' => Carbon::now()->subDays(10),
        ]);
    }
}
