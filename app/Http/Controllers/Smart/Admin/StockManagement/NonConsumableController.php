<?php

namespace App\Http\Controllers\Smart\Admin\StockManagement;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Asset;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Vendor;
use App\Models\TbProject;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * NonConsumable Controller rendering dedicated physical asset units listing (Daftar Tidak Habis Pakai).
 */
class NonConsumableController extends Controller
{
    /**
     * Display the non-consumable asset inventory listing page (Daftar Tidak Habis Pakai).
     */
    public function index(Request $request): Response
    {
        $assets = Asset::with([
            'location.parent', 'statusApprovals', 'legacyProject',
            'lot.model.type.category', 'lot.model.brand',
            'lot.organizer', 'lot.vendor', 'lot.legacyVendor', 'legacyVendor', 'lifecycles.actor',
            'fulfillments' => fn($q) => $q->whereNull('completed_at')->with('requestItem.request.user')
        ])
        ->where('status', 'not like', 'Pending%')
        ->where('status', '!=', 'Belum Diverifikasi')
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($asset) {
            $pendingApproval = $asset->statusApprovals->firstWhere('decision', 'pending');
            $approvedApproval = $asset->status === 'Tidak Aktif' 
                ? $asset->statusApprovals->where('decision', 'approved')->sortByDesc('updated_at')->first() 
                : null;
            $model = $asset->lot->model ?? null;
            return [
                'id' => $asset->id,
                'number' => $asset->number, // Asset number
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
                'condition' => $asset->condition,
                'type' => $asset->borrowing_type,
                'borrowing_type' => $asset->borrowing_type,
                'classification' => $asset->classification,
                'comp_specification' => $asset->comp_specification,
                'specification' => $asset->comp_specification,
                'price' => $asset->price,
                'image_url' => $asset->image_url,
                'vehicle_registration' => $asset->vehicle_registration,
                'legacy_burden' => $asset->legacy_burden,
                'burden' => $asset->legacy_burden,
                'legacy_project_id' => $asset->legacy_project_id,
                'project_id' => $asset->legacy_project_id,
                'project_name' => $asset->legacyProject ? $asset->legacyProject->project_name : null,
                'project_no' => $asset->legacyProject ? $asset->legacyProject->no_project : null,
                'created_at' => $asset->created_at?->toIso8601String(),
                'updated_at' => $asset->updated_at ? $asset->updated_at->format('d-m-Y H:i') : '-',
                
                // Asset-specific legacy vendor
                'asset_vendor' => $asset->legacyVendor?->name,
                'asset_vendor_id' => $asset->legacy_vendor_id,
                'unit_vendor' => $asset->legacyVendor?->name,
                'unit_vendor_id' => $asset->legacy_vendor_id,
                'legacy_vendor_id' => $asset->legacy_vendor_id,

                // Location info
                'location' => $asset->location?->full_name ?? '-',
                'location_id' => $asset->location_id,

                // Parent lot info
                'lot_id' => $asset->lot_id,
                'lot_number' => $asset->lot->number ?? '-',
                'lot_imageUrl' => $asset->lot->image_url ?? null,
                'lot_unitPrice' => $asset->lot->unit_price ?? null,
                'organizer' => $asset->lot->organizer->name ?? '-',
                'organizer_id' => $asset->lot->organizer_id ?? null,
                'vendor' => $asset->lot?->vendor_name ?? '-',
                'vendor_id' => $asset->lot->vendor_id ?? null,
                'legacy_vendor_id' => $asset->lot->legacy_vendor_id ?? null,
                'lot_organizer' => $asset->lot->organizer->name ?? '-',
                'lot_vendor' => $asset->lot?->vendor_name ?? '-',
                'lot_vendor_id' => $asset->lot->vendor_id ?? null,
                'lot_po_number' => $asset->lot->po_number ?? '-',
                'lot_purchase_grant_date' => ($asset->lot && $asset->lot->purchase_grant_date) ? $asset->lot->purchase_grant_date->format('Y-m-d') : null,

                // Parent model info
                'model_id' => $model->id ?? null,
                'model_code' => $model->number ?? '-',
                'model_number' => $model->number ?? '-',
                'model_name' => $model->name ?? '-',
                'model_brand' => $model->brand->name ?? '-',
                'model_specification' => $model->specification ?? '-',
                'model_category' => $model->type->category->name ?? '-',
                'model_category_code' => $model->type->category->code ?? null,
                'model_type' => $model->type->name ?? '-',
                'type_name' => $model->type->name ?? '-',
                'model_uom' => $model->uom->name ?? '-',

                // Backwards compat with legacy keys
                'barang_id' => $model->id ?? null,
                'barang_code' => $model->number ?? '-',
                'barang_nama' => $model->name ?? '-',
                'barang_brand' => $model->brand->name ?? '-',
                'barang_specification' => $model->specification ?? '-',
                'barang_category' => $model->type->category->name ?? '-',
                'barang_category_code' => $model->type->category->code ?? null,
                'barang_subcategory' => $model->type->name ?? '-',
                'barang_uom' => $model->uom->name ?? '-',

                // Active borrowing info
                'active_borrowing' => $asset->active_borrowing,

                // Audit trails (lifecycles)
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
            ];
        });

        $locations = Location::with('parent')->orderBy('name')->get();
        $organizers = Organizer::orderBy('name')->get();
        $vendors = Vendor::orderBy('name')->get();
        $projects = TbProject::validForSelection()->orderBy('project_name')->get();
        $users = User::select('id', 'employee_name', 'employee_id')
            ->where('active', 1)
            ->orderBy('employee_name')
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'name' => "{$u->employee_name} ({$u->employee_id})",
                'employee_id' => $u->employee_id,
            ]);

        return Inertia::render('Smart/Admin/StockManagement/NonConsumableList', [
            'user' => $request->user(),
            'assets' => $assets,
            'units' => $assets,
            'locations' => $locations,
            'organizers' => $organizers,
            'vendors' => $vendors,
            'projects' => $projects,
            'users' => $users,
        ]);
    }
}
