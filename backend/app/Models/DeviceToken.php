<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceToken extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    const PLATFORM_IOS     = 'ios';
    const PLATFORM_ANDROID = 'android';
    const PLATFORM_WEB     = 'web';

    protected $fillable = ['guest_id', 'token', 'platform', 'last_used_at'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function guest(): BelongsTo { return $this->belongsTo(Guest::class); }
}
