<?php

namespace App\Models;

use App\Enums\NightAuditBlockerStatus;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A close blocker raised by a non-empty blocking check (Phase 9, D-06): only
 * `unsettled_departures` and `unassigned_arrivals`. Type, count and evidence
 * are read through the check. Resolving is a noted attestation with no
 * override; it never edits source data.
 *
 * `note` is never activity-logged (D-14).
 */
class NightAuditBlocker extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    protected $fillable = [
        'night_audit_id', 'night_audit_check_id', 'status', 'note', 'acted_by', 'acted_at',
    ];

    protected $casts = [
        'status'   => NightAuditBlockerStatus::class,
        'acted_at' => 'datetime',
    ];

    public function audit(): BelongsTo { return $this->belongsTo(NightAudit::class, 'night_audit_id'); }
    public function check(): BelongsTo { return $this->belongsTo(NightAuditCheck::class, 'night_audit_check_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'acted_by'); }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'acted_by'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
