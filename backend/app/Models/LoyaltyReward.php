<?php

namespace App\Models;

use App\Enums\LoyaltyRewardType;
use App\Traits\HasTranslations;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A staff-managed catalog reward (Phase 10, Q18). Soft-deletable (recycle bin);
 * vouchers snapshot the reward so a purge never breaks their history.
 */
class LoyaltyReward extends Model
{
    use HasFactory, HasTranslations, HasUuid, LogsActivity, SoftDeletes;

    protected $translatable = ['name', 'description'];

    protected $fillable = [
        'name', 'description', 'type', 'points_cost', 'discount_usd',
        'voucher_valid_days', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => LoyaltyRewardType::class,
            'points_cost' => 'integer',
            'discount_usd' => 'decimal:2',
            'voucher_valid_days' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(LoyaltyVoucher::class);
    }
}
