<?php

namespace App\Models\Inventory;

use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Vendor;
use App\Models\TbProject;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lot (Inventory Batch) model representing received batches, procurement info, and stock counts.
 */
class Lot extends Model
{
    use HasFactory;

    protected $with = ['barang'];

    protected $appends = ['age'];

    protected $fillable = [
        'number',
        'barang_id',
        'organizer_id',
        'vendor_id',
        'legacy_vendor_id',
        'location_id',
        'initial_quantity',
        'current_quantity',
        'po_number',
        'date_of_receipt',
        'unit_price',
        'image_url',
        'burden',
        'project_id',
    ];

    protected $casts = [
        'date_of_receipt' => 'datetime',
        'unit_price' => 'decimal:2',
        'initial_quantity' => 'integer',
        'current_quantity' => 'integer',
        'vendor_id' => 'integer',
        'legacy_vendor_id' => 'integer',
    ];

    /**
     * The catalog item associated with this lot.
     */
    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class);
    }

    /**
     * The organizer / PIC responsible for this lot.
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    /**
     * The legacy vendor from local database (used for existing assets and inventory).
     */
    public function legacyVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'legacy_vendor_id');
    }

    /**
     * The vendor from external eproc database (for new LOTs).
     * Target db: "eproc" (not operational yet).
     */
    public function vendor(): BelongsTo
    {
        // Future eproc vendor relation (target DB: eproc)
        // return $this->belongsTo(EprocVendor::class, 'vendor_id');
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    /**
     * Display name of the vendor (resolves legacy vendor first for existing assets, or eproc vendor).
     */
    public function getVendorNameAttribute(): string
    {
        return $this->vendor?->name ?? $this->legacyVendor?->name ?? '-';
    }

    /**
     * The storage location for this lot.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * The project associated with this lot procurement, if any.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(TbProject::class, 'project_id', 'id_project');
    }

    /**
     * Serialized asset units created from this lot.
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * Inventory logs for this lot.
     * LOT ||--o{ INVENTORY_LOG : "logged"
     */
    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class);
    }

    /**
     * Fulfillments associated with this consumable lot.
     */
    public function fulfillments(): HasMany
    {
        return $this->hasMany(\App\Models\Request\RequestFulfillment::class, 'lot_id');
    }

    /**
     * Accessor to ensure number attribute is properly trimmed.
     */
    public function getNumberAttribute(?string $value): ?string
    {
        return $value !== null ? trim($value) : null;
    }

    /**
     * Get the lot's age in days.
     */
    protected function age(): Attribute
    {
        return Attribute::get(function () {
            if (!$this->date_of_receipt) {
                return 0;
            }
            return (int) floor($this->date_of_receipt->diffInYears(now()));
        });
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
     * Retrieve the model for a bound value, supporting alphanumeric code, raw code (number), and numeric ID.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?? $this->getRouteKeyName();

        // 1. Direct exact match
        $model = $this->where($field, $value)->first();
        if ($model) {
            return $model;
        }

        // 2. Numeric ID match
        if (is_numeric($value)) {
            $model = $this->where('id', $value)->first();
            if ($model) {
                return $model;
            }
        }

        // 3. Alphanumeric normalized match
        $cleanValue = preg_replace('/[^a-zA-Z0-9]/', '', (string)$value);
        if (!empty($cleanValue)) {
            $model = $this->whereRaw(
                "LOWER(REPLACE(REPLACE(REPLACE(REPLACE({$field}, '-', ''), ' ', ''), '_', ''), '/', '')) = ?",
                [strtolower($cleanValue)]
            )->first();

            if ($model) {
                return $model;
            }

            // Fallback in-memory check
            return $this->all()->first(function ($item) use ($field, $cleanValue) {
                $itemClean = preg_replace('/[^a-zA-Z0-9]/', '', (string)($item->{$field} ?? ''));
                return strcasecmp($itemClean, $cleanValue) === 0;
            });
        }

        return null;
    }
}
