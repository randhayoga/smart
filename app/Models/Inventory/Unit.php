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

class Unit extends Model
{
    use HasFactory;

    protected static function booted()
    {
        // 1. Log when a Unit is created
        static::created(function ($unit) {
            if ($unit->status !== 'Pending') {
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
                    'actor_id' => auth()->id(),
                    'note' => 'Aset berhasil diregistrasi ke dalam sistem.',
                ]);
            }
        });

        // 2. Log when a Unit is updated
        static::updating(function ($unit) {
            $dirty = $unit->getDirty();
            $tracked = ['status', 'condition', 'location_id', 'floor_id', 'room_id'];
            $changed = array_intersect(array_keys($dirty), $tracked);

            if (!empty($changed)) {
                if ($unit->isDirty('status') && $unit->status === 'Pending') {
                    return;
                }

                UnitLifecycle::where('unit_id', $unit->id)
                    ->whereNull('end_date')
                    ->update(['end_date' => now()]);

                $notes = [];
                $actionType = 'Perubahan status';
                $approval = null;

                if ($unit->isDirty('status')) {
                    $oldStatus = $unit->getOriginal('status');
                    $newStatus = $unit->status;
                    $actionType = 'Perubahan status';

                    if ($oldStatus === 'Pending') {
                        $approval = UnitStatusApproval::where('unit_id', $unit->id)
                            ->whereIn('decision', ['approved', 'rejected'])
                            ->orderBy('updated_at', 'desc')
                            ->first();

                        if ($approval) {
                            $notes[] = $approval->note ?? "Pengajuan status '{$approval->proposed_status}' " . ($approval->decision === 'approved' ? 'disetujui.' : 'ditolak.');
                        } else {
                            $notes[] = "Status diubah dari '{$oldStatus}' menjadi '{$newStatus}'.";
                        }
                    } else {
                        $notes[] = "Status diubah dari '{$oldStatus}' menjadi '{$newStatus}'.";
                    }
                }

                if ($unit->isDirty('condition')) {
                    $notes[] = "Kondisi fisik diubah dari '{$unit->getOriginal('condition')}' menjadi '{$unit->condition}'.";
                    $actionType = 'Perubahan kondisi';
                }

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

                    $notes[] = "Lokasi dipindahkan dari '{$oldPath}' ke '{$newPath}.'";
                    $actionType = 'Pemindahan';
                }

                $note = implode(' | ', $notes) ?: 'Detail aset diperbarui.';
                $previousState = array_intersect_key($unit->getOriginal(), array_flip($tracked));
                $newState = array_intersect_key($unit->toArray(), array_flip($tracked));

                $actorId = auth()->id() ?? ($approval ? $approval->approver_id : null);

                UnitLifecycle::create([
                    'unit_id' => $unit->id,
                    'action_type' => $actionType,
                    'status' => $unit->status,
                    'condition' => $unit->condition,
                    'location_id' => $unit->location_id,
                    'floor_id' => $unit->floor_id,
                    'room_id' => $unit->room_id,
                    'start_date' => now(),
                    'end_date' => null,
                    'actor_id' => $actorId,
                    'note' => $note,
                    'previous_state' => $previousState,
                    'new_state' => $newState,
                ]);
            }
        });

        // 3. Close lifecycle on delete
        static::deleted(function ($unit) {
            UnitLifecycle::where('unit_id', $unit->id)
                ->whereNull('end_date')
                ->update(['end_date' => now()]);
        });
    }

    protected $with = ['lot', 'location', 'floor', 'room'];

    protected $fillable = [
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

    public function getNumberAttribute($value)
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
