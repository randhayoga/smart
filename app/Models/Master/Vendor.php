<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'contact_person_1',
        'cp_email_1',
        'cp_phone_1',
        'contact_person_2',
        'cp_email_2',
        'cp_phone_2',
    ];

    /**
     * Lots supplied by this vendor.
     * VENDOR ||--o{ LOT : "supplied by"
     */
    public function lots(): HasMany
    {
        return $this->hasMany(\App\Models\Inventory\Lot::class);
    }
}
