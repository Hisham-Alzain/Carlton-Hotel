<?php

namespace App\Models;

use App\Enums\NightAuditCheckStatus;
use App\Enums\NightAuditCheckType;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The persisted review of one check category within a night audit (Phase 9,
 * D-06). `type`, `blocking`, `issue_count`, `evidence` and
 * `evidence_truncated` are the snapshot, written once at open. Staff move a
 * `pending` check to `resolved` or `overridden` with a mandatory note.
 *
 * `note` and `evidence` are never activity-logged (D-14).
 */
class NightAuditCheck extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    protected $fillable = [
        'night_audit_id', 'type', 'blocking', 'status', 'issue_count', 'evidence',
        'evidence_truncated', 'note', 'acted_by', 'acted_at',
    ];

    protected $casts = [
        'type'               => NightAuditCheckType::class,
        'status'             => NightAuditCheckStatus::class,
        'blocking'           => 'boolean',
        'issue_count'        => 'integer',
        'evidence'           => 'array',
        'evidence_truncated' => 'boolean',
        'acted_at'           => 'datetime',
    ];

    public function audit(): BelongsTo { return $this->belongsTo(NightAudit::class, 'night_audit_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'acted_by'); }
    public function blocker(): HasOne  { return $this->hasOne(NightAuditBlocker::class); }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'acted_by'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
