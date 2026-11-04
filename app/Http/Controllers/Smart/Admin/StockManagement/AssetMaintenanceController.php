<?php

namespace App\Http\Controllers\Smart\Admin\StockManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Smart\StoreAssetMaintenanceRequest;
use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetLifecycle;
use App\Models\Inventory\AssetMaintenance;
use App\Models\User;
use App\Services\ImageUploadService;
use App\Services\Inventory\StockInventoryQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Controller managing Asset Maintenance history, input submissions,
 * condition updates, and lifecycle logging.
 */
class AssetMaintenanceController extends Controller
{
    public function __construct(
        protected ImageUploadService $imageUploadService,
        protected StockInventoryQueryService $queryService
    ) {}

    /**
     * Get maintenance history for a specific asset and available user options.
     */
    public function index(Asset $asset): JsonResponse
    {
        $maintenances = AssetMaintenance::query()
            ->with([
                'maintainer:id,employee_name,employee_id',
                'user:id,employee_name,employee_id',
            ])
            ->where('asset_id', $asset->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'asset_id' => $item->asset_id,
                    'maintainer_id' => $item->maintainer_id,
                    'maintainer_name' => $item->maintainer?->name ?? '-',
                    'maintainer_employee_id' => $item->maintainer?->employee_id ?? '-',
                    'user_id' => $item->user_id,
                    'user_name' => $item->user?->name ?? '-',
                    'user_employee_id' => $item->user?->employee_id ?? '-',
                    'type' => $item->type,
                    'source' => $item->source,
                    'reference' => $item->reference,
                    'problem' => $item->problem,
                    'solution' => $item->solution,
                    'cost' => (float) $item->cost,
                    'date' => $item->date ? $item->date->format('Y-m-d') : null,
                    'formatted_date' => $item->date ? $item->date->format('d/m/Y') : '-',
                    'image_url' => $item->image_url,
                    'created_at' => $item->created_at ? $item->created_at->format('d-m-Y H:i') : null,
                ];
            });

        return response()->json([
            'maintenances' => $maintenances,
            'users' => $this->queryService->getActiveUsersForSelection(),
        ]);
    }

    /**
     * Store a new maintenance entry for an asset.
     */
    public function store(StoreAssetMaintenanceRequest $request, Asset $asset): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();
        $actor = Auth::user();
        $targetUser = User::find($validated['user_id']);

        $proofUrl = null;
        if ($request->hasFile('proof_file')) {
            $proofUrl = $this->imageUploadService->storeImageOrFile(
                $request->file('proof_file'),
                'inventory/maintenance'
            );
        }

        $newMaintenance = DB::transaction(function () use ($validated, $asset, $actor, $targetUser, $proofUrl) {
            $costFormatted = number_format((float) $validated['cost'], 0, ',', '.');
            $targetUserName = $targetUser?->name ?? 'User';

            // 1. Create Maintenance record
            $maintenance = AssetMaintenance::create([
                'asset_id' => $asset->id,
                'maintainer_id' => $actor->id,
                'user_id' => $validated['user_id'],
                'type' => $validated['type'],
                'source' => $validated['source'],
                'reference' => $validated['source'] === 'TiSu' ? ($validated['reference'] ?? null) : null,
                'problem' => $validated['problem'],
                'solution' => $validated['solution'],
                'cost' => $validated['cost'],
                'date' => $validated['date'],
                'image_url' => $proofUrl,
            ]);

            // 2. Handle Condition Change:
            // "if the condition after maintenance is the same as the condition before it
            // make sure nothing is done (including no logging of change of condition)."
            $conditionInput = $validated['condition'];
            $previousCondition = $asset->condition;
            $isConditionChanged = ($conditionInput !== 'Tidak Berubah' && $conditionInput !== $previousCondition);

            if ($isConditionChanged) {
                // Update asset condition (observer will automatically create condition change lifecycle)
                $asset->condition = $conditionInput;
                $asset->save();
            }

            // 3. Log the Maintenance event in AssetLifecycle
            // Informative Indonesian summary
            $sourceRef = $validated['source'] === 'TiSu' && !empty($validated['reference'])
                ? "{$validated['source']} (Ref: {$validated['reference']})"
                : $validated['source'];

            $logNote = "Perawatan {$validated['type']} [{$sourceRef}]. Masalah: {$validated['problem']}. Solusi: {$validated['solution']}. Biaya: Rp {$costFormatted}. User: {$targetUserName}.";

            AssetLifecycle::where('asset_id', $asset->id)
                ->whereNull('end_date')
                ->update(['end_date' => now()]);

            AssetLifecycle::create([
                'asset_id' => $asset->id,
                'action_type' => 'Pemeliharaan',
                'status' => $asset->status,
                'condition' => $asset->condition,
                'location_id' => $asset->location_id,
                'start_date' => now(),
                'end_date' => null,
                'actor_id' => $actor->id,
                'note' => $logNote,
                'previous_state' => [
                    'maintenance_id' => $maintenance->id,
                    'type' => $validated['type'],
                    'cost' => $validated['cost'],
                    'proof_url' => $maintenance->image_url,
                ],
                'new_state' => [
                    'condition' => $asset->condition,
                ],
            ]);

            return $maintenance;
        });

        // Load relations for response
        $newMaintenance->load(['maintainer:id,employee_name,employee_id', 'user:id,employee_name,employee_id']);

        if (! $request->wantsJson()) {
            return back()->with('success', 'Riwayat perawatan berhasil disimpan.');
        }

        return response()->json([
            'message' => 'Riwayat perawatan berhasil disimpan.',
            'maintenance' => [
                'id' => $newMaintenance->id,
                'asset_id' => $newMaintenance->asset_id,
                'maintainer_id' => $newMaintenance->maintainer_id,
                'maintainer_name' => $newMaintenance->maintainer?->name ?? '-',
                'user_id' => $newMaintenance->user_id,
                'user_name' => $newMaintenance->user?->name ?? '-',
                'type' => $newMaintenance->type,
                'source' => $newMaintenance->source,
                'reference' => $newMaintenance->reference,
                'problem' => $newMaintenance->problem,
                'solution' => $newMaintenance->solution,
                'cost' => (float) $newMaintenance->cost,
                'date' => $newMaintenance->date ? $newMaintenance->date->format('Y-m-d') : null,
                'formatted_date' => $newMaintenance->date ? $newMaintenance->date->format('d/m/Y') : '-',
                'image_url' => $newMaintenance->image_url,
                'created_at' => $newMaintenance->created_at ? $newMaintenance->created_at->format('d-m-Y H:i') : null,
            ],
            'asset_condition' => $asset->fresh()->condition,
        ], 201);
    }
}
