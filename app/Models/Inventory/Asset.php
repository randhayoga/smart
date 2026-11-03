<?php

namespace App\Models\Inventory;

use App\Models\Master\Location;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Asset model representing individual physical items and tracking their state/location lifecycle.
 */
class Asset extends Model
{
    use HasFactory;

    protected $table = 'assets';

    /**
     * Global flag to suppress automatic lifecycle entry creation during specific operations.
     */
    public static bool $suppressLifecycleLogging = false;

    /**
     * Execute callback without triggering automatic lifecycle logging in model events.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function withoutLifecycleLogging(callable $callback)
    {
        $previous = static::$suppressLifecycleLogging;
        static::$suppressLifecycleLogging = true;
        try {
            return $callback();
        } finally {
            static::$suppressLifecycleLogging = $previous;
        }
    }

    protected static function booted()
    {
        // 1. Log when an Asset is created
        static::created(function (Asset $asset) {
            if ($asset->approval_status !== 'Pending:DM' && !str_starts_with($asset->status ?? '', 'Pending')) {
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
            if (static::$suppressLifecycleLogging) {
                return;
            }

            $dirty = $asset->getDirty();
            $tracked = ['status', 'approval_status', 'condition', 'location_id', 'is_assigned_to_location', 'license_exp_date'];
            $changed = array_intersect(array_keys($dirty), $tracked);

            if (!empty($changed)) {
                $isPendingStatus = ($asset->isDirty('approval_status') && $asset->approval_status === 'Pending:DM');

                $isLicenseExtended = false;
                $formattedNewDate = null;
                if ($asset->is_software && $asset->isDirty('license_exp_date')) {
                    $oldExpDate = $asset->getOriginal('license_exp_date') ? Carbon::parse($asset->getOriginal('license_exp_date'))->startOfDay() : null;
                    $newExpDate = $asset->license_exp_date ? Carbon::parse($asset->license_exp_date)->startOfDay() : null;

                    if ($newExpDate && (!$oldExpDate || $newExpDate->gt($oldExpDate))) {
                        $isLicenseExtended = true;
                        $formattedNewDate = $newExpDate->format('d-m-Y');
                        $asset->status = 'Tersedia';
                    }
                }

                $actions = [];

                // 1. Assignment Action (Preempts redundant status change log)
                if ($asset->isDirty('is_assigned_to_location')) {
                    // When unassigning or assigning, the assignment event happened at the original location
                    $locId = $asset->getOriginal('location_id') ?? $asset->location_id;
                    $location = $locId ? \App\Models\Master\Location::find($locId) : null;
                    $locationName = $location?->name ?? $location?->full_name ?? '-';

                    $note = $asset->is_assigned_to_location
                        ? "Di-assign ke lokasi {$locationName}"
                        : "Assignment ke lokasi {$locationName} selesai.";

                    $actions[] = [
                        'action_type' => 'Assignment',
                        'note' => $note,
                        'location_id' => $locId,
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
                } elseif (($asset->isDirty('status') || $asset->isDirty('approval_status') || $isLicenseExtended) && !$isPendingStatus) {
                    // 2. Status Change
                    $oldStatus = $asset->getOriginal('status');
                    $newStatus = $asset->status;
                    $oldApproval = $asset->getOriginal('approval_status');

                    $approval = null;
                    $actionType = 'Perubahan status';
                    if (!empty($oldApproval) || str_contains((string)$oldStatus, 'Pending')) {
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
                        if ($isLicenseExtended) {
                            $note = "Lisensi software telah diperpanjang hingga {$formattedNewDate}.";
                        } elseif ($newStatus === 'Lisensi Kedaluwarsa') {
                            $expDate = $asset->license_exp_date ? Carbon::parse($asset->license_exp_date)->format('d-m-Y') : null;
                            $note = $expDate ? "Lisensi software telah kedaluwarsa pada {$expDate}." : "Lisensi software telah kedaluwarsa.";
                        } else {
                            $note = "Status diubah dari '{$oldStatus}' menjadi '{$newStatus}'.";
                        }
                    }

                    $actions[] = [
                        'action_type' => $actionType,
                        'note' => $note,
                        'location_id' => $asset->location_id,
                        'previous_state' => ['status' => $oldStatus],
                        'new_state' => ['status' => $newStatus],
                        'approval' => $approval,
                    ];
                }

                // 2. Condition Change (Suppressed if status change was already handled as an approval/rejection, or if software asset)
                $isHandledByApproval = isset($approval) && $approval !== null;
                if (!$asset->is_software && $asset->isDirty('condition') && !$isPendingStatus && !$isHandledByApproval) {
                    $oldCondition = $asset->getOriginal('condition');
                    $newCondition = $asset->condition;
                    $note = "Kondisi fisik diubah dari '{$oldCondition}' menjadi '{$newCondition}'.";

                    $actions[] = [
                        'action_type' => 'Perubahan kondisi',
                        'note' => $note,
                        'location_id' => $asset->location_id,
                        'previous_state' => ['condition' => $oldCondition],
                        'new_state' => ['condition' => $newCondition],
                        'approval' => null,
                    ];
                }

                // 3. Location Change (Pemindahan - suppressed for software assets)
                if (!$asset->is_software && $asset->isDirty('location_id')) {
                    $oldLoc = Location::find($asset->getOriginal('location_id'))?->full_name ?? '-';
                    $newLoc = Location::find($asset->location_id)?->full_name ?? '-';
                    $note = "Lokasi dipindahkan dari '{$oldLoc}' ke '{$newLoc}.'";

                    $actions[] = [
                        'action_type' => 'Pemindahan',
                        'note' => $note,
                        'location_id' => $asset->location_id,
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
                            'location_id' => $act['location_id'] ?? $asset->location_id,
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
        'approval_status',
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
        'license_exp_date',
        'renewal_reminder',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'project_id' => 'integer',
        'legacy_vendor_id' => 'integer',
        'is_assigned_to_location' => 'boolean',
        'license_exp_date' => 'date',
        'renewal_reminder' => 'integer',
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
        $statuses = [];
        if (!empty($this->status)) {
            $statuses[] = $this->status;
        }
        if (!empty($this->approval_status)) {
            $statuses[] = $this->approval_status;
        }

        return $statuses;
    }

    /**
     * Check if the asset currently has the given status or approval status.
     */
    public function hasStatus(string $status): bool
    {
        return $this->status === $status || $this->approval_status === $status;
    }

    /**
     * Scope to filter assets that match a specific status or approval status.
     */
    public function scopeWhereHasStatus($query, string $status)
    {
        return $query->where(function ($q) use ($status) {
            $q->where('status', $status)
              ->orWhere('approval_status', $status);
        });
    }

    /**
     * Scope to filter assets strictly by approval status using direct index seek.
     */
    public function scopeWhereApprovalStatus($query, ?string $approvalStatus)
    {
        return $query->where('approval_status', $approvalStatus);
    }

    /**
     * Get the primary base status of the asset.
     */
    public function getBaseStatusAttribute(): string
    {
        return $this->status ?? 'Tersedia';
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
        if (!$this->hasStatus('Dipinjam (ST)') && !$this->hasStatus('Di-assign (LT)') && !$this->hasStatus('Dipinjam (sewa)')) {
            return null;
        }

        $assignment = $this->relationLoaded('fulfillments')
            ? $this->fulfillments->whereNull('return_id')->sortByDesc('id')->first()
            : \App\Models\Request\RequestFulfillment::with(['requestItem.request.user'])
                ->where('asset_id', $this->id)
                ->whereNull('return_id')
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
     * Accessor to determine if this asset belongs to the software category.
     */
    public function getIsSoftwareAttribute(): bool
    {
        $this->loadMissing('lot.model.type.category');
        $category = $this->lot?->model?->type?->category;
        return $category ? ($category->code === 'SOFT' || strtolower($category->name ?? '') === 'software') : false;
    }

    /**
     * Determine if this asset is a software asset past its license expiration date.
     */
    public function isLicenseExpired(): bool
    {
        if (!$this->is_software || !$this->license_exp_date) {
            return false;
        }

        return Carbon::parse($this->license_exp_date)->startOfDay()->lt(Carbon::today());
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

    /**
     * Automatically transition software assets whose license expiration date has passed to 'Lisensi Kedaluwarsa'.
     *
     * @return int Number of assets transitioned
     */
    public static function expireOverdueLicenses(): int
    {
        $today = Carbon::today();
        $overdueAssets = static::with(['lot.model.brand', 'lot.model.type.category'])
            ->whereNotNull('license_exp_date')
            ->whereDate('license_exp_date', '<', $today)
            ->whereNotIn('status', ['Dihapus', 'Lisensi Kedaluwarsa', 'Belum Diverifikasi'])
            ->whereHas('lot.model.type.category', function ($query) {
                $query->where('code', 'SOFT')
                    ->orWhereRaw('LOWER(name) = ?', ['software']);
            })
            ->get();

        $count = 0;
        $notificationService = app(NotificationService::class);

        foreach ($overdueAssets as $asset) {
            $asset->status = 'Lisensi Kedaluwarsa';
            $asset->save();
            $count++;

            try {
                $notificationService->notifySoftwareLicenseExpired($asset);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Failed to notify license expiration for asset {$asset->number}: " . $e->getMessage());
            }
        }

        return $count;
    }

    /**
     * Execute overdue license expiration check cached once per calendar day to ensure 0ms DB cost on subsequent requests.
     *
     * @return int
     */
    public static function expireOverdueLicensesCached(): int
    {
        $cacheKey = 'software_licenses_overdue_checked_' . now()->toDateString();
        if (Cache::has($cacheKey)) {
            return 0;
        }

        $count = static::expireOverdueLicenses();
        Cache::put($cacheKey, true, now()->endOfDay());
        return $count;
    }
}
