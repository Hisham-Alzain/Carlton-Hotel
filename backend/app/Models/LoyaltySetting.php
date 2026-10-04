<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The loyalty program settings singleton (Phase 10, Q4, LOY-02): at most one
 * row (`singleton` = 1), created by the first dashboard save - never seeded.
 *
 * A null rate means that capability is off; consumers (`LoyaltyProgram`) throw
 * rather than default it. Read fresh on each use and never cached (M-8).
 */
class LoyaltySetting extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'singleton', 'earn_rate', 'redeem_value_usd', 'expiry_months', 'expiry_warning_days',
        'min_redeem_points', 'max_redeem_percent', 'updated_by',
    ];

    /** Mirrors the column defaults so an unsaved instance reads the same as a saved one. */
    protected $attributes = [
        'singleton' => 1,
        'expiry_months' => 24,
        'expiry_warning_days' => 30,
    ];

    protected function casts(): array
    {
        return [
            'earn_rate' => 'decimal:4',
            'redeem_value_usd' => 'decimal:4',
            'expiry_months' => 'integer',
            'expiry_warning_days' => 'integer',
            'min_redeem_points' => 'integer',
            'max_redeem_percent' => 'decimal:2',
        ];
    }

    /** The six program values and who last changed them. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'earn_rate', 'redeem_value_usd', 'expiry_months', 'expiry_warning_days',
                'min_redeem_points', 'max_redeem_percent', 'updated_by',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
