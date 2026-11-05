<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use App\Models\Request\Request as SmartRequest;
use Inertia\Inertia;

/**
 * Borrowed Controller tracking active asset loans, due dates, borrow durations, and current placements.
 */
class BorrowedController extends Controller
{
    /**
     * Display active borrowings tracking page (Lacak Peminjaman).
     */
    public function index()
    {
        $borrowedList = SmartRequest::with([
            'user:id,employee_name,employee_id',
            'handover:id,request_id,user_confirmed_at',
            'statusLogs:id,request_id,status_from,status_to,note',
            'items' => fn($q) => $q->select('id', 'request_id', 'start_date', 'end_date'),
            'items.fulfillments' => fn($q) => $q->select('id', 'request_item_id', 'asset_id'),
            'items.fulfillments.asset:id,borrowing_type,status',
        ])
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
                    'type' => $req->specific_type_key,
                    'borrower' => $req->user->name ?? '-',
                    'dueDate' => $dueDateStr,
                    'daysLeft' => $daysLeft,
                    'daysPassed' => $daysPassed,
                ];
            });

        return Inertia::render('Smart/Admin/Requests/ActiveRequests/ActiveRequests', [
            'user' => [
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ],
            'activeTab' => 'Lacak Peminjaman',
            'borrowedList' => $borrowedList,
        ]);
    }
}
