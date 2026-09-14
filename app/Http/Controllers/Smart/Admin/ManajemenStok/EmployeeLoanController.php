<?php

namespace App\Http\Controllers\Smart\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\AdmUser;
use App\Models\HrdEmployee;
use App\Models\Request\RequestFulfillment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

/**
 * Employee Loan Controller managing the nested employee loan resource.
 * Adheres strictly to Cruddy by Design (standard index action for employee loans).
 */
class EmployeeLoanController extends Controller
{
    /**
     * Display a listing of active and historical loans for a given employee.
     */
    public function index(HrdEmployee $employee): JsonResponse
    {
        $userId = $employee->admUser?->id ?? AdmUser::where('username', $employee->employee_id)->value('id');

        if (!$userId) {
            return response()->json([
                'active' => [],
                'history' => [],
            ]);
        }

        $fulfillments = RequestFulfillment::query()
            ->with([
                'unit.location.parent.parent',
                'unit.statusApprovals',
                'unit.lot.barang.subcategory.category',
                'unit.lot.barang.brand',
                'unit.lot.barang.uom',
                'unit.lot.organizer',
                'unit.lot.vendor',
                'unit.lifecycles.actor',
                'requestItem.request.handover',
                'requestItem.request.return',
                'handover',
                'return',
            ])
            ->whereNotNull('unit_id')
            ->whereHas('requestItem.request', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->get();

        $active = [];
        $history = [];

        foreach ($fulfillments as $f) {
            $startDate = $f->confirmed_at
                ?? $f->handover?->user_confirmed_at
                ?? $f->requestItem?->request?->handover?->user_confirmed_at;

            if (!$startDate) {
                continue;
            }

            $completedDate = $f->completed_at
                ?? $f->return?->completed_at
                ?? $f->requestItem?->request?->return?->completed_at;

            $unit = $f->unit;
            $lot = $unit?->lot;
            $barang = $lot?->barang;
            $brand = $barang?->brand?->name ?? '-';
            $location = $unit?->location?->full_name ?? '-';

            $pendingApproval = $unit?->statusApprovals?->firstWhere('decision', 'pending');
            $approvedApproval = $unit?->status === 'Tidak Aktif' 
                ? $unit?->statusApprovals?->where('decision', 'approved')->sortByDesc('updated_at')->first() 
                : null;

            $assetData = $unit ? [
                'id' => $unit->id,
                'number' => $unit->number,
                'status' => $unit->status,
                'proposed_status' => $pendingApproval 
                    ? $pendingApproval->proposed_condition 
                    : ($approvedApproval ? $approvedApproval->proposed_condition : null),
                'proposed_condition' => $pendingApproval 
                    ? $pendingApproval->proposed_condition 
                    : ($approvedApproval ? $approvedApproval->proposed_condition : null),
                'memo_url' => $pendingApproval 
                    ? $pendingApproval->memo_url 
                    : ($approvedApproval ? $approvedApproval->memo_url : null),
                'lost_doc_url' => $pendingApproval 
                    ? $pendingApproval->lost_doc_url 
                    : ($approvedApproval ? $approvedApproval->lost_doc_url : null),
                'bod_boc_approval_url' => $pendingApproval 
                    ? $pendingApproval->bod_boc_approval_url 
                    : ($approvedApproval ? $approvedApproval->bod_boc_approval_url : null),
                'condition' => $unit->condition,
                'price' => $unit->price,
                'image_url' => $unit->image_url,
                'vehicle_registration' => $unit->vehicle_registration,
                'updated_at' => $unit->updated_at ? $unit->updated_at->format('d-m-Y H:i') : '-',
                'location' => $location,
                'location_id' => $unit->location_id,
                'lot_id' => $unit->lot_id,
                'lot_number' => $lot?->number ?? '-',
                'lot_imageUrl' => $lot?->image_url ?? null,
                'lot_unitPrice' => $lot?->unit_price ?? null,
                'organizer' => $lot?->organizer?->name ?? '-',
                'organizer_id' => $lot?->organizer_id ?? null,
                'vendor' => $lot?->vendor?->name ?? '-',
                'vendor_id' => $lot?->vendor_id ?? null,
                'lot_organizer' => $lot?->organizer?->name ?? '-',
                'lot_vendor' => $lot?->vendor?->name ?? '-',
                'lot_po_number' => $lot?->po_number ?? '-',
                'lot_date_of_receipt' => ($lot && $lot->date_of_receipt) ? $lot->date_of_receipt->format('Y-m-d') : null,
                'barang_id' => $barang?->id ?? null,
                'barang_code' => $barang?->number ?? '-',
                'barang_nama' => $barang?->name ?? '-',
                'barang_brand' => $brand,
                'barang_specification' => $barang?->specification ?? '-',
                'barang_category' => $barang?->subcategory?->category?->name ?? '-',
                'barang_subcategory' => $barang?->subcategory?->name ?? '-',
                'barang_uom' => $barang?->uom?->name ?? '-',
                'active_borrowing' => $unit->active_borrowing,
                'lifecycles' => $unit->lifecycles ? $unit->lifecycles->map(function ($log) {
                    return [
                        'waktu' => $log->start_date ? $log->start_date->format('d-m-Y H:i:s') : '-',
                        'status' => $log->status,
                        'action_type' => $log->action_type,
                        'aktor' => ($log->action_type === 'Approval' && str_contains($log->note ?? '', 'BoD/BoC')) ? 'BoD/BoC' : ($log->actor->name ?? '-'),
                        'durasi' => $log->formatted_duration,
                        'catatan' => $log->note ?? '-',
                    ];
                })->toArray() : [],
            ] : null;

            $baseData = [
                'id' => $f->id,
                'unit_id' => $f->unit_id,
                'unit_number' => $unit?->number ?? '-',
                'barang_nama' => $barang?->name ?? '-',
                'brand' => $brand,
                'condition' => $unit?->condition ?? '-',
                'location' => $location,
                'start_date' => Carbon::parse($startDate)->format('d-m-Y H:i'),
                'start_date_raw' => Carbon::parse($startDate)->toISOString(),
                'asset' => $assetData,
            ];

            if ($completedDate) {
                $baseData['return_date'] = Carbon::parse($completedDate)->format('d-m-Y H:i');
                $baseData['return_date_raw'] = Carbon::parse($completedDate)->toISOString();
                $history[] = $baseData;
            } else {
                $dueDate = $f->requestItem?->end_date;
                $baseData['due_date'] = $dueDate ? Carbon::parse($dueDate)->format('d-m-Y') : '-';
                $baseData['due_date_raw'] = $dueDate ? Carbon::parse($dueDate)->toISOString() : null;
                $active[] = $baseData;
            }
        }

        // Sort: Newest start_date for active, newest return_date for history
        usort($active, fn($a, $b) => strcmp($b['start_date_raw'] ?? '', $a['start_date_raw'] ?? ''));
        usort($history, fn($a, $b) => strcmp($b['return_date_raw'] ?? '', $a['return_date_raw'] ?? ''));

        return response()->json([
            'active' => $active,
            'history' => $history,
        ]);
    }
}
