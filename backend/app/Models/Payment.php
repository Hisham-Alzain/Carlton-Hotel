<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    protected $fillable = [
        'payable_type', 'payable_id', 'method',
        'amount_usd', 'recorded_by', 'note', 'status',
    ];

    protected $casts = ['amount_usd' => 'decimal:2'];

    public function payable(): MorphTo   { return $this->morphTo(); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
    public function refunds(): HasMany   { return $this->hasMany(Refund::class); }
}
