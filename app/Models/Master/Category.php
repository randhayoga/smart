<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
    ];

    public function subcategories(): HasMany
    {
        return $this->hasMany(Subcategory::class);
    }
}
