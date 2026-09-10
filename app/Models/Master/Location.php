<?php

namespace App\Models\Master;

use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master Location model representing physical sites, branches, buildings, floors, or rooms.
 * Uses a self-referencing hierarchy via parent_id.
 */
class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'parent_id',
        'is_active',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'full_name',
    ];

    /**
     * Parent location.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }

    /**
     * Direct child locations.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Location::class, 'parent_id');
    }

    /**
     * Lots with this as default location.
     * LOCATION ||--o{ LOT : "default location"
     */
    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }

    /**
     * Units currently at this location.
     * LOCATION ||--o{ UNIT : "currently at"
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * Filter active locations.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the full breadcrumb path name of this location (e.g., 'Graha RE 1, Lantai Mezzanine, Ruang IFS Departemen').
     */
    public function getFullNameAttribute(): string
    {
        $parts = [$this->name];
        $current = $this;

        while ($current->parent_id) {
            $parent = $current->relationLoaded('parent') ? $current->parent : $current->parent()->first();
            if (!$parent || $parent->id === $current->id) {
                break;
            }
            array_unshift($parts, $parent->name);
            $current = $parent;
        }

        return implode(', ', $parts);
    }

    /**
     * Recursively retrieve all descendant IDs of this location.
     */
    public function allChildrenIds(): array
    {
        $ids = [];
        $children = $this->relationLoaded('children') ? $this->children : $this->children()->get();
        foreach ($children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->allChildrenIds());
        }
        return $ids;
    }
}
