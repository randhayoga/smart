<?php

namespace App\Http\Controllers\Smart\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestItem;
use App\Models\Request\RequestStatusLog;
use App\Models\Request\RequestHandover;
use App\Models\Request\RequestReturn;
use App\Models\Inventory\Unit;
use App\Models\Inventory\Barang;
use Carbon\Carbon;

class RequestHistoryController extends Controller
{
    /**
     * Memetakan data permintaan ke format array response.
     */
    private function mapRequest($req)
    {
        $statusMap = [
            'wait' => 'Menunggu approval',
            'approve' => 'Disetujui',
            'confirm' => 'Serah Terima',
            'handover' => 'Serah Terima',
            'borrow' => 'Dipinjam',
            'return' => 'Dipinjam',
            'success' => 'Selesai',
            'reject' => 'Ditolak',
            'cancel' => 'Dibatalkan',
            'pending' => 'Pending',
        ];

        $type = $req->start_date ? 'peminjaman' : 'permintaan';
        
        $durationDays = 0;
        $durationHours = 0;
        if ($req->start_date && $req->end_date) {
            $diff = $req->start_date->diff($req->end_date);
            $durationDays = $diff->days;
            $durationHours = $diff->h;
        }

        $items = $req->items->map(function ($item) {
            // Count stock quantity of units in status 'tersedia'
            $stockQuantity = Unit::whereHas('lot', function ($q) use ($item) {
                $q->where('barang_id', $item->barang_id);
            })->where('status', 'tersedia')->count();

            // Get assigned assets (serial numbers)
            $assets = \App\Models\Request\RequestUnitAssignment::where('request_item_id', $item->id)
                ->with('unit')
                ->get()
                ->pluck('unit.number')
                ->toArray();

            return [
                'id' => $item->id,
                'barang_id' => $item->barang_id,
                'subcategory' => $item->barang->subcategory->name ?? '-',
                'brand' => $item->barang->brand->name ?? '-',
                'name' => $item->barang->name ?? '-',
                'spec' => $item->barang->specification ?? '',
                'quantity' => $item->quantity_requested,
                'stockQuantity' => $stockQuantity,
                'category' => $item->barang->subcategory->category->name ?? '-',
                'is_consumable' => (bool) ($item->barang->subcategory->category->is_consumable ?? false),
                'imageUrl' => $item->barang->image_url ? '/storage/' . $item->barang->image_url : null,
                'assets' => $assets,
            ];
        });

        return [
            'id' => $req->id,
            'number' => $req->request_number,
            'type' => $type,
            'pemanfaatan' => $req->utilization,
            'pemanfaatanDetail' => $req->utilization === 'corporate' 
                ? ($req->department->name ?? '-') 
                : ($req->project->name ?? '-'),
            'durationStart' => $req->start_date ? $req->start_date->format('d-m-Y H:i') : null,
            'durationEnd' => $req->end_date ? $req->end_date->format('d-m-Y H:i') : null,
            'durationDays' => $durationDays,
            'durationHours' => $durationHours,
            'status' => $statusMap[$req->status] ?? $req->status,
            'raw_status' => $req->status,
            'created_at' => $req->created_at ? $req->created_at->format('Y-m-d') : '-',
            'items' => $items,
            'approver_name' => $req->approver?->name,
            'approval_by' => $req->approval?->approver?->name,
            'approval_at' => $req->approval?->decided_at?->format('d-m-Y H:i'),
            'confirmation_by' => $req->adminConfirmation?->admin?->name,
            'confirmation_at' => $req->adminConfirmation?->decided_at?->format('d-m-Y H:i'),
            'return_confirmed_by' => $req->statusLogs->first(fn($log) => $log->status_from === 'return' && $log->status_to === 'success')?->changer?->name,
            'handover_method' => $req->handover ? ($req->handover->method === 'pickup' ? 'Ambil sendiri' : 'Diantar') : null,
            'handover_time' => $req->handover?->scheduled_date?->format('d-m-Y H:i'),
            'handover_location' => $req->handover?->location,
            'handover_note' => $req->handover?->note,
        ];
    }

    /**
     * Menampilkan halaman riwayat permintaan dan peminjaman pengguna.
     */
    public function index(Request $request): Response
    {
        $requests = SmartRequest::with([
            'approver',
            'items.barang.subcategory.category',
            'items.barang.brand',
            'project',
            'department',
            'approval.approver',
            'adminConfirmation.admin',
            'handover',
            'statusLogs.changer'
        ])
            ->where('user_id', $request->user()->id)
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn($r) => $this->mapRequest($r));

        return Inertia::render('Smart/User/RequestHistory', [
            'user' => $request->user(),
            'requests' => $requests,
        ]);
    }

    /**
     * Menampilkan halaman detail dari permintaan tertentu.
     */
    public function show(Request $request, string $id): Response
    {
        $req = SmartRequest::with([
            'approver',
            'items.barang.subcategory.category',
            'items.barang.brand',
            'project',
            'department',
            'approval.approver',
            'adminConfirmation.admin',
            'handover',
            'statusLogs.changer'
        ])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $placements = \App\Models\Request\RequestUnitAssignment::whereIn('request_item_id', $req->items->pluck('id'))
            ->with('unit')
            ->get()
            ->filter(fn($asn) => $asn->unit && $asn->placement)
            ->mapWithKeys(fn($asn) => [$asn->unit->number => $asn->placement])
            ->toArray();

        return Inertia::render('Smart/User/RequestHistoryDetail', [
            'user' => $request->user(),
            'requestId' => $req->id,
            'request' => $this->mapRequest($req),
            'placements' => $placements,
        ]);
    }

    /**
     * Membatalkan permintaan peminjaman atau barang habis pakai.
     */
    public function cancel(Request $request, $id)
    {
        $req = SmartRequest::where('user_id', $request->user()->id)->findOrFail($id);
        
        $oldStatus = $req->status;
        $req->update(['status' => 'cancel']);

        RequestStatusLog::create([
            'request_id' => $req->id,
            'status_from' => $oldStatus,
            'status_to' => 'cancel',
            'changed_by' => $request->user()->id,
            'note' => 'Permintaan dibatalkan oleh pengguna.',
        ]);

        return redirect()->back()->with('success', 'Permintaan berhasil dibatalkan.');
    }

    /**
     * Mengatur jadwal serah terima barang.
     */
    public function handover(Request $request, $id)
    {
        $validated = $request->validate([
            'method' => 'required|string',
            'scheduled_date' => 'required|date',
            'location' => 'required|string',
            'note' => 'nullable|string',
        ]);

        $req = SmartRequest::where('user_id', $request->user()->id)->findOrFail($id);
        
        $oldStatus = $req->status;
        $req->update(['status' => 'confirm']); // transition to confirm/serah terima

        RequestHandover::updateOrCreate(
            ['request_id' => $req->id],
            [
                'method' => $validated['method'] === 'Ambil sendiri' ? 'pickup' : 'delivery',
                'scheduled_date' => Carbon::parse($validated['scheduled_date']),
                'location' => $validated['location'],
                'is_auto_set' => false,
                'note' => $validated['note'] ?? null,
            ]
        );

        RequestStatusLog::create([
            'request_id' => $req->id,
            'status_from' => $oldStatus,
            'status_to' => 'confirm',
            'changed_by' => $request->user()->id,
            'note' => 'Serah terima diatur oleh pengguna.',
        ]);

        return redirect()->back()->with('success', 'Jadwal serah terima berhasil disimpan.');
    }

    /**
     * Mengonfirmasi bahwa barang telah diterima oleh pengguna.
     */
    public function receive(Request $request, $id)
    {
        $req = SmartRequest::with(['items.barang.subcategory.category'])
            ->where('user_id', $request->user()->id)->findOrFail($id);
        
        $oldStatus = $req->status;
        $isBorrow = (bool) $req->start_date;

        // Barang consumable TIDAK masuk daftar peminjaman — langsung ke arsip (success)
        $allConsumable = $req->items->every(function ($item) {
            return (bool) ($item->barang->subcategory->category->is_consumable ?? false);
        });

        $newStatus = ($isBorrow && !$allConsumable) ? 'borrow' : 'success';

        $req->update(['status' => $newStatus]);

        // Tandai status unit sesuai jenis permintaan
        $requestItems = RequestItem::where('request_id', $req->id)->get();
        foreach ($requestItems as $reqItem) {
            $assignments = \App\Models\Request\RequestUnitAssignment::where('request_item_id', $reqItem->id)->get();
            foreach ($assignments as $asn) {
                $status = 'dipakai';
                if ($isBorrow && !$allConsumable && !$asn->unit->is_vehicle) {
                    $status = 'dipinjam';
                }
                $asn->unit->update([
                    'status' => $status,
                ]);
            }
        }

        // Set handover user_confirmed_at
        $handover = RequestHandover::where('request_id', $req->id)->first();
        if ($handover) {
            $handover->update(['user_confirmed_at' => now()]);
        }

        RequestStatusLog::create([
            'request_id' => $req->id,
            'status_from' => $oldStatus,
            'status_to' => $newStatus,
            'changed_by' => $request->user()->id,
            'note' => 'Barang telah diterima oleh pengguna.',
        ]);

        return redirect()->back()->with('success', 'Barang berhasil dikonfirmasi telah diterima.');
    }

    /**
     * Mengatur jadwal pengembalian aset yang dipinjam.
     */
    public function returnAsset(Request $request, $id)
    {
        $validated = $request->validate([
            'method' => 'required|string',
            'scheduled_date' => 'required|date',
            'location' => 'required|string',
            'note' => 'nullable|string',
        ]);

        $req = SmartRequest::where('user_id', $request->user()->id)->findOrFail($id);
        
        $oldStatus = $req->status;
        $req->update(['status' => 'return']); // transition to return phase

        RequestReturn::create([
            'request_id' => $req->id,
            'method' => $validated['method'] === 'Kembalikan sendiri' ? 'self' : 'delivery',
            'scheduled_date' => Carbon::parse($validated['scheduled_date']),
            'location' => $validated['location'],
            'is_auto_set' => false,
            'note' => $validated['note'] ?? null,
        ]);

        RequestStatusLog::create([
            'request_id' => $req->id,
            'status_from' => $oldStatus,
            'status_to' => 'return',
            'changed_by' => $request->user()->id,
            'note' => 'Pengembalian diatur oleh pengguna.',
        ]);

        return redirect()->back()->with('success', 'Jadwal pengembalian berhasil diajukan.');
    }

    /**
     * Memperbarui lokasi penempatan aset (baik oleh user maupun admin).
     */
    public function updatePlacement(Request $request)
    {
        $validated = $request->validate([
            'placements' => 'required|array',
        ]);

        foreach ($validated['placements'] as $assetNumber => $location) {
            $unit = \App\Models\Inventory\Unit::where('number', $assetNumber)->first();
            if ($unit) {
                \App\Models\Request\RequestUnitAssignment::where('unit_id', $unit->id)
                    ->update(['placement' => $location]);
            }
        }

        return redirect()->back()->with('success', 'Penempatan aset berhasil disimpan.');
    }
}
