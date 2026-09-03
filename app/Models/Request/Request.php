<?php

namespace App\Models\Request;

use App\Models\AdmUser;
use App\Models\HrdOrgchart;
use App\Models\TbProject;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Request extends Model
{
    use HasUuids;

    protected $table = 'requests';

    protected $fillable = [
        'uuid',
        'request_number',
        'user_id',
        'approver_id',
        'utilization',
        'org_id',
        'project_id',
        'reasoning',
        'status',
    ];

    /**
     * Generate a UUIDv7 for the model.
     */
    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    /**
     * Specify the column that receives the generated UUID.
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Get the route key for implicit route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Normalize UUID attribute to lowercase.
     */
    public function getUuidAttribute($value): ?string
    {
        return $value ? strtolower((string) $value) : null;
    }

    protected $casts = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(AdmUser::class, 'user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(AdmUser::class, 'approver_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(HrdOrgchart::class, 'org_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(TbProject::class, 'project_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RequestItem::class, 'request_id');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(RequestStatusLog::class, 'request_id');
    }

    public function approval(): HasOne
    {
        return $this->hasOne(RequestApproval::class, 'request_id')->latestOfMany();
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(RequestApproval::class, 'request_id');
    }

    public function adminConfirmation(): HasOne
    {
        return $this->hasOne(RequestAdminConfirmation::class, 'request_id')->latestOfMany();
    }

    public function adminConfirmations(): HasMany
    {
        return $this->hasMany(RequestAdminConfirmation::class, 'request_id');
    }

    public function handover(): HasOne
    {
        return $this->hasOne(RequestHandover::class, 'request_id');
    }

    public function handovers(): HasMany
    {
        return $this->hasMany(RequestHandover::class, 'request_id');
    }

    public function return(): HasOne
    {
        return $this->hasOne(RequestReturn::class, 'request_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(RequestReturn::class, 'request_id');
    }

    /**
     * Determine if this request is a borrowing request (Peminjaman).
     */
    public function isBorrow(): bool
    {
        if ($this->relationLoaded('items')) {
            return $this->items->contains(fn($item) => $item->start_date !== null);
        }

        return $this->items()->whereNotNull('start_date')->exists();
    }

    /**
     * Capitalized request type name ('Peminjaman' or 'Permintaan').
     */
    public function getTypeNameAttribute(): string
    {
        return $this->isBorrow() ? 'Peminjaman' : 'Permintaan';
    }

    /**
     * Lowercase request type identifier ('peminjaman' or 'permintaan').
     */
    public function getTypeKeyAttribute(): string
    {
        return $this->isBorrow() ? 'peminjaman' : 'permintaan';
    }

    /**
     * Formatted destination / utilization string (Corporate or Project).
     */
    public function getDestinationNameAttribute(): string
    {
        if ($this->utilization === 'corporate') {
            $deptName = $this->department?->org_name ?? $this->department?->name ?? 'Departemen';
            return "Corporate ({$deptName})";
        }

        $projNo = $this->project?->no_project;
        $projName = $this->project?->project_name ?? 'Project';
        return $projNo ? "Project {$projNo} ({$projName})" : "Project ({$projName})";
    }

    /**
     * Accessor for start_date from the first request item.
     */
    public function getStartDateAttribute()
    {
        if ($this->relationLoaded('items')) {
            return $this->items->first()?->start_date;
        }

        return $this->items()->whereNotNull('start_date')->value('start_date');
    }

    /**
     * Accessor for end_date from the first request item.
     */
    public function getEndDateAttribute()
    {
        if ($this->relationLoaded('items')) {
            return $this->items->first()?->end_date;
        }

        return $this->items()->whereNotNull('end_date')->value('end_date');
    }
}

