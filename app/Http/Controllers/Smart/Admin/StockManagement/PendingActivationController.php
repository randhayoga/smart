<?php

namespace App\Http\Controllers\Smart\Admin\StockManagement;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Asset;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Vendor;
use App\Models\TbProject;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pending Activation Controller listing asset units pending manager activation approval.
 */
class PendingActivationController extends Controller
{
    /**
     * Display a listing of pending activation units.
     */
    public function index(Request $request): Response
    {
        $assets = Asset::with([
            'location.parent', 'activationApprovals',
            'lot.model.type.category', 'lot.model.brand',
            'lot.organizer', 'lot.vendor', 'lot.legacyVendor', 'lifecycles.actor'
        ])
        ->where(function ($q) {
            $q->where('status', 'Belum Diverifikasi')
              ->orWhereHas('activationApprovals', fn($aq) => $aq->where('decision', 'pending'));
        })
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($asset) {
            $model = $asset->lot->model ?? null;
            return [
                'id' => $asset->id,
                'number' => $asset->number,
                'legacy_number' => $asset->legacy_number,
                'status' => $asset->status,
                'condition' => $asset->condition,
                'type' => $asset->borrowing_type,
                'borrowing_type' => $asset->borrowing_type,
                'classification' => $asset->classification,
                'price' => $asset->price,
                'image_url' => $asset->image_url,
                'vehicle_registration' => $asset->vehicle_registration,
                'created_at' => $asset->created_at?->toIso8601String(),
                'updated_at' => $asset->updated_at ? $asset->updated_at->format('d-m-Y H:i') : '-',
                
                // Location info
                'location' => $asset->location ? $asset->location->full_name : '-',
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
                'model_type' => $model->type->name ?? '-',
                'model_uom' => $model->uom->name ?? '-',

                // Backwards compat with legacy keys
                'barang_id' => $model->id ?? null,
                'barang_code' => $model->number ?? '-',
                'barang_nama' => $model->name ?? '-',
                'barang_brand' => $model->brand->name ?? '-',
                'barang_specification' => $model->specification ?? '-',
                'barang_category' => $model->type->category->name ?? '-',
                'barang_subcategory' => $model->type->name ?? '-',
                'barang_uom' => $model->uom->name ?? '-',

                // Audit trails (lifecycles)
                'lifecycles' => $asset->lifecycles->map(function ($log) {
                    return [
                        'waktu' => $log->start_date ? $log->start_date->format('d-m-Y H:i:s') : '-',
                        'status' => $log->status,
                        'action_type' => $log->action_type,
                        'aktor' => $log->actor->name ?? 'System',
                        'durasi' => $log->formatted_duration,
                        'catatan' => $log->note ?? '-',
                    ];
                })->toArray(),
            ];
        });

        $locations = Location::with('parent')->active()->orderBy('name')->get();
        $organizers = Organizer::orderBy('name')->get();
        $vendors = Vendor::orderBy('name')->get();
        $projects = TbProject::validForSelection()->orderBy('project_name')->get();

        return Inertia::render('Smart/Admin/StockManagement/PendingActivationList', [
            'user' => $request->user(),
            'assets' => $assets,
            'units' => $assets,
            'locations' => $locations,
            'organizers' => $organizers,
            'vendors' => $vendors,
            'projects' => $projects,
        ]);
    }
}
