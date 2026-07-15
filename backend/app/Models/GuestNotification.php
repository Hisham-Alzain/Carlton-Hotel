<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestNotification extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    const TYPE_WELCOME        = 'welcome';
    const TYPE_ROOM_READY     = 'room_ready';
    const TYPE_INQUIRY_ROUTED = 'inquiry_routed';

    protected $fillable = [
        'guest_id', 'department', 'type', 'title', 'body', 'data', 'sent_at', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data'    => 'array',
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function guest(): BelongsTo { return $this->belongsTo(Guest::class); }
}
