<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vendor Contact Person model representing 1:N contact persons for a vendor.
 */
class VendorCp extends Model
{
    use HasFactory;

    protected $table = 'vendors_cp';

    protected $fillable = [
        'vendor_id',
        'contact_person',
        'cp_email',
        'cp_phone',
    ];

    /**
     * The vendor that owns this contact person.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }
}
