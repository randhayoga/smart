<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Request\Request as SmartRequest;

class BorrowedController extends Controller
{
    /**
     * Menampilkan halaman daftar peminjaman aktif (Lacak Peminjaman).
     */
    public function index()
    {
        $borrowedList = SmartRequest::with(['user', 'handover'])
            ->where('status', 'borrow')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($req) {
                // Calculate days left
                $daysLeft = '-';
                $dueDateStr = '-';
                if ($req->end_date) {
                    $dueDateStr = $req->end_date->format('d-m-Y H:i');
                    $diff = now()->diffInDays($req->end_date, false);
                    $daysLeft = $diff >= 0 ? (string)(int)$diff : 'Telat ' . abs((int)$diff) . ' hari';
                }

                // Calculate days passed since the goods were taken
                $daysPassed = '-';
                $confirmedAt = $req->handover?->user_confirmed_at ?? $req->updated_at;
                if ($confirmedAt) {
                    $diffPassed = $confirmedAt->diffInDays(now(), false);
                    $daysPassedVal = max(0, (int) $diffPassed);
                    $daysPassed = (string)$daysPassedVal . ' hari';
                }

                return [
                    'id' => $req->id,
                    'number' => $req->request_number,
                    'borrower' => $req->user->name ?? '-',
                    'dueDate' => $dueDateStr,
                    'daysLeft' => $daysLeft,
                    'daysPassed' => $daysPassed,
                ];
            });

        return Inertia::render('Smart/Admin/Borrowed', [
            'user' => [
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ],
            'borrowedList' => $borrowedList,
        ]);
    }

    /**
     * Menampilkan detail informasi peminjaman barang aktif.
     */
    public function show($id)
    {
        $req = SmartRequest::with(['user', 'approver', 'approval.approver', 'adminConfirmation.admin', 'items.barang.subcategory.category', 'items.barang.brand', 'project', 'department'])
            ->findOrFail($id);

        $daysLeft = '-';
        $dueDateStr = '-';
        if ($req->end_date) {
            $dueDateStr = $req->end_date->format('d-m-Y H:i');
            $diff = now()->diffInDays($req->end_date, false);
            $daysLeft = $diff >= 0 ? (string)$diff : 'Telat ' . abs($diff) . ' hari';
        }

        $durationDays = 0;
        $durationHours = 0;
        if ($req->start_date && $req->end_date) {
            $diff = $req->start_date->diff($req->end_date);
            $durationDays = $diff->days;
            $durationHours = $diff->h;
        }

        $items = $req->items->map(function ($item) {
            $assets = \App\Models\Request\RequestUnitAssignment::where('request_item_id', $item->id)
                ->with('unit')
                ->get()
                ->pluck('unit.number')
                ->toArray();

            return [
                'id' => $item->id,
                'brand' => ($item->barang->brand->name ?? '-') . ' ' . ($item->barang->specification ?? ''),
                'category' => $item->barang->subcategory->category->name ?? '-',
                'subcategory' => $item->barang->subcategory->name ?? '-',
                'quantity' => $item->quantity_requested,
                'assets' => $assets,
                'imageUrl' => $item->barang->image_url ?? null,
            ];
        });

        $borrowedData = [
            'id' => $req->id,
            'number' => $req->request_number,
            'requester' => $req->user->name ?? '-',
            'approver' => $req->approver->name ?? '-',
            'approval_by' => $req->approval?->approver?->name,
            'confirmation_by' => $req->adminConfirmation?->admin?->name,
            'createdAt' => $req->created_at ? $req->created_at->format('d-m-Y H:i') : '-',
            'pemanfaatan' => $req->utilization,
            'pemanfaatanDetail' => $req->utilization === 'corporate' 
                ? ($req->department->name ?? '-') 
                : ($req->project->name ?? '-'),
            'durationStart' => $req->start_date ? $req->start_date->format('d-m-Y H:i') : null,
            'durationEnd' => $req->end_date ? $req->end_date->format('d-m-Y H:i') : null,
            'durationDays' => $durationDays,
            'durationHours' => $durationHours,
            'status' => $req->status,
            'type' => $req->start_date ? 'peminjaman' : 'permintaan',
            'items' => $items,
            'dueDate' => $dueDateStr,
            'daysLeft' => $daysLeft,
        ];

        return Inertia::render('Smart/Admin/BorrowedDetail', [
            'borrowedId' => $req->id,
            'request' => $borrowedData,
            'user' => [
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ]
        ]);
    }
}
