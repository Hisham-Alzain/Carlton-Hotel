<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    protected $fillable = [
        'payment_id', 'amount_usd', 'reason', 'recorded_by', 'status',
    ];

    protected $casts = ['amount_usd' => 'decimal:2'];

    public function payment(): BelongsTo  { return $this->belongsTo(Payment::class); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
}
