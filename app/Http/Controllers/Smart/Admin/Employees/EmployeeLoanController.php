<?php

namespace App\Http\Controllers\Smart\Admin\Employees;

use App\Http\Controllers\Controller;
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
        $userId = $employee->id;

        if (!$userId) {
            return response()->json([
                'active' => [],
                'history' => [],
            ]);
        }

        $fulfillments = RequestFulfillment::query()
            ->with([
                'asset.location.parent.parent',
                'asset.deletionApprovals',
                'asset.lot.model.type.category',
                'asset.lot.model.brand',
                'asset.lot.model.uom',
                'asset.lot.organizer',
                'asset.lot.vendor',
                'asset.lifecycles.actor',
                'requestItem.request.handover',
                'requestItem.request.return',
                'handover',
                'return',
            ])
            ->whereNotNull('asset_id')
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

            $asset = $f->asset;
            $lot = $asset?->lot;
            $model = $lot?->model;
            $brand = $model?->brand?->name ?? '-';
            $location = $asset?->location?->full_name ?? '-';

            $pendingApproval = $asset?->deletionApprovals?->firstWhere('decision', 'pending');
            $approvedApproval = ($asset && ($asset->hasStatus('Tidak Aktif') || $asset->hasStatus('Pending:BoD/BoC') || $asset->hasStatus('Dihapus')))
                ? $asset->deletionApprovals?->where('decision', 'approved')->sortByDesc('id')->first()
                : null;
            $rejectedApproval = (!$pendingApproval && !$approvedApproval)
                ? $asset?->deletionApprovals?->where('decision', 'rejected')->sortByDesc('id')->first()
                : null;
            $latestDisapproval = $asset?->deletionApprovals?->whereNotNull('bod_boc_disapproval_url')->sortByDesc('id')->first();

            $assetData = $asset ? [
                'id' => $asset->id,
                'number' => $asset->number,
                'legacy_number' => $asset->legacy_number,
                'status' => $asset->status,
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
                'bod_boc_disapproval_url' => $latestDisapproval?->bod_boc_disapproval_url,
                'admin_note' => $pendingApproval ? $pendingApproval->admin_note : ($approvedApproval ? $approvedApproval->admin_note : ($rejectedApproval ? $rejectedApproval->admin_note : null)),
                'dm_note' => $pendingApproval ? $pendingApproval->dm_note : ($approvedApproval ? $approvedApproval->dm_note : ($rejectedApproval ? $rejectedApproval->dm_note : null)),
                'condition' => $asset->condition,
                'price' => $asset->price,
                'type' => $asset->borrowing_type,
                'borrowing_type' => $asset->borrowing_type,
                'classification' => $asset->classification,
                'specification' => $asset->specification,
                'image_url' => $asset->image_url,
                'vehicle_registration' => $asset->vehicle_registration,
                'burden' => $asset->burden,
                'created_at' => $asset->created_at?->toIso8601String(),
                'updated_at' => $asset->updated_at ? $asset->updated_at->format('d-m-Y H:i') : '-',
                'location' => $location,
                'location_id' => $asset->location_id,
                'lot_id' => $lot?->id,
                'lot_number' => $lot?->number ?? '-',
                'lot_imageUrl' => $lot?->image_url,
                'organizer' => $lot?->organizer?->name ?? '-',
                'vendor' => $lot?->vendor_name ?? '-',
                'model_id' => $model?->id,
                'model_number' => $model?->number ?? '-',
                'model_name' => $model?->name ?? '-',
                'model_brand' => $brand,
                'model_specification' => $model?->specification ?? '-',
                'model_category' => $model?->type?->category?->name ?? '-',
                'model_type' => $model?->type?->name ?? '-',
                'model_uom' => $model?->uom?->name ?? '-',
                'barang_id' => $model?->id,
                'barang_code' => $model?->number ?? '-',
                'barang_nama' => $model?->name ?? '-',
                'barang_brand' => $brand,
                'barang_specification' => $model?->specification ?? '-',
                'barang_category' => $model?->type?->category?->name ?? '-',
                'barang_subcategory' => $model?->type?->name ?? '-',
                'barang_uom' => $model?->uom?->name ?? '-',
                'lifecycles' => $asset->lifecycles->map(function ($log) {
                    return [
                        'waktu' => $log->start_date ? $log->start_date->format('d-m-Y H:i:s') : '-',
                        'status' => $log->status,
                        'action_type' => $log->action_type,
                        'aktor' => ($log->action_type === 'Approval' && str_contains($log->note ?? '', 'BoD/BoC')) ? 'BoD/BoC' : ($log->actor->name ?? '-'),
                        'durasi' => $log->formatted_duration,
                        'catatan' => $log->note ?? '-',
                    ];
                })->toArray(),
            ] : null;

            $itemName = $model?->name ?? '-';
            $itemCode = $model?->number ?? '-';
            $assetCode = $asset?->number ?? '-';
            $lotCode = $lot?->number ?? '-';
            $categoryName = $model?->type?->category?->name ?? '-';
            $typeName = $model?->type?->name ?? '-';

            $item = [
                'id' => $f->id,
                'asset_id' => $f->asset_id,
                'unit_id' => $f->asset_id,
                'request_number' => $f->requestItem?->request?->request_number ?? '-',
                'item_name' => $itemName,
                'barang_nama' => $itemName,
                'model_name' => $itemName,
                'item_code' => $itemCode,
                'item_number' => $itemCode,
                'barang_code' => $itemCode,
                'model_number' => $itemCode,
                'asset_code' => $assetCode,
                'asset_number' => $assetCode,
                'unit_code' => $assetCode,
                'unit_number' => $assetCode,
                'lot_code' => $lotCode,
                'lot_number' => $lotCode,
                'brand' => $brand,
                'category' => $categoryName,
                'type' => $typeName,
                'subcategory' => $typeName,
                'condition' => $asset?->condition ?? '-',
                'location' => $location,
                'start_date' => Carbon::parse($startDate)->format('d-m-Y H:i'),
                'start_date_raw' => Carbon::parse($startDate)->toISOString(),
                'asset' => $assetData,
                'unit' => $assetData,
            ];

            if ($completedDate) {
                $item['completed_date'] = Carbon::parse($completedDate)->format('d-m-Y H:i');
                $item['return_date'] = Carbon::parse($completedDate)->format('d-m-Y H:i');
                $item['return_date_raw'] = Carbon::parse($completedDate)->toISOString();
                $history[] = $item;
            } else {
                $dueDate = $f->requestItem?->end_date;
                $item['due_date'] = $dueDate ? Carbon::parse($dueDate)->format('d-m-Y') : '-';
                $item['due_date_raw'] = $dueDate ? Carbon::parse($dueDate)->toISOString() : null;
                $active[] = $item;
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
