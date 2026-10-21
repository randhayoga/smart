<?php

namespace App\Http\Controllers\Smart\Admin\StockManagement;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Asset;
use App\Models\Master\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Asset Scan Controller providing mobile-optimized asset lookup and detail verification for scanned QR codes.
 */
class AssetScanController extends Controller
{
    /**
     * Look up an asset by raw/alphanumeric code or legacy number and return matching asset number & clean route key.
     */
    public function lookup(string $code): JsonResponse
    {
        $asset = (new Asset())->resolveRouteBinding($code);

        if (!$asset) {
            return response()->json([
                'found' => false,
            ], 404);
        }

        $cleanNumber = preg_replace('/[^a-zA-Z0-9]/', '', (string)$asset->number);

        return response()->json([
            'found' => true,
            'id' => $asset->id,
            'number' => $asset->number,
            'legacy_number' => $asset->legacy_number,
            'alphanumeric_code' => $cleanNumber,
            'url' => route('smart.scan', ['asset' => $cleanNumber]),
        ]);
    }

    /**
     * Show the standalone mobile scan page for the specified asset.
     */
    public function show(Asset $asset): Response
    {
        $asset->load([
            'location.parent.parent', 'statusApprovals',
            'lot.model.type.category', 'lot.model.brand', 'lot.model.uom',
            'lot.organizer', 'lot.vendor', 'lot.legacyVendor', 'legacyVendor'
        ]);

        $pendingApproval = $asset->statusApprovals->firstWhere('decision', 'pending');
        $approvedApproval = ($asset->hasStatus('Tidak Aktif') || $asset->hasStatus('Pending:BoD/BoC') || $asset->hasStatus('Dihapus'))
            ? $asset->statusApprovals->where('decision', 'approved')->sortByDesc('id')->first()
            : null;
        $model = $asset->lot->model ?? null;

        $mappedAsset = [
            'id' => $asset->id,
            'number' => $asset->number, // Nomor Aset
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
            'updated_at' => $asset->updated_at ? $asset->updated_at->format('d-m-Y H:i') : '-',
            
            // Asset-specific legacy vendor
            'asset_vendor' => $asset->legacyVendor?->name,
            'asset_vendor_id' => $asset->legacy_vendor_id,
            'unit_vendor' => $asset->legacyVendor?->name,
            'unit_vendor_id' => $asset->legacy_vendor_id,
            'legacy_vendor_id' => $asset->legacy_vendor_id,

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
            'lot_vendor_id' => $asset->lot->vendor_id ?? null,
            'lot_po_number' => $asset->lot->po_number ?? '-',
            'lot_purchase_grant_date' => ($asset->lot && $asset->lot->purchase_grant_date) ? $asset->lot->purchase_grant_date->format('Y-m-d') : null,
            'lot_age' => $asset->lot ? $asset->lot->age : null,

            // Parent model info
            'model_id' => $model->id ?? null,
            'model_number' => $model->number ?? '-',
            'model_code' => $model->number ?? '-',
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
        ];

        $locations = Location::with('parent')->active()->orderBy('name')->get();

        return Inertia::render('Smart/MultiRoles/ScanResult', [
            'asset' => $mappedAsset,
            'locations' => $locations,
        ]);
    }
}
