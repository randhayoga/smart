<?php

namespace App\Actions\Request;

use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetLifecycle;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestFulfillment;
use App\Models\Request\RequestItem;
use App\Models\Request\RequestStatusLog;
use App\Services\ImageUploadService;
use App\Services\InventoryLogService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Action handling atomic multi-item manual consumable requests, short-term (ST) borrowings,
 * and long-term (LT) assignments with immediate stock/lifecycle logging.
 */
class ProcessManualRequest
{
    public function __construct(
        protected ImageUploadService $imageUploadService,
        protected InventoryLogService $inventoryLogService,
        protected NotificationService $notificationService
    ) {
    }

    /**
     * Execute the manual borrowing or request transaction atomically.
     *
     * @param array<string, mixed> $data Validated request payload
     * @param UploadedFile $formFile Uploaded physical/signed request form
     * @param int $adminUserId Authenticated admin user ID
     * @return SmartRequest
     * @throws ValidationException
     */
    public function execute(array $data, UploadedFile $formFile, int $adminUserId): SmartRequest
    {
        $requestType = strtolower((string) $data['request_type']);
        $utilization = (string) $data['utilization'];
        $orgId = $utilization === 'corporate' ? ($data['org_id'] ?? null) : null;
        $projectId = $utilization === 'project' ? ($data['project_id'] ?? null) : null;
        $userId = (int) $data['user_id'];
        $approverId = (int) $data['approver_id'];
        $requestReasoning = !empty($data['reasoning']) ? trim((string) $data['reasoning']) : null;
        $itemsPayload = $data['items'] ?? [];

        return DB::transaction(function () use (
            $requestType,
            $utilization,
            $orgId,
            $projectId,
            $userId,
            $approverId,
            $requestReasoning,
            $itemsPayload,
            $data,
            $formFile,
            $adminUserId
        ) {
            $formUrl = $this->imageUploadService->storeImageOrFile($formFile, 'request_forms');
            $requestNumber = $this->generateRequestNumber();

            if ($requestType === 'consumable') {
                return $this->executeConsumableRequest(
                    $requestNumber,
                    $userId,
                    $approverId,
                    $utilization,
                    $orgId,
                    $projectId,
                    $requestReasoning,
                    $formUrl,
                    Carbon::parse($data['date'])->startOfDay(),
                    $itemsPayload,
                    $adminUserId
                );
            }

            return $this->executeAssetBorrowOrAssignment(
                $requestType,
                $requestNumber,
                $userId,
                $approverId,
                $utilization,
                $orgId,
                $projectId,
                $requestReasoning,
                $formUrl,
                Carbon::parse($data['start_date'])->startOfDay(),
                in_array($requestType, ['st', 'rent'], true) && !empty($data['end_date']) ? Carbon::parse($data['end_date'])->setTime(23, 59, 59) : null,
                $itemsPayload,
                $adminUserId
            );
        });
    }

    /**
     * Process a multi-model consumable manual request with FIFO lot stock deduction.
     */
    private function executeConsumableRequest(
        string $requestNumber,
        int $userId,
        int $approverId,
        string $utilization,
        ?int $orgId,
        ?int $projectId,
        ?string $requestReasoning,
        string $formUrl,
        Carbon $date,
        array $itemsPayload,
        int $adminUserId
    ): SmartRequest {
        $modelIds = array_values(array_unique(array_map(fn($i) => (int) $i['model_id'], $itemsPayload)));

        $models = InventoryModel::with('type')
            ->whereIn('id', $modelIds)
            ->get()
            ->keyBy('id');

        // Lock all active lots for the requested models in FIFO order
        $lockedLots = Lot::whereIn('model_id', $modelIds)
            ->where('current_quantity', '>', 0)
            ->orderBy('purchase_grant_date', 'asc')
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get()
            ->groupBy('model_id');

        foreach ($itemsPayload as $index => $itemInput) {
            $modelId = (int) $itemInput['model_id'];
            $qtyRequested = (int) $itemInput['quantity'];
            $model = $models->get($modelId);

            if (!$model || !$model->type || !$model->type->is_consumable) {
                throw ValidationException::withMessages([
                    "items.{$index}.model_id" => ['Model yang dipilih bukan barang habis pakai (consumable).'],
                ]);
            }

            $modelLots = $lockedLots->get($modelId, collect());
            $totalAvailable = (int) $modelLots->sum('current_quantity');

            if ($totalAvailable < $qtyRequested) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => [
                        "Stok tersedia untuk {$model->name} ({$totalAvailable}) tidak mencukupi jumlah permintaan ({$qtyRequested}).",
                    ],
                ]);
            }
        }

        $smartRequest = SmartRequest::create([
            'request_number' => $requestNumber,
            'user_id' => $userId,
            'approver_id' => $approverId,
            'utilization' => $utilization,
            'org_id' => $orgId,
            'project_id' => $projectId,
            'reasoning' => $requestReasoning,
            'form_url' => $formUrl,
            'status' => 'success',
        ]);

        $destinationName = null;
        if ($utilization === 'corporate' && $orgId !== null) {
            $destinationName = \App\Models\HrdOrgchart::where('id', $orgId)->value('org_name');
        } elseif ($utilization === 'project' && $projectId !== null) {
            $destinationName = \App\Models\TbProject::where('id_project', $projectId)->value('project_name');
        }

        $affectedModels = [];

        foreach ($itemsPayload as $itemInput) {
            $modelId = (int) $itemInput['model_id'];
            $qtyRequested = (int) $itemInput['quantity'];
            $itemReasoning = !empty($itemInput['reasoning']) ? trim((string) $itemInput['reasoning']) : null;
            $model = $models->get($modelId);

            $totalBefore = (int) Lot::where('model_id', $modelId)->sum('current_quantity');

            $requestItem = RequestItem::create([
                'request_id' => $smartRequest->id,
                'type_id' => $model->type_id,
                'model_id' => $model->id,
                'quantity_requested' => $qtyRequested,
                'start_date' => null,
                'end_date' => null,
                'reasoning' => $itemReasoning,
                'status' => 'fulfilled',
            ]);

            $remainingToDeduct = $qtyRequested;
            $modelLots = $lockedLots->get($modelId, collect());

            foreach ($modelLots as $lot) {
                if ($remainingToDeduct <= 0) {
                    break;
                }

                $availableInLot = (int) $lot->current_quantity;
                if ($availableInLot <= 0) {
                    continue;
                }

                $takeQty = min($remainingToDeduct, $availableInLot);
                $newLotBalance = $availableInLot - $takeQty;

                $lot->current_quantity = $newLotBalance;
                $lot->save();

                $this->clampCompetingLotFulfillments($lot->id, $newLotBalance);

                RequestFulfillment::create([
                    'request_item_id' => $requestItem->id,
                    'lot_id' => $lot->id,
                    'asset_id' => null,
                    'quantity_fulfilled' => $takeQty,
                    'assigned_at' => $date,
                    'confirmed_at' => $date,
                    'completed_at' => $date,
                ]);

                $this->inventoryLogService->logConsumableStockOut(
                    $model,
                    $lot,
                    $takeQty,
                    $availableInLot,
                    $newLotBalance,
                    $userId,
                    $utilization,
                    $destinationName,
                    $itemReasoning ?? $requestReasoning,
                    $date
                );

                $remainingToDeduct -= $takeQty;
            }

            $affectedModels[] = [
                'model' => $model,
                'total_before' => $totalBefore,
            ];
        }

        $adminUser = \App\Models\User::find($adminUserId);
        $adminName = $adminUser?->employee_name ?? $adminUser?->name ?? 'Admin';

        \App\Models\Request\RequestAdminConfirmation::create([
            'request_id' => $smartRequest->id,
            'admin_id' => $adminUserId,
            'action' => 'confirm',
            'note' => $requestReasoning ?? 'Permintaan manual habis pakai dikonfirmasi oleh Admin',
            'decided_at' => now(),
        ]);

        RequestStatusLog::create([
            'request_id' => $smartRequest->id,
            'status_from' => 'draft',
            'status_to' => 'success',
            'changed_by' => $adminUserId,
            'note' => $requestReasoning ?? 'Permintaan manual habis pakai dikonfirmasi oleh Admin',
            'created_at' => now(),
        ]);

        foreach ($affectedModels as $entry) {
            /** @var InventoryModel $m */
            $m = $entry['model'];
            $threshold = (int) ($m->min_stock_threshold ?? 0);
            if ($threshold > 0 && $entry['total_before'] >= $threshold) {
                $this->notificationService->checkAndNotifyLowStock($m);
            }
        }

        return $smartRequest;
    }

    /**
     * Process a multi-asset short-term borrowing (ST), long-term assignment (LT), or borrowing rent (Rent).
     */
    private function executeAssetBorrowOrAssignment(
        string $requestType,
        string $requestNumber,
        int $userId,
        int $approverId,
        string $utilization,
        ?int $orgId,
        ?int $projectId,
        ?string $requestReasoning,
        string $formUrl,
        Carbon $startDate,
        ?Carbon $endDate,
        array $itemsPayload,
        int $adminUserId
    ): SmartRequest {
        $expectedBorrowingType = $requestType === 'rent' ? null : strtoupper($requestType); // 'ST', 'LT', or null for Rent (both ST/LT)
        $newAssetStatus = match ($requestType) {
            'rent' => 'Dipinjam (sewa)',
            'st' => 'Dipinjam (ST)',
            default => 'Di-assign (LT)',
        };
        $lifecycleActionType = in_array($requestType, ['st', 'rent'], true) ? 'Peminjaman' : 'Assignment';

        $assetIds = array_values(array_unique(array_map(fn($i) => (int) $i['asset_id'], $itemsPayload)));

        $lockedAssets = Asset::with('lot.model.type')
            ->whereIn('id', $assetIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($itemsPayload as $index => $itemInput) {
            $assetId = (int) $itemInput['asset_id'];
            /** @var Asset|null $asset */
            $asset = $lockedAssets->get($assetId);

            if (!$asset) {
                throw ValidationException::withMessages([
                    "items.{$index}.asset_id" => ['Aset tidak ditemukan.'],
                ]);
            }

            if ($asset->status !== 'Tersedia' || $asset->approval_status !== null) {
                throw ValidationException::withMessages([
                    "items.{$index}.asset_id" => [
                        "Aset {$asset->number} tidak tersedia atau sedang menunggu persetujuan.",
                    ],
                ]);
            }

            if ($expectedBorrowingType !== null && strtoupper((string) $asset->borrowing_type) !== $expectedBorrowingType) {
                throw ValidationException::withMessages([
                    "items.{$index}.asset_id" => [
                        "Aset {$asset->number} memiliki tipe peminjaman {$asset->borrowing_type} (bukan {$expectedBorrowingType}).",
                    ],
                ]);
            }
        }

        // Evict any competing unconfirmed fulfillments referencing these assets
        RequestFulfillment::whereIn('asset_id', $assetIds)
            ->whereNull('confirmed_at')
            ->whereNull('completed_at')
            ->delete();

        $smartRequest = SmartRequest::create([
            'request_number' => $requestNumber,
            'user_id' => $userId,
            'approver_id' => $approverId,
            'utilization' => $utilization,
            'org_id' => $orgId,
            'project_id' => $projectId,
            'reasoning' => $requestReasoning,
            'form_url' => $formUrl,
            'status' => 'borrow',
        ]);

        foreach ($itemsPayload as $itemInput) {
            $assetId = (int) $itemInput['asset_id'];
            $itemReasoning = !empty($itemInput['reasoning']) ? trim((string) $itemInput['reasoning']) : null;
            /** @var Asset $asset */
            $asset = $lockedAssets->get($assetId);
            $model = $asset->lot?->model;

            $requestItem = RequestItem::create([
                'request_id' => $smartRequest->id,
                'type_id' => $model?->type_id,
                'model_id' => $model?->id,
                'quantity_requested' => 1,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'reasoning' => $itemReasoning,
                'status' => 'fulfilled',
            ]);

            RequestFulfillment::create([
                'request_item_id' => $requestItem->id,
                'asset_id' => $asset->id,
                'lot_id' => null,
                'quantity_fulfilled' => 1,
                'assigned_at' => $startDate,
                'confirmed_at' => $startDate,
                'completed_at' => null,
            ]);

            // Close open lifecycle entries for this asset and insert immediate Peminjaman / Assignment lifecycle
            AssetLifecycle::where('asset_id', $asset->id)
                ->whereNull('end_date')
                ->update(['end_date' => $startDate]);

            AssetLifecycle::create([
                'asset_id' => $asset->id,
                'action_type' => $lifecycleActionType,
                'status' => $newAssetStatus,
                'condition' => $asset->condition ?? 'Bagus',
                'location_id' => $asset->location_id,
                'start_date' => $startDate,
                'end_date' => null,
                'actor_id' => $userId,
                'note' => $itemReasoning ?? $requestReasoning ?? "{$lifecycleActionType} manual [{$requestNumber}]",
                'previous_state' => ['status' => 'Tersedia'],
                'new_state' => [
                    'status' => $newAssetStatus,
                    'borrower_id' => $userId,
                    'request_number' => $requestNumber,
                ],
            ]);

            Asset::withoutEvents(function () use ($asset, $newAssetStatus) {
                $asset->update(['status' => $newAssetStatus]);
            });
        }

        $defaultNote = match ($requestType) {
            'rent' => 'Peminjaman (sewa) manual dikonfirmasi oleh Admin',
            'st' => 'Peminjaman manual (ST) dikonfirmasi oleh Admin',
            default => 'Assignment manual (LT) dikonfirmasi oleh Admin',
        };

        \App\Models\Request\RequestAdminConfirmation::create([
            'request_id' => $smartRequest->id,
            'admin_id' => $adminUserId,
            'action' => 'confirm',
            'note' => $requestReasoning ?? $defaultNote,
            'decided_at' => now(),
        ]);

        RequestStatusLog::create([
            'request_id' => $smartRequest->id,
            'status_from' => 'draft',
            'status_to' => 'borrow',
            'changed_by' => $adminUserId,
            'note' => $requestReasoning ?? $defaultNote,
            'created_at' => now(),
        ]);

        return $smartRequest;
    }

    /**
     * Clamp or remove competing unconfirmed lot fulfillments when remaining stock drops.
     */
    private function clampCompetingLotFulfillments(int $lotId, int $newBalance): void
    {
        $unconfirmed = RequestFulfillment::where('lot_id', $lotId)
            ->whereNull('asset_id')
            ->whereNull('confirmed_at')
            ->whereNull('completed_at')
            ->get();

        foreach ($unconfirmed as $competing) {
            if ($newBalance <= 0) {
                $competing->delete();
            } elseif ($competing->quantity_fulfilled > $newBalance) {
                $competing->update(['quantity_fulfilled' => $newBalance]);
            }
        }
    }

    /**
     * Generate a unique MMYYYY-XXXX request number under row lock.
     */
    private function generateRequestNumber(): string
    {
        $prefix = Carbon::now()->format('mY') . '-';

        $latestRequest = SmartRequest::where('request_number', 'like', $prefix . '%')
            ->orderByDesc('request_number')
            ->lockForUpdate()
            ->first();

        $nextNumber = 1;
        if ($latestRequest) {
            $lastSequence = (int) substr($latestRequest->request_number, -4);
            $nextNumber = $lastSequence + 1;
        }

        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
