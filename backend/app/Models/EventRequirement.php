<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRequirement extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    protected $fillable = ['event_inquiry_id', 'type', 'notes'];

    public function inquiry(): BelongsTo { return $this->belongsTo(EventInquiry::class, 'event_inquiry_id'); }
}
