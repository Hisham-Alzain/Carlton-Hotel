<?php

namespace App\Models;

use App\Enums\ModifierType;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    protected $fillable = [
        'code',
        'type',
        'value',
        'expires_at',
        'max_uses',
        'used_count',
        'is_active',
    ];

    protected $casts = [
        'type'       => ModifierType::class,
        'expires_at' => 'datetime',
        'value'      => 'decimal:2',
        'is_active'  => 'boolean',
    ];

    public function isValid(): bool
    {
        if (! $this->is_active) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) return false;
        return true;
    }
}
