<?php

namespace App\Models\Inventory;

use App\Models\Master\Location;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;

/**
 * Asset model representing individual physical items and tracking their state/location lifecycle.
 */
class Asset extends Model
{
    use HasFactory;

    protected $table = 'assets';

    protected static function booted()
    {
        // 1. Log when an Asset is created
        static::created(function (Asset $asset) {
            if (!$asset->hasStatus('Pending:DM') && !str_starts_with($asset->status ?? '', 'Pending')) {
                AssetLifecycle::create([
                    'asset_id' => $asset->id,
                    'action_type' => 'Registrasi',
                    'status' => $asset->status,
                    'condition' => $asset->condition,
                    'location_id' => $asset->location_id,
                    'start_date' => now(),
                    'end_date' => null,
                    'actor_id' => Auth::id(),
                    'note' => 'Aset berhasil diregistrasi ke dalam sistem.',
                ]);
            }
        });

        // 2. Log when an Asset is updated
        static::updating(function (Asset $asset) {
            $dirty = $asset->getDirty();
            $tracked = ['status', 'condition', 'location_id', 'is_assigned_to_location'];
            $changed = array_intersect(array_keys($dirty), $tracked);

            if (!empty($changed)) {
                $isPendingStatus = ($asset->isDirty('status') && $asset->hasStatus('Pending:DM'));

                $actions = [];

                // 1. Assignment Action (Preempts redundant status change log)
                if ($asset->isDirty('is_assigned_to_location')) {
                    $locId = $asset->location_id ?? $asset->getOriginal('location_id');
                    $location = $locId ? \App\Models\Master\Location::find($locId) : null;
                    $locationName = $location?->name ?? $location?->full_name ?? '-';

                    $note = $asset->is_assigned_to_location
                        ? "Aset di-assign ke lokasi {$locationName}"
                        : "Assignment asset ke lokasi {$locationName} selesai.";

                    $actions[] = [
                        'action_type' => 'Assignment',
                        'note' => $note,
                        'previous_state' => [
                            'is_assigned_to_location' => $asset->getOriginal('is_assigned_to_location'),
                            'status' => $asset->getOriginal('status'),
                        ],
                        'new_state' => [
                            'is_assigned_to_location' => $asset->is_assigned_to_location,
                            'status' => $asset->status,
                        ],
                        'approval' => null,
                    ];
                } elseif ($asset->isDirty('status') && !$isPendingStatus) {
                    // 2. Status Change
                    $oldStatus = $asset->getOriginal('status');
                    $newStatus = $asset->status;

                    $approval = null;
                    $actionType = 'Perubahan status';
                    if (str_contains((string)$oldStatus, 'Pending')) {
                        $approval = AssetDeletionApproval::where('asset_id', $asset->id)
                            ->whereIn('decision', ['approved', 'rejected'])
                            ->orderBy('updated_at', 'desc')
                            ->first();

                        if ($approval) {
                            $decisionText = $approval->decision === 'approved' ? 'disetujui' : 'ditolak';
                            $actionType = $approval->decision === 'approved' ? 'Persetujuan' : 'Penolakan';
                            $note = $approval->dm_note ?? "Penghapusan aset dengan kondisi {$approval->proposed_condition} {$decisionText} oleh DM IFS.";
                        } else {
                            $note = "Status diubah dari '{$oldStatus}' menjadi '{$newStatus}'.";
                        }
                    } else {
                        $note = "Status diubah dari '{$oldStatus}' menjadi '{$newStatus}'.";
                    }

                    $actions[] = [
                        'action_type' => $actionType,
                        'note' => $note,
                        'previous_state' => ['status' => $oldStatus],
                        'new_state' => ['status' => $newStatus],
                        'approval' => $approval,
                    ];
                }

                // 2. Condition Change (Suppressed if status change was already handled as an approval/rejection)
                $isHandledByApproval = isset($approval) && $approval !== null;
                if ($asset->isDirty('condition') && !$isPendingStatus && !$isHandledByApproval) {
                    $oldCondition = $asset->getOriginal('condition');
                    $newCondition = $asset->condition;
                    $note = "Kondisi fisik diubah dari '{$oldCondition}' menjadi '{$newCondition}'.";

                    $actions[] = [
                        'action_type' => 'Perubahan kondisi',
                        'note' => $note,
                        'previous_state' => ['condition' => $oldCondition],
                        'new_state' => ['condition' => $newCondition],
                        'approval' => null,
                    ];
                }

                // 3. Location Change (Pemindahan)
                if ($asset->isDirty('location_id')) {
                    $oldLoc = Location::find($asset->getOriginal('location_id'))?->full_name ?? '-';
                    $newLoc = Location::find($asset->location_id)?->full_name ?? '-';
                    $note = "Lokasi dipindahkan dari '{$oldLoc}' ke '{$newLoc}.'";

                    $actions[] = [
                        'action_type' => 'Pemindahan',
                        'note' => $note,
                        'previous_state' => [
                            'location_id' => $asset->getOriginal('location_id'),
                        ],
                        'new_state' => [
                            'location_id' => $asset->location_id,
                        ],
                        'approval' => null,
                    ];
                }

                if (!empty($actions)) {
                    AssetLifecycle::where('asset_id', $asset->id)
                        ->whereNull('end_date')
                        ->update(['end_date' => now()]);

                    $totalActions = count($actions);
                    foreach ($actions as $index => $act) {
                        if ($index > 0) {
                            AssetLifecycle::where('asset_id', $asset->id)
                                ->whereNull('end_date')
                                ->update(['end_date' => now()]);
                        }

                        $actorId = Auth::id() ?? ($act['approval'] ? $act['approval']->approver_id : null);
                        $isLast = ($index === $totalActions - 1);

                        AssetLifecycle::create([
                            'asset_id' => $asset->id,
                            'action_type' => $act['action_type'],
                            'status' => $asset->status,
                            'condition' => $asset->condition,
                            'location_id' => $asset->location_id,
                            'start_date' => now(),
                            'end_date' => $isLast ? null : now(),
                            'actor_id' => $actorId,
                            'note' => $act['note'],
                            'previous_state' => $act['previous_state'],
                            'new_state' => $act['new_state'],
                        ]);
                    }
                }
            }
        });

        // 3. Close lifecycle on delete
        static::deleted(function (Asset $asset) {
            AssetLifecycle::where('asset_id', $asset->id)
                ->whereNull('end_date')
                ->update(['end_date' => now()]);
        });
    }

    protected $with = ['lot', 'location'];

    protected $attributes = [
        'status' => 'Belum Diverifikasi',
        'borrowing_type' => 'LT',
        'classification' => 'Aset',
        'burden' => 'Corporate',
        'is_assigned_to_location' => false,
    ];

    protected $fillable = [
        'number',
        'legacy_number',
        'lot_id',
        'location_id',
        'status',
        'condition',
        'borrowing_type',
        'type',
        'classification',
        'price',
        'image_url',
        'vehicle_registration',
        'burden',
        'project_id',
        'legacy_vendor_id',
        'comp_specification',
        'is_assigned_to_location',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'project_id' => 'integer',
        'legacy_vendor_id' => 'integer',
        'is_assigned_to_location' => 'boolean',
    ];

    /**
     * The inventory lot from which this asset originated.
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    /**
     * The project associated with this asset when burden is Project.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(\App\Models\TbProject::class, 'project_id', 'id_project');
    }

    /**
     * Current building/site location of this asset.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * The legacy vendor associated with this asset during migration.
     */
    public function legacyVendor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Master\Vendor::class, 'legacy_vendor_id');
    }

    /**
     * Display name of the legacy vendor associated with this asset, if any.
     */
    public function getLegacyVendorNameAttribute(): ?string
    {
        return $this->legacyVendor?->name;
    }

    /**
     * Inventory logs recording events for this asset.
     */
    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class, 'asset_id');
    }

    /**
     * Deletion approval requests for this asset.
     */
    public function deletionApprovals(): HasMany
    {
        return $this->hasMany(AssetDeletionApproval::class, 'asset_id');
    }

    /**
     * Get the array of active statuses for this asset.
     */
    public function getStatusesAttribute(): array
    {
        if (empty($this->status)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $this->status))));
    }

    /**
     * Check if the asset currently has the given status.
     */
    public function hasStatus(string $status): bool
    {
        return in_array($status, $this->statuses, true);
    }

    /**
     * Scope to filter assets that contain a specific status in their comma-separated status string.
     */
    public function scopeWhereHasStatus($query, string $status)
    {
        return $query->where(function ($q) use ($status) {
            $q->where('status', $status)
              ->orWhere('status', 'like', "{$status},%")
              ->orWhere('status', 'like', "%,{$status},%")
              ->orWhere('status', 'like', "%,{$status}");
        });
    }

    /**
     * Get the primary base status of the asset (excluding Pending:* tokens).
     */
    public function getBaseStatusAttribute(): string
    {
        foreach ($this->statuses as $s) {
            if (!str_starts_with($s, 'Pending')) {
                return $s;
            }
        }

        return 'Tersedia';
    }

    /**
     * Activation approval requests for this newly created asset.
     */
    public function activationApprovals(): HasMany
    {
        return $this->hasMany(AssetActivationApproval::class, 'asset_id');
    }

    public function unitActivationApprovals(): HasMany
    {
        return $this->hasMany(AssetActivationApproval::class, 'asset_id');
    }

    /**
     * Latest activation approval request for this asset.
     */
    public function latestActivationApproval(): HasOne
    {
        return $this->hasOne(AssetActivationApproval::class, 'asset_id')->latestOfMany();
    }

    public function latestUnitActivationApproval(): HasOne
    {
        return $this->hasOne(AssetActivationApproval::class, 'asset_id')->latestOfMany();
    }

    public function setTypeAttribute($value): void
    {
        $this->attributes['borrowing_type'] = $value;
    }

    public function getTypeAttribute()
    {
        return $this->attributes['borrowing_type'] ?? null;
    }

    /**
     * Complete lifecycle history records for this asset.
     */
    public function lifecycles(): HasMany
    {
        return $this->hasMany(AssetLifecycle::class, 'asset_id');
    }

    /**
     * Fulfillments associated with this asset.
     */
    public function fulfillments(): HasMany
    {
        return $this->hasMany(\App\Models\Request\RequestFulfillment::class, 'asset_id');
    }

    /**
     * Accessor to get active borrowing details if asset is currently loaned.
     */
    public function getActiveBorrowingAttribute(): ?array
    {
        if (!$this->hasStatus('Dipinjam')) {
            return null;
        }

        $assignment = $this->relationLoaded('fulfillments')
            ? $this->fulfillments->whereNull('completed_at')->sortByDesc('id')->first()
            : \App\Models\Request\RequestFulfillment::with(['requestItem.request.user'])
                ->where('asset_id', $this->id)
                ->whereNull('completed_at')
                ->latest('id')
                ->first();

        if (!$assignment || !$assignment->requestItem || !$assignment->requestItem->request) {
            return null;
        }

        $req = $assignment->requestItem->request;

        return [
            'assignment_id' => $assignment->id,
            'request_id' => $req->id,
            'request_number' => $req->request_number,
            'user_id' => $req->user_id,
            'user_name' => $req->user?->name ?? '-',
            'utilization' => $req->utilization,
            'org_id' => $req->org_id,
            'project_id' => $req->project_id,
            'start_date' => $assignment->requestItem->start_date ? $assignment->requestItem->start_date->format('Y-m-d') : ($req->created_at ? $req->created_at->format('Y-m-d') : null),
            'note' => $req->reasoning ?? '',
        ];
    }

    /**
     * Accessor to ensure number attribute is properly trimmed.
     */
    public function getNumberAttribute(?string $value): ?string
    {
        return $value !== null ? trim($value) : null;
    }

    /**
     * Accessor to determine if this asset is a vehicle based on item category.
     */
    public function getIsVehicleAttribute(): bool
    {
        $this->loadMissing('lot.model.type.category');
        $lot = $this->lot;
        if ($lot && $lot->model && $lot->model->type && $lot->model->type->category) {
            $catName = strtolower($lot->model->type->category->name);
            $typeName = strtolower($lot->model->type->name);
            return str_contains($catName, 'kendaraan') || str_contains($typeName, 'kendaraan') ||
                   str_contains($catName, 'mobil') || str_contains($typeName, 'mobil') ||
                   str_contains($catName, 'motor') || str_contains($typeName, 'motor');
        }
        return false;
    }

    /**
     * Get the route key for implicit route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'number';
    }

    /**
     * Get the route key value for generating URLs (alphanumeric only).
     */
    public function getRouteKey(): mixed
    {
        $number = $this->getAttribute($this->getRouteKeyName());
        if ($number !== null) {
            $clean = preg_replace('/[^a-zA-Z0-9]/', '', (string)$number);
            if ($clean !== '') {
                return $clean;
            }
        }
        return $this->getKey();
    }

    /**
     * Retrieve the model for a bound value, supporting alphanumeric code, raw code (number),
     * legacy number, and numeric ID.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?? $this->getRouteKeyName();

        // 1. Direct exact match on field (number)
        $model = $this->where($field, $value)->first();
        if ($model) {
            return $model;
        }

        // 2. Direct exact match on legacy_number
        $model = $this->where('legacy_number', $value)->first();
        if ($model) {
            return $model;
        }

        // 3. Numeric ID match
        if (is_numeric($value)) {
            $model = $this->where('id', $value)->first();
            if ($model) {
                return $model;
            }
        }

        // 4. Alphanumeric normalized match
        $cleanValue = preg_replace('/[^a-zA-Z0-9]/', '', (string)$value);
        if (!empty($cleanValue)) {
            // Check number column
            $model = $this->whereRaw(
                "LOWER(REPLACE(REPLACE(REPLACE(REPLACE({$field}, '-', ''), ' ', ''), '_', ''), '/', '')) = ?",
                [strtolower($cleanValue)]
            )->first();

            if ($model) {
                return $model;
            }

            // Check legacy_number column
            $model = $this->whereRaw(
                "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(legacy_number, '-', ''), ' ', ''), '_', ''), '/', '')) = ?",
                [strtolower($cleanValue)]
            )->first();

            if ($model) {
                return $model;
            }

            // Fallback in-memory check (number and legacy_number)
            return $this->all()->first(function ($item) use ($field, $cleanValue) {
                $itemClean = preg_replace('/[^a-zA-Z0-9]/', '', (string)($item->{$field} ?? ''));
                if (strcasecmp($itemClean, $cleanValue) === 0) {
                    return true;
                }
                $legacyClean = preg_replace('/[^a-zA-Z0-9]/', '', (string)($item->legacy_number ?? ''));
                return strcasecmp($legacyClean, $cleanValue) === 0;
            });
        }

        return null;
    }
}
