<?php

namespace App\Models\Inventory;

use App\Models\Master\Floor;
use App\Models\Master\Location;
use App\Models\Master\Room;
use App\Models\Inventory\UnitLifecycle;
use App\Models\Inventory\UnitStatusApproval;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\Auth;

class Unit extends Model
{
    use HasFactory, HasUuids;

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected static function booted()
    {
        // 1. Log when a Unit is created
        static::created(function (Unit $unit) {
            if (!str_starts_with($unit->status ?? '', 'Pending')) {
                UnitLifecycle::create([
                    'unit_id' => $unit->id,
                    'action_type' => 'Registrasi',
                    'status' => $unit->status,
                    'condition' => $unit->condition,
                    'location_id' => $unit->location_id,
                    'floor_id' => $unit->floor_id,
                    'room_id' => $unit->room_id,
                    'start_date' => now(),
                    'end_date' => null,
                    'actor_id' => Auth::id(),
                    'note' => 'Aset berhasil diregistrasi ke dalam sistem.',
                ]);
            }
        });

        // 2. Log when a Unit is updated
        static::updating(function (Unit $unit) {
            $dirty = $unit->getDirty();
            $tracked = ['status', 'condition', 'location_id', 'floor_id', 'room_id'];
            $changed = array_intersect(array_keys($dirty), $tracked);

            if (!empty($changed)) {
                $isPendingStatus = ($unit->isDirty('status') && str_starts_with($unit->status ?? '', 'Pending'));

                $actions = [];

                // 1. Status Change
                if ($unit->isDirty('status') && !$isPendingStatus) {
                    $oldStatus = $unit->getOriginal('status');
                    $newStatus = $unit->status;

                    $approval = null;
                    if (str_starts_with((string)$oldStatus, 'Pending')) {
                        $approval = UnitStatusApproval::where('unit_id', $unit->id)
                            ->whereIn('decision', ['approved', 'rejected'])
                            ->orderBy('updated_at', 'desc')
                            ->first();

                        if ($approval) {
                            $decisionText = $approval->decision === 'approved' ? 'disetujui' : 'ditolak';
                            $note = $approval->note ?? "Penghapusan aset dengan kondisi {$approval->proposed_condition} {$decisionText} oleh DM IFS.";
                        } else {
                            $note = "Status diubah dari '{$oldStatus}' menjadi '{$newStatus}'.";
                        }
                    } else {
                        $note = "Status diubah dari '{$oldStatus}' menjadi '{$newStatus}'.";
                    }

                    $actions[] = [
                        'action_type' => 'Perubahan status',
                        'note' => $note,
                        'previous_state' => ['status' => $oldStatus],
                        'new_state' => ['status' => $newStatus],
                        'approval' => $approval,
                    ];
                }

                // 2. Condition Change
                if ($unit->isDirty('condition') && !$isPendingStatus) {
                    $oldCondition = $unit->getOriginal('condition');
                    $newCondition = $unit->condition;
                    $note = "Kondisi fisik diubah dari '{$oldCondition}' menjadi '{$newCondition}'.";

                    $actions[] = [
                        'action_type' => 'Perubahan kondisi',
                        'note' => $note,
                        'previous_state' => ['condition' => $oldCondition],
                        'new_state' => ['condition' => $newCondition],
                        'approval' => null,
                    ];
                }

                // 3. Location / Floor / Room Change (Pemindahan)
                if ($unit->isDirty('location_id') || $unit->isDirty('floor_id') || $unit->isDirty('room_id')) {
                    $oldLoc = Location::find($unit->getOriginal('location_id'))->name ?? '-';
                    $oldFloor = $unit->getOriginal('floor_id') ? (Floor::find($unit->getOriginal('floor_id'))->name ?? '') : '';
                    $oldRoom = $unit->getOriginal('room_id') ? (Room::find($unit->getOriginal('room_id'))->name ?? '') : '';

                    $oldPath = $oldLoc;
                    if ($oldFloor) {
                        $oldPath .= ", {$oldFloor}";
                    }
                    if ($oldRoom) {
                        $oldPath .= ", {$oldRoom}";
                    }

                    $newLoc = Location::find($unit->location_id)->name ?? '-';
                    $newFloor = $unit->floor_id ? (Floor::find($unit->floor_id)->name ?? '') : '';
                    $newRoom = $unit->room_id ? (Room::find($unit->room_id)->name ?? '') : '';

                    $newPath = $newLoc;
                    if ($newFloor) {
                        $newPath .= ", {$newFloor}";
                    }
                    if ($newRoom) {
                        $newPath .= ", {$newRoom}";
                    }

                    $note = "Lokasi dipindahkan dari '{$oldPath}' ke '{$newPath}.'";

                    $actions[] = [
                        'action_type' => 'Pemindahan',
                        'note' => $note,
                        'previous_state' => [
                            'location_id' => $unit->getOriginal('location_id'),
                            'floor_id' => $unit->getOriginal('floor_id'),
                            'room_id' => $unit->getOriginal('room_id'),
                        ],
                        'new_state' => [
                            'location_id' => $unit->location_id,
                            'floor_id' => $unit->floor_id,
                            'room_id' => $unit->room_id,
                        ],
                        'approval' => null,
                    ];
                }

                if (!empty($actions)) {
                    UnitLifecycle::where('unit_id', $unit->id)
                        ->whereNull('end_date')
                        ->update(['end_date' => now()]);

                    $totalActions = count($actions);
                    foreach ($actions as $index => $act) {
                        if ($index > 0) {
                            UnitLifecycle::where('unit_id', $unit->id)
                                ->whereNull('end_date')
                                ->update(['end_date' => now()]);
                        }

                        $actorId = Auth::id() ?? ($act['approval'] ? $act['approval']->approver_id : null);
                        $isLast = ($index === $totalActions - 1);

                        UnitLifecycle::create([
                            'unit_id' => $unit->id,
                            'action_type' => $act['action_type'],
                            'status' => $unit->status,
                            'condition' => $unit->condition,
                            'location_id' => $unit->location_id,
                            'floor_id' => $unit->floor_id,
                            'room_id' => $unit->room_id,
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
        static::deleted(function (Unit $unit) {
            UnitLifecycle::where('unit_id', $unit->id)
                ->whereNull('end_date')
                ->update(['end_date' => now()]);
        });
    }

    protected $with = ['lot', 'location', 'floor', 'room'];

    protected $fillable = [
        'uuid',
        'number',
        'lot_id',
        'location_id',
        'floor_id',
        'room_id',
        'status',
        'condition',
        'price',
        'image_url',
        'vehicle_registration',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class);
    }

    public function statusApprovals(): HasMany
    {
        return $this->hasMany(UnitStatusApproval::class);
    }

    public function lifecycles(): HasMany
    {
        return $this->hasMany(UnitLifecycle::class);
    }

    public function getNumberAttribute(?string $value): ?string
    {
        return $value !== null ? trim($value) : null;
    }

    public function getIsVehicleAttribute(): bool
    {
        $this->loadMissing('lot.barang.subcategory.category');
        $lot = $this->lot;
        if ($lot && $lot->barang && $lot->barang->subcategory && $lot->barang->subcategory->category) {
            $catName = strtolower($lot->barang->subcategory->category->name);
            $subcatName = strtolower($lot->barang->subcategory->name);
            return str_contains($catName, 'kendaraan') || str_contains($subcatName, 'kendaraan') ||
                   str_contains($catName, 'mobil') || str_contains($subcatName, 'mobil') ||
                   str_contains($catName, 'motor') || str_contains($subcatName, 'motor');
        }
        return false;
    }
}
