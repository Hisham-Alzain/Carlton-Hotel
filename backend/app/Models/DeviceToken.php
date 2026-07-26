<?php

namespace App\Models;

use App\Enums\DevicePlatform;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceToken extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    protected $fillable = ['guest_id', 'token', 'platform', 'last_used_at'];

    protected function casts(): array
    {
        return [
            'platform'     => DevicePlatform::class,
            'last_used_at' => 'datetime',
        ];
    }

    public function guest(): BelongsTo { return $this->belongsTo(Guest::class); }
}
