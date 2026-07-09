<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RatePlan extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    protected $fillable = ['room_type_id', 'name', 'prepay_required', 'is_active'];

    protected $casts = [
        'prepay_required' => 'boolean',
        'is_active'       => 'boolean',
    ];

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}
