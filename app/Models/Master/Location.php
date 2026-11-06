<?php

namespace App\Models\Master;

use App\Models\HrdOrgchart;
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
        'related_departement',
        'is_active',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'related_departement' => 'integer',
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
     * Related HRD department/orgchart.
     * LOCATION ||--o{ HRD_ORGCHART : "allocated to department"
     */
    public function relatedDepartment(): BelongsTo
    {
        return $this->belongsTo(HrdOrgchart::class, 'related_departement', 'id');
    }

    /**
     * Department alias for relatedDepartment.
     */
    public function department(): BelongsTo
    {
        return $this->relatedDepartment();
    }

    /**
     * Related departement alias matching column name.
     */
    public function relatedDepartement(): BelongsTo
    {
        return $this->relatedDepartment();
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
        if (isset($this->attributes['full_name']) && is_string($this->attributes['full_name']) && $this->attributes['full_name'] !== '') {
            return $this->attributes['full_name'];
        }

        $parts = [$this->name];
        $current = $this;
        $visited = [$this->id => true];

        while ($current->parent_id) {
            if (isset($visited[$current->parent_id])) {
                break;
            }
            $visited[$current->parent_id] = true;

            $parent = $current->relationLoaded('parent') ? $current->parent : $current->parent()->first();
            if (!$parent || $parent->id === $current->id) {
                break;
            }
            array_unshift($parts, $parent->name);
            $current = $parent;
        }

        return $this->attributes['full_name'] = implode(', ', $parts);
    }

    /**
     * Automatically wire in-memory parent relationships and precompute full_name
     * whenever a collection of locations is hydrated, preventing N+1 queries during json_encode.
     */
    public function newCollection(array $models = []): \Illuminate\Database\Eloquent\Collection
    {
        $collection = parent::newCollection($models);

        if (count($models) > 1) {
            static::hydrateHierarchyInMemory($collection);
        }

        return $collection;
    }

    /**
     * Wire parent references and precompute full_name in memory for a collection of locations.
     */
    public static function hydrateHierarchyInMemory(iterable $locations): void
    {
        $byId = [];
        foreach ($locations as $loc) {
            if ($loc instanceof self && $loc->id !== null) {
                $byId[(int) $loc->id] = $loc;
            }
        }

        if (empty($byId)) {
            return;
        }

        foreach ($byId as $loc) {
            if ($loc->parent_id && isset($byId[(int) $loc->parent_id]) && !$loc->relationLoaded('parent')) {
                $loc->setRelation('parent', $byId[(int) $loc->parent_id]);
            }
        }

        foreach ($byId as $loc) {
            $parts = [$loc->name];
            $curr = $loc;
            $visited = [(int) $loc->id => true];
            $missingExternalParent = false;

            while ($curr->parent_id) {
                $pid = (int) $curr->parent_id;
                if (isset($visited[$pid])) {
                    break;
                }
                $visited[$pid] = true;

                $parent = $byId[$pid] ?? ($curr->relationLoaded('parent') ? $curr->parent : null);
                if (!$parent) {
                    $missingExternalParent = true;
                    break;
                }
                array_unshift($parts, $parent->name);
                $curr = $parent;
            }

            if (!$missingExternalParent) {
                $loc->attributes['full_name'] = implode(', ', $parts);
            }
        }
    }

    /**
     * Fetch all locations ordered by name in a single SQL query with full_name precomputed in memory.
     */
    public static function allWithFullName(): \Illuminate\Database\Eloquent\Collection
    {
        return static::query()->orderBy('name')->get();
    }

    /**
     * Build an O(1) lookup map of [location_id => ['building_id', 'building_name', 'floor_id', 'floor_name', 'room_id', 'room_name', 'full_name']].
     */
    public static function hierarchyMap(?iterable $locations = null): array
    {
        $locations = $locations ?? static::allWithFullName();
        $byId = [];

        foreach ($locations as $loc) {
            $id = (int) (is_array($loc) ? $loc['id'] : $loc->id);
            $byId[$id] = [
                'id' => $id,
                'name' => is_array($loc) ? ($loc['name'] ?? '') : ($loc->name ?? ''),
                'parent_id' => is_array($loc) ? ($loc['parent_id'] ?? null) : ($loc->parent_id ?? null),
                'is_active' => (bool) (is_array($loc) ? ($loc['is_active'] ?? true) : ($loc->is_active ?? true)),
            ];
        }

        $map = [];
        foreach ($byId as $id => $node) {
            $chain = [$node];
            $curr = $node;
            $visited = [$id => true];

            while (!empty($curr['parent_id']) && isset($byId[(int) $curr['parent_id']])) {
                $pid = (int) $curr['parent_id'];
                if (isset($visited[$pid])) {
                    break;
                }
                $visited[$pid] = true;
                $parent = $byId[$pid];
                array_unshift($chain, $parent);
                $curr = $parent;
            }

            $names = array_column($chain, 'name');
            $building = $chain[0] ?? null;
            $floor = $chain[1] ?? null;
            $room = $chain[2] ?? null;

            $isActive = true;
            foreach ($chain as $chainItem) {
                if (isset($chainItem['is_active']) && !$chainItem['is_active']) {
                    $isActive = false;
                    break;
                }
            }

            $map[$id] = [
                'building_id' => $building ? (int) $building['id'] : null,
                'building_name' => $building ? $building['name'] : null,
                'floor_id' => $floor ? (int) $floor['id'] : null,
                'floor_name' => $floor ? $floor['name'] : null,
                'room_id' => $room ? (int) $room['id'] : null,
                'room_name' => $room ? $room['name'] : null,
                'full_name' => implode(', ', $names),
                'is_active' => $isActive,
            ];
        }

        return $map;
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
