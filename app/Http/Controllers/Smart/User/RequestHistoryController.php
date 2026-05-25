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
                'spec' => $item->barang->specification ?? '',
                'quantity' => $item->quantity_requested,
                'stockQuantity' => $stockQuantity,
                'category' => $item->barang->subcategory->category->name ?? '-',
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
            'created_at' => $req->created_at ? $req->created_at->format('Y-m-d') : '-',
            'items' => $items,
        ];
    }

    /**
     * Menampilkan halaman riwayat permintaan dan peminjaman pengguna.
     */
    public function index(Request $request): Response
    {
        $requests = SmartRequest::with(['items.barang.subcategory.category', 'items.barang.brand', 'project', 'department'])
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
        $req = SmartRequest::with(['items.barang.subcategory.category', 'items.barang.brand', 'project', 'department'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return Inertia::render('Smart/User/RequestHistoryDetail', [
            'user' => $request->user(),
            'requestId' => $req->id,
            'request' => $this->mapRequest($req),
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
        $req = SmartRequest::where('user_id', $request->user()->id)->findOrFail($id);
        
        $oldStatus = $req->status;
        $isBorrow = (bool) $req->start_date;
        $newStatus = $isBorrow ? 'borrow' : 'success';

        $req->update(['status' => $newStatus]);

        // If it is borrow, mark units status to 'dipinjam'
        // If consumable, mark units status to 'dipakai' or 'nonaktif'
        $requestItems = RequestItem::where('request_id', $req->id)->get();
        foreach ($requestItems as $reqItem) {
            $assignments = \App\Models\Request\RequestUnitAssignment::where('request_item_id', $reqItem->id)->get();
            foreach ($assignments as $asn) {
                $asn->unit->update([
                    'status' => $isBorrow ? 'dipinjam' : 'dipakai',
                    'user_id' => $req->user_id,
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
}
