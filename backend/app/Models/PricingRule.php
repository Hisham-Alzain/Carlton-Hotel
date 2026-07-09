<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingRule extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    const SCOPE_SEASONAL = 'seasonal';
    const SCOPE_WEEKEND  = 'weekend';
    const SCOPE_HOLIDAY  = 'holiday';

    const TYPE_PERCENTAGE = 'percentage';
    const TYPE_FLAT       = 'flat';

    protected $fillable = [
        'room_type_id',
        'scope',
        'starts_on',
        'ends_on',
        'modifier_type',
        'modifier_value',
        'is_active',
    ];

    protected $casts = [
        'starts_on'      => 'date',
        'ends_on'        => 'date',
        'modifier_value' => 'decimal:2',
        'is_active'      => 'boolean',
    ];

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}
