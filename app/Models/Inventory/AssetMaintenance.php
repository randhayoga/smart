<?php

namespace App\Models\Inventory;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AssetMaintenance Model
 *
 * @property int $id
 * @property int $asset_id
 * @property int $maintainer_id
 * @property int|null $user_id
 * @property string $type
 * @property string $source
 * @property string|null $reference
 * @property string $problem
 * @property string $solution
 * @property float $cost
 * @property \Carbon\Carbon $date
 * @property string|null $image_url
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class AssetMaintenance extends Model
{
    use HasFactory;

    protected $table = 'asset_maintenances';

    protected $fillable = [
        'asset_id',
        'maintainer_id',
        'user_id',
        'type',
        'source',
        'reference',
        'problem',
        'solution',
        'cost',
        'date',
        'image_url',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'cost' => 'float',
    ];

    /**
     * The maintained asset.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    /**
     * User who performed the maintenance (the admin).
     */
    public function maintainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'maintainer_id');
    }

    /**
     * User who requested or was assigned to the asset.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
