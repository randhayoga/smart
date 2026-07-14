<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Inventory\UnitStatusApproval;
use App\Models\Inventory\Unit;
use App\Models\Inventory\UnitLifecycle;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class UnitStatusApprovalSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure dummy memo files exist in storage (for testing download/view)
        if (!Storage::disk('public')->exists('memos')) {
            Storage::disk('public')->makeDirectory('memos');
        }
        
        Storage::disk('public')->put('memos/berita_acara_sfg14.pdf', 'Dummy Memo Content for Acer Laptop lost status.');
        Storage::disk('public')->put('memos/berita_acara_byd.pdf', 'Dummy Memo Content for BYD Vehicle broken status.');

        // Clear existing approvals/lifecycles first to prevent duplicates
        UnitStatusApproval::truncate();
        UnitLifecycle::truncate();

        // Find existing units
        $laptop = Unit::where('number', 'LOT-2026-ELE-LAP-0001-0001-U01')->first();
        $vehicle = Unit::where('number', 'LOT-2026-KEN-MOB-0001-0002-U01')->first();

        if ($laptop) {
            // Seed lifecycles (Audit Trail)
            UnitLifecycle::create([
                'unit_id' => $laptop->id,
                'action_type' => 'Registrasi',
                'status' => 'Tersedia',
                'condition' => 'Baik',
                'location_id' => $laptop->location_id,
                'floor_id' => $laptop->floor_id,
                'room_id' => $laptop->room_id,
                'start_date' => Carbon::now()->subDays(30),
                'end_date' => Carbon::now()->subDays(2),
                'actor_id' => 1, // Admin: Radifa
                'note' => 'Stok awal terdaftar dan tersedia',
            ]);

            UnitLifecycle::create([
                'unit_id' => $laptop->id,
                'action_type' => 'Perubahan status',
                'status' => 'Hilang',
                'condition' => 'Baik',
                'location_id' => $laptop->location_id,
                'floor_id' => $laptop->floor_id,
                'room_id' => $laptop->room_id,
                'start_date' => Carbon::now()->subDays(2),
                'end_date' => null,
                'actor_id' => 1, // Admin: Radifa
                'note' => 'Aset dilaporkan hilang setelah dipinjam di proyek C',
            ]);

            // Seed status approval
            UnitStatusApproval::withoutEvents(function () use ($laptop) {
                UnitStatusApproval::create([
                    'unit_id' => $laptop->id,
                    'requester_id' => 1, // Admin Radifa
                    'proposed_status' => 'Hilang',
                    'previous_status' => 'Tersedia',
                    'decision' => 'pending',
                    'note' => '',
                    'requested_at' => Carbon::now()->subDays(2),
                    'memo_url' => 'memos/berita_acara_sfg14.pdf',
                    'lost_doc_url' => 'lost_docs/surat_kehilangan_sfg14.pdf',
                ]);
            });
            $laptop->update(['status' => 'Pending']);
        }

        if ($vehicle) {
            // Seed lifecycles (Audit Trail)
            UnitLifecycle::create([
                'unit_id' => $vehicle->id,
                'action_type' => 'Registrasi',
                'status' => 'Tersedia',
                'condition' => 'Baik',
                'location_id' => $vehicle->location_id,
                'floor_id' => $vehicle->floor_id,
                'room_id' => $vehicle->room_id,
                'start_date' => Carbon::now()->subDays(45),
                'end_date' => Carbon::now()->subDays(15),
                'actor_id' => 1,
                'note' => 'Registrasi unit kendaraan baru',
            ]);

            UnitLifecycle::create([
                'unit_id' => $vehicle->id,
                'action_type' => 'Pemeliharaan',
                'status' => 'Perbaikan',
                'condition' => 'Rusak Ringan',
                'location_id' => $vehicle->location_id,
                'floor_id' => $vehicle->floor_id,
                'room_id' => $vehicle->room_id,
                'start_date' => Carbon::now()->subDays(15),
                'end_date' => Carbon::now()->subDays(1),
                'actor_id' => 1,
                'note' => 'Perbaikan rutin berkala di bengkel resmi',
            ]);

            UnitLifecycle::create([
                'unit_id' => $vehicle->id,
                'action_type' => 'Perubahan status',
                'status' => 'Rusak Total',
                'condition' => 'Rusak Berat',
                'location_id' => $vehicle->location_id,
                'floor_id' => $vehicle->floor_id,
                'room_id' => $vehicle->room_id,
                'start_date' => Carbon::now()->subDays(1),
                'end_date' => null,
                'actor_id' => 1,
                'note' => 'Mengalami kerusakan mesin saat operasional',
            ]);

            // Seed status approval
            UnitStatusApproval::withoutEvents(function () use ($vehicle) {
                UnitStatusApproval::create([
                    'unit_id' => $vehicle->id,
                    'requester_id' => 1, // Admin Radifa
                    'proposed_status' => 'Rusak Total',
                    'previous_status' => 'Perbaikan',
                    'decision' => 'pending',
                    'note' => '',
                    'requested_at' => Carbon::now()->subDays(1),
                    'memo_url' => 'memos/berita_acara_byd.pdf',
                    'lost_doc_url' => null,
                ]);
            });
            $vehicle->update(['status' => 'Pending']);
        }
    }
}
