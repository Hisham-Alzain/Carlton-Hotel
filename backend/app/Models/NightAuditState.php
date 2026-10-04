<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The hotel's business-date state (Phase 9, D-03): a singleton row
 * (`singleton` = 1). It is created by the first night-audit opener that
 * supplies a date and advanced only by `CloseNightAuditAction`. Every audit
 * write locks this row first (D-11). Never public, so no uuid.
 */
class NightAuditState extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['singleton', 'current_business_date', 'last_closed_date'];

    protected $casts = [
        'singleton'             => 'integer',
        'current_business_date' => 'date:Y-m-d',
        'last_closed_date'      => 'date:Y-m-d',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['current_business_date', 'last_closed_date'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
