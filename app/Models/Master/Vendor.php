<?php

namespace App\Models\Master;

use App\Models\Inventory\Lot;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master Vendor model representing external suppliers and vendor contact information.
 */
class Vendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'address',
        'phone_number',
        'email',
        'description',
    ];

    /**
     * Contact persons for this vendor.
     * VENDOR ||--o{ VENDORS_CP : "has many contact persons"
     */
    public function contactPersons(): HasMany
    {
        return $this->hasMany(VendorCp::class, 'vendor_id');
    }

    /**
     * Lots supplied by this legacy vendor.
     * VENDOR ||--o{ LOT : "supplied by (legacy)"
     */
    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class, 'legacy_vendor_id');
    }
}
