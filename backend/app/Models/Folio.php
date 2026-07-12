<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Folio extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    const STATUS_OPEN     = 'open';
    const STATUS_SETTLED  = 'settled';

    protected $fillable = [
        'reservation_id', 'status', 'subtotal_usd', 'total_usd',
        'approved_by_guest_at', 'settled_at',
    ];

    protected $casts = [
        'subtotal_usd'         => 'decimal:2',
        'total_usd'            => 'decimal:2',
        'approved_by_guest_at' => 'datetime',
        'settled_at'           => 'datetime',
    ];

    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function items(): HasMany         { return $this->hasMany(FolioItem::class); }
    public function payments(): MorphMany     { return $this->morphMany(Payment::class, 'payable'); }
}
