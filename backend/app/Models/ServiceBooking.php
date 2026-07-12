<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ServiceBooking extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    const STATUS_PENDING   = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'guest_id', 'reservation_id', 'bookable_type', 'bookable_id',
        'scheduled_at', 'status', 'notes',
    ];

    protected $casts = ['scheduled_at' => 'datetime'];

    public function guest(): BelongsTo       { return $this->belongsTo(Guest::class); }
    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function bookable(): MorphTo      { return $this->morphTo(); }
}
