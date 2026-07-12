<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckInApproval extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    protected $fillable = ['reservation_id', 'status', 'approved_by', 'notes'];

    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function approver(): BelongsTo    { return $this->belongsTo(User::class, 'approved_by'); }
}
