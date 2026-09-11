<?php

namespace App\Http\Resources;

use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestFulfillment;
use App\Models\Request\RequestItem;
use App\Services\RequestFulfillmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource to transform a Request model for the Admin Request Fulfillment show page.
 *
 * @mixin SmartRequest
 */
class SmartFulfillmentResource extends JsonResource
{
    protected const STATUS_MAP = [
        'wait' => 'Menunggu approval',
        'approve' => 'Di-approve',
        'confirm' => 'Dikonfirmasi Admin',
        'menunggu_serah_terima' => 'Menunggu Serah Terima',
        'handover' => 'Serah Terima',
        'borrow' => 'Dipinjam',
        'return' => 'Dipinjam',
        'success' => 'Selesai',
        'reject' => 'Ditolak',
        'cancel' => 'Dibatalkan',
        'pending' => 'Pending',
        'partial' => 'Parsial',
    ];

    public function toArray(Request $request): array
    {
        $startDate = $this->start_date instanceof \Carbon\CarbonInterface 
            ? $this->start_date 
            : ($this->start_date ? \Carbon\Carbon::parse($this->start_date) : null);
        $endDate = $this->end_date instanceof \Carbon\CarbonInterface 
            ? $this->end_date 
            : ($this->end_date ? \Carbon\Carbon::parse($this->end_date) : null);

        $durationDays = 0;
        $durationHours = 0;
        if ($startDate && $endDate) {
            $diff = $startDate->diff($endDate);
            $durationDays = $diff->days;
            $durationHours = $diff->h;
        }

        $pemanfaatanDetail = '-';
        if ($this->utilization === 'corporate') {
            $pemanfaatanDetail = $this->department?->org_name ?? $this->department?->name ?? '-';
        } else {
            if ($this->project) {
                $pemanfaatanDetail = $this->project->no_project 
                    ? "{$this->project->no_project} ({$this->project->project_name})" 
                    : ($this->project->project_name ?? '-');
            }
        }

        $borrowPeriod = null;
        if ($startDate) {
            $borrowPeriod = $endDate 
                ? "{$startDate->format('d-m-Y H:i')} s.d. {$endDate->format('d-m-Y H:i')}"
                : "{$startDate->format('d-m-Y H:i')} s.d. - (Tanpa Tenggat Waktu)";
        }

        $fulfillmentService = app(RequestFulfillmentService::class);

        $totalRequested = 0;
        $totalAssigned = 0;
        $allAssigned = true;

        $itemsTransformed = $this->items->map(function (RequestItem $item) use ($fulfillmentService, &$totalRequested, &$totalAssigned, &$allAssigned) {
            $requested = (int) $item->quantity_requested;
            $totalRequested += $requested;

            $isConsumable = (bool) (
                $item->barang?->subcategory?->category?->is_consumable 
                ?? $item->subcategory?->category?->is_consumable 
                ?? false
            );

            $subcatName = $item->barang?->subcategory?->name ?? $item->subcategory?->name ?? '-';
            $catName = $item->barang?->subcategory?->category?->name ?? $item->subcategory?->category?->name ?? '-';
            $imageUrl = $item->barang?->image_url
                ? '/media/' . $item->barang->image_url
                : (($firstBarang = $item->subcategory?->barangs?->first()) && $firstBarang->image_url ? '/media/' . $firstBarang->image_url : null);
            $uomName = $item->barang?->uom?->name 
                ?? $item->subcategory?->barangs?->first()?->uom?->name 
                ?? 'satuan';

            $itemData = [
                'id' => $item->id,
                'barang_id' => $item->barang_id,
                'subcategory_id' => $item->subcategory_id,
                'category' => $catName,
                'subcategory' => $subcatName,
                'brand' => $item->barang?->brand?->name ?? '-',
                'name' => $item->barang?->name ?? 'Tidak Spesifik',
                'spec' => $item->barang?->specification ?? '',
                'quantity' => $requested,
                'quantity_requested' => $requested,
                'is_consumable' => $isConsumable,
                'imageUrl' => $imageUrl,
                'uom' => $uomName,
                'status' => $item->status,
            ];

            if ($isConsumable) {
                // Consumable item: LOT breakdown
                $lotFulfillments = $item->fulfillments
                    ->whereNotNull('lot_id')
                    ->whereNull('unit_id')
                    ->map(function ($f) {
                        $loc = $f->lot?->location?->full_name ?? '-';

                        return [
                            'fulfillment_id' => $f->id,
                            'lot_id' => $f->lot_id,
                            'lot_number' => $f->lot?->number ?? '-',
                            'brand_name' => $f->lot?->barang?->brand?->name ?? '-',
                            'item_name' => $f->lot?->barang?->name ?? '-',
                            'specification' => $f->lot?->barang?->specification ?? '',
                            'quantity_fulfilled' => (int) $f->quantity_fulfilled,
                            'storage_location' => $loc,
                            'date_of_receipt' => $f->lot?->date_of_receipt ? $f->lot->date_of_receipt->format('d-m-Y') : '-',
                        ];
                    })->values()->toArray();

                $sumFulfilled = array_sum(array_column($lotFulfillments, 'quantity_fulfilled'));
                $totalAssigned += $sumFulfilled;
                if ($sumFulfilled < $requested) {
                    $allAssigned = false;
                }

                $existingLotMap = $item->fulfillments
                    ->whereNotNull('lot_id')
                    ->whereNull('unit_id')
                    ->pluck('quantity_fulfilled', 'lot_id')
                    ->toArray();

                $availableLots = $fulfillmentService->getAvailableLotsQuery($item)
                    ->get()
                    ->map(function ($l) use ($existingLotMap) {
                        $loc = $l->location?->full_name ?? '-';

                        $brand = $l->barang?->brand?->name ?? '';
                        $name = $l->barang?->name ?? '';
                        $spec = $l->barang?->specification ?? '';
                        $variantParts = array_filter([$brand, $name, $spec], fn($v) => !empty($v) && $v !== '-');
                        $variant = !empty($variantParts) ? implode(' ', $variantParts) : ($l->barang?->name ?? '-');

                        $assignedQty = (int) ($existingLotMap[$l->id] ?? 0);
                        $totalAvailable = (int) $l->current_quantity;

                        return [
                            'id' => $l->id,
                            'lot_code' => $l->number ?? '-',
                            'brand' => $brand,
                            'name' => $name,
                            'spec' => $spec,
                            'variant' => $variant,
                            'storage_location' => $loc,
                            'current_quantity' => $totalAvailable,
                            'allocated_quantity' => $assignedQty,
                            'uom' => $l->barang?->uom?->name ?? 'satuan',
                            'date_of_receipt' => $l->date_of_receipt ? $l->date_of_receipt->format('d-m-Y') : '-',
                        ];
                    })->values()->toArray();

                $itemData['lot_fulfillments'] = $lotFulfillments;
                $itemData['available_lots'] = $availableLots;
                $itemData['consumable_summary'] = [
                    'quantity_requested' => $requested,
                    'quantity_fulfilled' => $sumFulfilled,
                    'is_fully_fulfilled' => $sumFulfilled >= $requested,
                ];
            } else {
                // Non-consumable item: Serialized Unit slots & modal datatable
                $fulfillments = $item->fulfillments
                    ->whereNotNull('unit_id')
                    ->sortBy('id')
                    ->values();

                $assignedCount = $fulfillments->count();
                $totalAssigned += $assignedCount;
                if ($assignedCount < $requested) {
                    $allAssigned = false;
                }

                $slots = [];
                $assignedUnitIds = [];
                $lockedUnitIds = [];

                for ($i = 0; $i < $requested; $i++) {
                    $slotNum = $i + 1;
                    if ($i < $assignedCount) {
                        $f = $fulfillments[$i];
                        $unit = $f->unit;
                        $unitId = $unit?->id;
                        $assetNumber = $unit?->number;

                        if ($unitId) {
                            $assignedUnitIds[] = $unitId;
                        }

                        $isBorrowed = ($f->handover_id !== null) 
                            || ($unit && strtolower((string)$unit->status) === 'dipinjam')
                            || in_array($this->status, ['borrow', 'success']);

                        $isAssigned = ($f->assigned_at !== null) || $isBorrowed;

                        if ($isBorrowed) {
                            $state = 'borrowed';
                            $color = 'green';
                            $label = 'Sudah diserahkan / Dipinjam';
                            $isLocked = true;
                            if ($unitId) {
                                $lockedUnitIds[] = $unitId;
                            }
                        } elseif ($isAssigned) {
                            $state = 'assigned';
                            $color = 'purple';
                            $label = 'Dikonfirmasi (Menunggu Serah Terima)';
                            $isLocked = true;
                            if ($unitId) {
                                $lockedUnitIds[] = $unitId;
                            }
                        } else {
                            $state = 'assigned';
                            $color = 'purple';
                            $label = 'Dialokasikan (Belum Dikonfirmasi)';
                            $isLocked = false;
                        }

                        $slots[] = [
                            'slot_number' => $slotNum,
                            'fulfillment_id' => $f->id,
                            'unit_id' => $unitId,
                            'asset_number' => $assetNumber,
                            'state' => $state,
                            'color' => $color,
                            'label' => $label,
                            'is_locked' => $isLocked,
                        ];
                    } else {
                        $slots[] = [
                            'slot_number' => $slotNum,
                            'fulfillment_id' => null,
                            'unit_id' => null,
                            'asset_number' => null,
                            'state' => 'unfulfilled',
                            'color' => 'red',
                            'label' => 'Belum dialokasikan',
                            'is_locked' => false,
                        ];
                    }
                }

                // Available units for the "Pilih Alokasi Aset" modal datatable
                $availableUnitModels = $fulfillmentService->getAvailableUnitsQuery($item, true)->get();
                $stagedElsewhereUnitIds = RequestFulfillment::whereIn('unit_id', $availableUnitModels->pluck('id'))
                    ->where('request_item_id', '!=', $item->id)
                    ->whereNull('assigned_at')
                    ->whereNull('completed_at')
                    ->pluck('unit_id')
                    ->all();

                $availableUnits = $availableUnitModels
                    ->map(function ($u) use ($assignedUnitIds, $lockedUnitIds, $stagedElsewhereUnitIds) {
                        $loc = $u->location?->full_name ?? '-';

                        $brand = $u->lot?->barang?->brand?->name ?? '';
                        $name = $u->lot?->barang?->name ?? '';
                        $spec = $u->lot?->barang?->specification ?? '';
                        $variantParts = array_filter([$brand, $name, $spec], fn($v) => !empty($v) && $v !== '-');
                        $variant = !empty($variantParts) ? implode(' ', $variantParts) : ($u->lot?->barang?->name ?? '-');

                        return [
                            'id' => $u->id,
                            'asset_code' => $u->number,
                            'lot_code' => $u->lot?->number ?? '-',
                            'brand' => $brand,
                            'name' => $name,
                            'spec' => $spec,
                            'variant' => $variant,
                            'status' => $u->status,
                            'condition' => $u->condition ?? 'Baik',
                            'storage_location' => $loc,
                            'is_currently_assigned' => in_array($u->id, $assignedUnitIds),
                            'is_locked' => in_array($u->id, $lockedUnitIds),
                            'is_staged_elsewhere' => in_array($u->id, $stagedElsewhereUnitIds),
                        ];
                    })->values()->toArray();

                $itemData['allocation_slots'] = $slots;
                $itemData['available_units'] = $availableUnits;
                $itemData['assets'] = $fulfillments->pluck('unit.number')->filter()->values()->toArray();
            }

            return $itemData;
        });

        $logs = [];
        if ($this->relationLoaded('statusLogs')) {
            $logs = $this->statusLogs->map(function ($log) {
                $actorRole = 'User';
                $actorName = $log->changer->name ?? '-';
                if ($log->changer && $log->changer->role === 'admin') {
                    $actorRole = 'Admin';
                } else if ($log->changer && in_array($log->changer->role, ['manager', 'ifs_manager'])) {
                    $actorRole = 'Manager';
                }

                return [
                    'id' => $log->id,
                    'status_from' => $log->status_from,
                    'status_to' => $log->status_to,
                    'time' => $log->created_at ? $log->created_at->format('d-m-Y H:i') : '-',
                    'actor' => "{$actorRole}: {$actorName}",
                    'user' => $actorName,
                    'note' => $log->note ?? '',
                ];
            })->toArray();
        }

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'number' => $this->request_number,
            'type' => $this->type_key,
            'typeLabel' => $this->type_name,
            'pemanfaatan' => $this->utilization,
            'pemanfaatanDetail' => $pemanfaatanDetail,
            'destination' => $this->destination_name,
            'reasoning' => $this->reasoning,
            'durationStart' => $startDate ? $startDate->format('d-m-Y H:i') : null,
            'durationEnd' => $endDate ? $endDate->format('d-m-Y H:i') : null,
            'durationDays' => $durationDays,
            'durationHours' => $durationHours,
            'borrowPeriod' => $borrowPeriod,
            'status' => !empty($this->status) 
                ? implode(', ', array_map(fn($s) => self::STATUS_MAP[trim($s)] ?? trim($s), explode(',', $this->status)))
                : '-',
            'statuses' => !empty($this->status)
                ? array_values(array_map(fn($s) => self::STATUS_MAP[trim($s)] ?? trim($s), explode(',', $this->status)))
                : [],
            'status_badges' => !empty($this->status)
                ? array_values(array_map(function ($raw) {
                    $label = self::STATUS_MAP[trim($raw)] ?? trim($raw);
                    $class = match ($label) {
                        'Menunggu Serah Terima', 'Serah Terima' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-300',
                        'Parsial', 'Partial' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-950/40 dark:text-cyan-300',
                        'Dikonfirmasi Admin' => 'bg-teal-100 text-teal-800 dark:bg-teal-950/40 dark:text-teal-300',
                        'Di-approve' => 'bg-blue-100 text-blue-800 dark:bg-blue-950/40 dark:text-blue-300',
                        'Menunggu approval' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
                        'Selesai' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
                        'Dipinjam' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300',
                        'Ditolak' => 'bg-destructive/10 text-destructive dark:bg-destructive/20 border border-destructive/20',
                        'Dibatalkan' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
                        default => 'bg-muted text-muted-foreground border-border',
                    };
                    return ['label' => $label, 'class' => $class];
                }, explode(',', $this->status)))
                : [],
            'raw_status' => $this->status,
            'created_at' => $this->created_at ? $this->created_at->format('d-m-Y H:i') : '-',
            
            // Requester details (shown above PIC Approval)
            'requester' => $this->user?->name ?? '-',
            'requester_email' => $this->user?->email,
            'requester_department' => $this->department?->org_name 
                ?? $this->department?->name 
                ?? $this->user?->org_name 
                ?? '-',

            // PIC Approval details
            'approver_name' => $this->approver?->name ?? '-',
            'approval_by' => $this->approval?->approver?->name,
            'approval_at' => $this->approval?->decided_at?->format('d-m-Y H:i'),
            'approval' => $this->approval ? [
                'id' => $this->approval->id,
                'note' => $this->approval->note,
                'decision' => $this->approval->decision,
                'decided_at' => $this->approval->decided_at?->format('d-m-Y H:i'),
                'approver_name' => $this->approval->approver?->name,
            ] : null,

            // Confirmation details
            'confirmation_by' => $this->adminConfirmation?->admin?->name,
            'confirmation_at' => $this->adminConfirmation?->decided_at?->format('d-m-Y H:i'),

            // Handover schedule details
            'handover_method' => $this->handover ? ($this->handover->method === 'pickup' ? 'Ambil sendiri' : 'Diantar') : null,
            'handover_time' => $this->handover?->scheduled_date?->format('d-m-Y H:i'),
            'handover_location' => $this->handover?->location,
            'handover_note' => $this->handover?->note,

            'logs' => $logs,
            'items' => $itemsTransformed,

            // Fulfillment summary indicators
            'fulfillment_summary' => [
                'total_items' => $this->items->count(),
                'total_quantity_requested' => $totalRequested,
                'total_quantity_assigned' => $totalAssigned,
                'is_all_assigned' => $allAssigned,
                'can_confirm_full' => $allAssigned,
                'can_confirm_partial' => $totalAssigned > 0 && !$allAssigned,
            ],
        ];
    }
}
