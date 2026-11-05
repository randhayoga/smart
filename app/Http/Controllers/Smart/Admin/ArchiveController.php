<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use App\Models\Request\Request as SmartRequest;

/**
 * Archive Controller providing searchable history of fulfilled, rejected, and cancelled requests.
 */
class ArchiveController extends Controller
{
    /**
     * Display the request/borrow archive listing page (Arsip) - redirects to Requests List with Arsip tab.
     */
    public function index()
    {
        return redirect()->route('smart.requests.index', ['tab' => 'Arsip']);
    }

    /**
     * Retrieve formatted archive request data.
     */
    public static function getArchiveData(): \Illuminate\Support\Collection
    {
        return SmartRequest::with([
            'user:id,employee_name',
            'statusLogs:id,request_id,status_from,status_to,note',
            'items' => fn($q) => $q->select('id', 'request_id', 'start_date', 'end_date', 'status'),
            'items.fulfillments:id,request_item_id,asset_id',
            'items.fulfillments.asset:id,borrowing_type,status',
        ])
            ->whereIn('status', ['success', 'rejected', 'cancelled'])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($req) {
                $statusLabel = match ($req->status) {
                    'success' => 'Sukses',
                    'rejected' => 'Ditolak',
                    'cancelled' => 'Dibatalkan',
                    default => 'Pending',
                };

                $startTime = '-';
                $endTime = '-';
                if ($req->start_date) {
                    $startTime = $req->start_date->format('d-m-Y H:i');
                }
                if ($req->end_date) {
                    $endTime = $req->end_date->format('d-m-Y H:i');
                }

                return [
                    'id' => $req->id,
                    'number' => $req->request_number,
                    'type' => $req->specific_type_key,
                    'status' => $statusLabel,
                    'requester' => $req->user->name ?? '-',
                    'startTime' => $startTime,
                    'endTime' => $endTime,
                ];
            });
    }
}
